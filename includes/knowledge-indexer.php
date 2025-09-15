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
        require_once ABSPATH . 'wp-admin/includes/file.php';
        $uploaded_paths = get_option('dual_chatbot_uploaded_files', []);
        // Loop through each uploaded file.
        foreach ($uploads['name'] as $index => $name) {
            if ($uploads['error'][$index] !== UPLOAD_ERR_OK) {
                continue;
            }
            $file_array = [
                'name'     => $uploads['name'][$index],
                'type'     => $uploads['type'][$index],
                'tmp_name' => $uploads['tmp_name'][$index],
                'error'    => 0,
                'size'     => $uploads['size'][$index],
            ];
            $override = [
                'test_form' => false,
                'mimes'     => ['txt' => 'text/plain', 'pdf' => 'application/pdf', 'csv' => 'text/csv'],
            ];
            $result = wp_handle_upload($file_array, $override);
            if (!empty($result['error'])) {
                continue;
            }
            $uploaded_paths[] = $result['file'];
        }
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
                $total_chunks++;
            } catch (Exception $e) {
                // Stop processing on error to avoid hitting rate limits.
                throw $e;
            }
        }
        return $total_chunks;
    }

    /**
     * Extract textual content from a given file path.  Supports .txt and
     * .pdf files.  For PDF files the function attempts to use the `pdftotext`
     * binary if available.  Returns an empty string on failure.
     */
    private function extract_text_from_file(string $file_path): string {
        $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        if ($extension === 'txt' || $extension === 'csv') {
            return file_get_contents($file_path) ?: '';
        }
        if ($extension === 'pdf') {
            // Try to convert PDF to text via system binary.
            $cmd = sprintf('pdftotext %s -', escapeshellarg($file_path));
            $output = shell_exec($cmd);
            if (!empty($output)) {
                return $output;
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
