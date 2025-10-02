<?php
/**
 * Knowledge indexing for the Dual Chatbot Plugin.
 *
 * This class handles file uploads from the admin page, extracts textual
 * content from supported formats, generates embeddings via OpenAI, and
 * stores the resulting vectors in the custom database table.  Indexing can
 * be resource intensive and is intended to be run on demand via the
 * admin interface.
 */
if (!defined('ABSPATH')) {
    exit;
}

class Dual_Chatbot_Knowledge_Indexer {
    /** Ensure protected upload dir exists and is access-protected. */
    private function ensure_protected_upload_dir(): array {
        $up = wp_upload_dir();
        $base = trailingslashit($up['basedir']) . 'dual-chatbot';
        $url  = trailingslashit($up['baseurl']) . 'dual-chatbot';
        if (!is_dir($base)) { wp_mkdir_p($base); }
        // Add protection files (best-effort)
        $htaccess = $base . '/.htaccess';
        if (!file_exists($htaccess)) {
            @file_put_contents($htaccess, "# Dual Chatbot protected uploads\nOptions -Indexes\n<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n  Deny from all\n</IfModule>\n");
        }
        $webconfig = $base . '/web.config';
        if (!file_exists($webconfig)) {
            @file_put_contents($webconfig, "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration>\n  <system.webServer>\n    <security>\n      <authorization>\n        <remove users=\"*\" roles=\"\" verbs=\"\"/>\n        <add accessType=\"Deny\" users=\"*\" />\n      </authorization>\n    </security>\n  </system.webServer>\n</configuration>\n");
        }
        $index = $base . '/index.html';
        if (!file_exists($index)) { @file_put_contents($index, ""); }
        return ['dir' => $base, 'url' => $url];
    }
    /**
     * Entry point for processing uploaded files and indexing their content.
     * Returns the total number of chunks processed.
     *
     * @throws Exception if an error occurs during indexing.
     */
    public function process_uploaded_files(): int {
        // Handle newly uploaded files (if any) from the settings page form.
        if (!empty($_FILES['dual_chatbot_documents']['name'][0])) {
            $this->handle_file_uploads($_FILES['dual_chatbot_documents']);
        }
        // Retrieve stored file list from options.
        $files = get_option('dual_chatbot_uploaded_files', []);
        $processed_chunks = 0;
        foreach ($files as $file) {
            dual_chatbot_log('Indexiere Datei: ' . $file);
            $processed_chunks += $this->index_file($file);
        }
        dual_chatbot_log('Indexierung abgeschlossen: ' . $processed_chunks . ' Abschnitte');
        return $processed_chunks;
    }

    /**
     * Handle file uploads using WordPress API and store file paths in
     * `dual_chatbot_uploaded_files` option.
     */
    private function handle_file_uploads(array $uploads): void {
        if (!current_user_can('manage_options')) {
            wp_die(__('Forbidden', 'dual-chatbot'), 403);
        }
        // Accept either a dedicated form nonce or rely on the AJAX reindex nonce already validated upstream
        if (isset($_POST['_dcb_idx_nonce'])) {
            check_admin_referer('dual_chatbot_indexer', '_dcb_idx_nonce');
        }
        require_once ABSPATH . 'wp-admin/includes/file.php';
        $uploaded_paths = get_option('dual_chatbot_uploaded_files', []);
        $protected = $this->ensure_protected_upload_dir();
        $targetDir = $protected['dir'];
        $whitelist = [
            'pdf'  => 'application/pdf',
            'txt'  => 'text/plain',
            'md'   => 'text/markdown',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'csv'  => 'text/csv',
        ];
        // Temporarily redirect uploads into protected subdir
        $upload_dir_filter = function($dirs) use ($targetDir) {
            $dirs['path'] = $targetDir;
            $dirs['url']  = trailingslashit($dirs['baseurl']) . 'dual-chatbot';
            $dirs['subdir'] = '/dual-chatbot';
            return $dirs;
        };
        add_filter('upload_dir', $upload_dir_filter, 10, 1);
        // Loop through each uploaded file.
        foreach ($uploads['name'] as $index => $name) {
            if (($uploads['error'][$index] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) { continue; }
            $raw_name = sanitize_file_name((string)$uploads['name'][$index]);
            $file_array = [
                'name'     => $raw_name,
                'type'     => $uploads['type'][$index],
                'tmp_name' => $uploads['tmp_name'][$index],
                'error'    => 0,
                'size'     => $uploads['size'][$index],
            ];
            $override = [ 'test_form' => false, 'mimes' => $whitelist ];
            $result = wp_handle_upload($file_array, $override);
            if (!empty($result['error'])) { continue; }
            $path = $result['file'];
            $safe_name = sanitize_file_name(basename($path));
            // Double-check type/extension
            $ft = wp_check_filetype_and_ext($path, $safe_name, $whitelist);
            if (empty($ft['ext']) || empty($ft['type']) || !isset($whitelist[$ft['ext']])) {
                @unlink($path);
                continue;
            }
            // Store only files inside our protected folder
            if (strpos($path, $targetDir) === 0) {
                $uploaded_paths[] = $path;
            } else {
                // Move into protected dir if needed
                $new_path = trailingslashit($targetDir) . $safe_name;
                if (@rename($path, $new_path)) {
                    $uploaded_paths[] = $new_path;
                } else {
                    // fallback: leave as-is but do not store
                }
            }
        }
        remove_filter('upload_dir', $upload_dir_filter, 10);
        update_option('dual_chatbot_uploaded_files', $uploaded_paths);
    }

    /**
     * Index a single file by reading its contents, splitting it into chunks,
     * generating embeddings, and inserting them into the knowledge table.
     * Returns the number of processed chunks.
     */
    private function index_file(string $file_path): int {
        global $wpdb;
        $knowledge_table = $wpdb->prefix . 'chatbot_knowledge';
        $file_name = basename($file_path);
        // Remove existing entries for this file to avoid duplicates.
        $wpdb->delete($knowledge_table, ['file_name' => $file_name], ['%s']);
        if (!empty($wpdb->last_error)) {
            error_log('[dual-chatbot] sql_error rid=' . wp_generate_uuid4() . ' err=' . $wpdb->last_error);
        }
        $text = $this->extract_text_from_file($file_path);
        if (empty($text)) {
            return 0;
        }
        // Split text into roughly 1000 character chunks preserving word boundaries.
        $chunks = $this->split_into_chunks($text, 1000);
        $api_key = get_option(Dual_Chatbot_Plugin::OPTION_OPENAI_API_KEY, '');
        $total_chunks = 0;
        foreach ($chunks as $index => $chunk) {
            $chunk = trim($chunk);
            if (empty($chunk)) {
                continue;
            }
            try {
                $embedding = $this->create_embedding($chunk, $api_key);
                $wpdb->insert($knowledge_table, [
                    'file_name'   => $file_name,
                    'chunk_index' => $index,
                    'chunk_text'  => $chunk,
                    'embedding'   => json_encode($embedding),
                ], ['%s','%d','%s','%s']);
                if (!empty($wpdb->last_error)) {
                    error_log('[dual-chatbot] sql_error rid=' . wp_generate_uuid4() . ' err=' . $wpdb->last_error);
                }
                $total_chunks++;
            } catch (Exception $e) {
                // Stop processing on error to avoid hitting rate limits.
                throw $e;
            }
        }
        return $total_chunks;
    }

    /**
     * Extract textual content from a given file path.
     *
     * Supports .txt and .csv directly. For .pdf it first tries
     * Smalot\PdfParser\Parser (if present), otherwise falls back to
     * the `pdftotext` binary. If neither is available/working, returns
     * an empty string and logs a hint.
     */
    private function extract_text_from_file(string $file_path): string {
        $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        if ($extension === 'txt' || $extension === 'csv') {
            return file_get_contents($file_path) ?: '';
        }
        if ($extension === 'pdf') {
            // 1) Prefer PHP library if available (no new dependency enforced).
            try {
                if (class_exists('Smalot\\PdfParser\\Parser')) {
                    $parser = new \Smalot\PdfParser\Parser();
                    $pdf    = $parser->parseFile($file_path);
                    $text   = $pdf->getText();
                    if (is_string($text) && trim($text) !== '') {
                        return $text;
                    }
                }
            } catch (\Throwable $e) {
                // Ignore and fall back to shell approach.
            }
            // 2) Fallback: use pdftotext via shell if available/working.
            if (function_exists('shell_exec')) {
                $cmd = sprintf('pdftotext %s -', escapeshellarg($file_path));
                $output = @shell_exec($cmd);
                if (is_string($output) && trim($output) !== '') {
                    return $output;
                }
            }
            // 3) Both approaches unavailable/failed: mark transient, remember time, return empty string, and log hint.
            if (function_exists('set_transient')) {
                set_transient('dcb_pdf_extract_missing', 1, DAY_IN_SECONDS);
            }
            if (function_exists('update_option')) {
                update_option('dcb_pdf_extract_missing_time', time(), false);
            }
            if (function_exists('dual_chatbot_log')) {
                dual_chatbot_log('PDF-Extraktion nicht verfuegbar: Bitte Smalot\\PdfParser oder das pdftotext-Tool installieren.');
            } else {
                error_log('[dual-chatbot] PDF-Extraktion nicht verfuegbar: Bitte Smalot\\PdfParser oder das pdftotext-Tool installieren.');
            }
        }
        return '';
    }

    /**
     * Split a long string into an array of chunks of approximately the given
     * length, attempting to avoid breaking words in half.  Each chunk ends
     * at a whitespace boundary nearest to the target length.
     */
    private function split_into_chunks(string $text, int $length): array {
        $words = preg_split('/\s+/', $text);
        $chunks = [];
        $current = '';
        foreach ($words as $word) {
            if (mb_strlen($current . ' ' . $word) > $length && !empty($current)) {
                $chunks[] = $current;
                $current = $word;
            } else {
                $current .= (empty($current) ? '' : ' ') . $word;
            }
        }
        if (!empty($current)) {
            $chunks[] = $current;
        }
        return $chunks;
    }

    /**
     * Generate an embedding for a text chunk using the OpenAI Embeddings API.
     * Throws an exception on error.  A dedicated API key must be provided.
     */
    private function create_embedding(string $text, string $api_key): array {
        if (empty($api_key)) {
            throw new Exception(__('OpenAI API Key fehlt.', 'dual-chatbot'));
        }
        $endpoint = 'https://api.openai.com/v1/embeddings';
        $body = [
            'model' => 'text-embedding-ada-002',
            'input' => mb_substr($text, 0, 2048),
        ];
        $args = [
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ],
            'body' => wp_json_encode($body),
            'timeout' => 60,
        ];
        $response = wp_remote_post($endpoint, $args);
        if (is_wp_error($response)) {
            throw new Exception($response->get_error_message());
        }
        $status = wp_remote_retrieve_response_code($response);
        $json = json_decode(wp_remote_retrieve_body($response), true);
        if ($status !== 200 || empty($json['data'][0]['embedding'])) {
            $err = $json['error']['message'] ?? __('Fehler bei der Erstellung des Embeddings.', 'dual-chatbot');
            throw new Exception($err);
        }
        return $json['data'][0]['embedding'];
    }
}
