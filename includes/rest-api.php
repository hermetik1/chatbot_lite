<?php
/**
 * REST API endpoints for the Dual Chatbot Plugin.
 *
 * This class registers the necessary REST routes and handles inbound
 * requests.  Each endpoint validates the nonce provided by the front end
 * before performing any server‑side operations.  The endpoints support
 * submitting a chat message, retrieving past messages, and transcribing
 * audio via Whisper.
 */
if (!defined('ABSPATH')) {
    exit;
}

// Asset enqueue and widget markup are handled in the main plugin file.

// === KLASSEN-START ===
class Dual_Chatbot_Rest_API {
    private static ?Dual_Chatbot_Rest_API $instance = null;

    public static function get_instance(): Dual_Chatbot_Rest_API {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void {
        register_rest_route('chatbot/v1', '/submit_message', [
            'methods'  => 'POST',
            'callback' => [$this, 'handle_submit_message'],
            'permission_callback' => [$this, 'permission_callback'],
            'args'     => [
                'message' => [
                    'type' => 'string',
                    'required' => true,
                ],
                'context' => [
                    'type' => 'string',
                    'required' => true,
                    'enum' => ['faq', 'advisor'],
                ],
                'session_id' => [
                    'type' => 'string',
                    'required' => false,
                ],
                'client_msg_id' => [
                    'type' => 'string',
                    'required' => false,
                ],
                'web_search' => [
                    'type'     => 'boolean',
                    'required' => false,
                ],
            ],
        ]);

        register_rest_route('chatbot/v1', '/get_history', [
            'methods'  => 'GET',
            'callback' => [$this, 'handle_get_history'],
            'permission_callback' => [$this, 'permission_callback'],
            'args' => [
                'session_id' => [
                    'type' => 'string',
                    'required' => true,
                ],
            ],
        ]);

        register_rest_route('chatbot/v1', '/transcribe_audio', [
            'methods'  => 'POST',
            'callback' => [$this, 'handle_transcribe_audio'],
            'permission_callback' => [$this, 'permission_callback'],
        ]);

        register_rest_route('chatbot/v1', '/list_sessions', [
            'methods' => 'GET',
            'callback' => [$this, 'handle_list_sessions'],
            'permission_callback' => [$this, 'permission_callback'],
        ]);

        register_rest_route('chatbot/v1', '/check_membership', [
            'methods' => 'GET',
            'callback' => [$this, 'handle_check_membership'],
            'permission_callback' => [$this, 'permission_callback'],
        ]);

        register_rest_route('chatbot/v1', '/rename_session', [
            'methods'  => 'POST',
            'callback' => [$this, 'handle_rename_session'],
            'permission_callback' => [$this, 'permission_callback'],
            'args'    => [
                'session_id' => [ 'type' => 'string', 'required' => true ],
                'title'      => [ 'type' => 'string', 'required' => true ],
                'context'    => [ 'type' => 'string', 'required' => false ],
            ],
        ]);

        register_rest_route('chatbot/v1', '/delete_session', [
            'methods'  => 'POST',
            'callback' => [$this, 'handle_delete_session'],
            'permission_callback' => [$this, 'permission_callback'],
            'args'    => [
                'session_id' => [ 'type' => 'string', 'required' => true ],
                'context'    => [ 'type' => 'string', 'required' => false ],
            ],
        ]);

        register_rest_route('chatbot/v1', '/search_sessions', [
            'methods'  => 'GET',
            'callback' => [$this, 'handle_search_sessions'],
            'permission_callback' => [$this, 'permission_callback'],
            'args'    => [
                'query'   => [ 'type' => 'string', 'required' => true ],
                'context' => [ 'type' => 'string', 'required' => true ],
            ],
        ]);

        // Streaming endpoint (NDJSON over HTTP)
        register_rest_route('chatbot/v1', '/stream_message', [
            'methods'  => 'POST',
            'callback' => [$this, 'handle_stream_message'],
            'permission_callback' => [$this, 'permission_callback'],
            'args'     => [
                'message'    => [ 'type' => 'string',  'required' => true ],
                'context'    => [ 'type' => 'string',  'required' => true, 'enum' => ['faq','advisor'] ],
                'session_id' => [ 'type' => 'string',  'required' => false ],
                'client_msg_id' => [ 'type' => 'string', 'required' => false ],
                'web_search' => [ 'type' => 'boolean', 'required' => false ],
            ],
        ]);

        // Client-side finalize (safety persist) – saves a bot/user message to history
        register_rest_route('chatbot/v1', '/append_history', [
            'methods'  => 'POST',
            'callback' => [$this, 'handle_append_history'],
            'permission_callback' => [$this, 'permission_callback'],
            'args'     => [
                'session_id' => [ 'type' => 'string', 'required' => true ],
                'message'    => [ 'type' => 'string', 'required' => true ],
                'sender'     => [ 'type' => 'string', 'required' => true, 'enum' => ['user','bot'] ],
                'context'    => [ 'type' => 'string', 'required' => false, 'enum' => ['faq','advisor'] ],
                'client_msg_id' => [ 'type' => 'string', 'required' => false ],
                'reply_to_client_msg_id' => [ 'type' => 'string', 'required' => false ],
            ],
        ]);

        register_rest_route('chatbot/v1', '/edit_message', [
            'methods'  => 'POST',
            'callback' => [$this, 'handle_edit_message'],
            'permission_callback' => [$this, 'permission_callback'],
            'args'     => [
                'id'         => [ 'type' => 'integer', 'required' => true ],
                'session_id' => [ 'type' => 'string',  'required' => true ],
                'content'    => [ 'type' => 'string',  'required' => true ],
            ],
        ]);

        // Edit any message (user or bot). Used when regenerating to overwrite the bot answer in place.
        register_rest_route('chatbot/v1', '/edit_bot_message', [
            'methods'  => 'POST',
            'callback' => [$this, 'handle_edit_bot_message'],
            'permission_callback' => [$this, 'permission_callback'],
            'args'     => [
                'id'         => [ 'type' => 'integer', 'required' => true ],
                'session_id' => [ 'type' => 'string',  'required' => true ],
                'content'    => [ 'type' => 'string',  'required' => true ],
            ],
        ]);

        register_rest_route('chatbot/v1', '/delete_message', [
            'methods'  => 'POST',
            'callback' => [$this, 'handle_delete_message'],
            'permission_callback' => [$this, 'permission_callback'],
            'args'     => [
                'id'         => [ 'type' => 'integer', 'required' => true ],
                'session_id' => [ 'type' => 'string',  'required' => true ],
            ],
        ]);

        register_rest_route('chatbot/v1', '/react_message', [
            'methods'  => 'POST',
            'callback' => [$this, 'handle_react_message'],
            'permission_callback' => [$this, 'permission_callback'],
            'args'     => [
                'id'       => [ 'type' => 'integer', 'required' => true ],
                'reaction' => [ 'type' => 'string',  'required' => true, 'enum' => ['up','down'] ],
                'feedback' => [ 'type' => 'string',  'required' => false ],
            ],
        ]);
    }

    /** Ensure new idempotency columns exist on the history table. */
    private function ensure_history_columns(): void {
        global $wpdb;
        $history_table = $wpdb->prefix . 'chatbot_history';
        // Check once per request
        static $done = false; if ($done) return; $done = true;
        // Detect columns
        $db = $wpdb->dbname ? $wpdb->dbname : DB_NAME;
        $need_client = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=%s AND TABLE_NAME=%s AND COLUMN_NAME='client_msg_id'",
            $db, $history_table
        )) == 0;
        $need_reply = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=%s AND TABLE_NAME=%s AND COLUMN_NAME='reply_to_client_msg_id'",
            $db, $history_table
        )) == 0;
        if ($need_client) {
            $wpdb->query("ALTER TABLE `$history_table` ADD COLUMN `client_msg_id` varchar(64) NULL");
        }
        if ($need_reply) {
            $wpdb->query("ALTER TABLE `$history_table` ADD COLUMN `reply_to_client_msg_id` varchar(64) NULL");
        }
        // Add helpful indexes (best-effort)
        // Use IF NOT EXISTS where available; otherwise ignore errors.
        @$wpdb->query("CREATE INDEX idx_client_msg ON `$history_table` (session_id, client_msg_id)");
        @$wpdb->query("CREATE INDEX idx_reply_to_client_msg ON `$history_table` (session_id, reply_to_client_msg_id)");
    }

    public function permission_callback(WP_REST_Request $request): bool {
        $nonce = $request->get_header('x_wp_nonce');
        return wp_verify_nonce($nonce, 'wp_rest');
    }

    /**
     * Stream a model response chunk-by-chunk to the client using NDJSON lines.
     * Each line is a JSON object with keys: type (meta|delta|error|done) and payload.
     * The final bot response is persisted in history when the stream completes.
     */
    public function handle_stream_message(WP_REST_Request $request) {
        $this->ensure_history_columns();
        // Continue even if client disconnects (user switches chat/closes UI)
        if (function_exists('ignore_user_abort')) { @ignore_user_abort(true); }
        @set_time_limit(0);
        // Prepare parameters
        $message    = sanitize_text_field($request->get_param('message'));
        $context    = sanitize_text_field($request->get_param('context'));
        $session_id = sanitize_text_field($request->get_param('session_id'));
        $web_search = (bool) $request->get_param('web_search');
        $client_msg_id = sanitize_text_field($request->get_param('client_msg_id'));
        $no_user_insert = filter_var($request->get_param('no_user_insert'), FILTER_VALIDATE_BOOLEAN);
        if (empty($session_id)) {
            $session_id = wp_generate_uuid4();
        }
        $user_id = get_current_user_id();

        // Ensure session and conditionally save user message up front
        try {
            $this->create_or_update_session($session_id, $user_id, $context, $message);
            if (!$no_user_insert) {
                // Idempotent user insert (if not exists for this client_msg_id)
                global $wpdb; $history_table = $wpdb->prefix . 'chatbot_history';
                $exists_user = null;
                if (!empty($client_msg_id)) {
                    $exists_user = $wpdb->get_var($wpdb->prepare("SELECT id FROM $history_table WHERE session_id=%s AND sender='user' AND client_msg_id=%s LIMIT 1", $session_id, $client_msg_id));
                }
                if (!$exists_user) {
                    $this->insert_chat_history($user_id, $session_id, 'user', $message, $context, $client_msg_id, null);
                }
            }
        } catch (Exception $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 500);
        }

        // Disable WP output buffering for streaming
        if (!headers_sent()) {
            header('Content-Type: application/x-ndjson');
            header('Cache-Control: no-cache');
            header('X-Accel-Buffering: no'); // for nginx
        }
        // Flush all existing output buffers and enable implicit flush
        if (function_exists('ob_implicit_flush')) { @ob_implicit_flush(true); }
        while (ob_get_level() > 0) { ob_end_flush(); }
        flush();

        // Helper to emit a NDJSON line
        $emit = function(array $obj) {
            // Only write if the connection is still open
            if (connection_status() === CONNECTION_NORMAL) {
                echo wp_json_encode($obj, JSON_UNESCAPED_UNICODE) . "\n";
                @flush();
            }
        };

        // If bot already answered this client message, return existing without new LLM call
        global $wpdb; $history_table = $wpdb->prefix . 'chatbot_history';
        $target_bot_id = intval($request->get_param('target_bot_id'));
        if ($target_bot_id <= 0 && !empty($client_msg_id)) {
            $existing_bot = $wpdb->get_row($wpdb->prepare("SELECT message_content FROM $history_table WHERE session_id=%s AND sender='bot' AND reply_to_client_msg_id=%s LIMIT 1", $session_id, $client_msg_id), ARRAY_A);
            if ($existing_bot && isset($existing_bot['message_content'])) {
                $emit(['type'=>'meta','session_id'=>$session_id]);
                // emit whole content as one delta
                $emit(['type'=>'delta','content'=>$existing_bot['message_content']]);
                $emit(['type'=>'done']);
                exit;
            }
        }

        // Send meta with session id
        $emit(['type' => 'meta', 'session_id' => $session_id]);

        // Build messages similar to non-stream pipeline
        try {
            // Reuse existing bot row if target provided; otherwise create placeholder to update while streaming
            if ($target_bot_id > 0) {
                $bot_row_id = $target_bot_id;
                // Clear previous content to show fresh stream on reloads
                $this->update_history_message($bot_row_id, '');
            } else {
                $bot_row_id = $this->insert_bot_placeholder($user_id, $session_id, $context, $client_msg_id);
            }
            $last_saved_len = 0;
            $save_progress = function() use (&$full, &$last_saved_len, $bot_row_id) {
                if (strlen($full) > $last_saved_len) {
                    $this->update_history_message($bot_row_id, $full);
                    $last_saved_len = strlen($full);
                }
            };
            // local accumulators to persist even if connection drops
            $full = '';
            $saved = false;
            // Ensure that in any unexpected shutdown we still persist what we have
            register_shutdown_function(function() use (&$full, &$saved, $session_id, $user_id, $bot_row_id) {
                try {
                    if (!$saved && !empty($full)) {
                        // Final update to the placeholder row
                        $this->update_history_message($bot_row_id, $full);
                    }
                } catch (\Throwable $e) { /* swallow */ }
            });

            if ($context === 'faq') {
                // RAG style prompt with knowledge chunks
                $api_key = get_option(Dual_Chatbot_Plugin::OPTION_OPENAI_API_KEY, '');
                if (empty($api_key)) { throw new Exception(__('OpenAI API Key fehlt.', 'dual-chatbot')); }
                $embedding = $this->create_embedding($message);
                $chunks = $this->get_similar_chunks($embedding, 3);
                $context_text = '';
                foreach ($chunks as $chunk) { $context_text .= $chunk['chunk_text'] . "\n"; }
                $prompt = [
                    ['role' => 'system', 'content' => "Du bist ausschlieYlich ein Experte f2r 2sterreichisches Gemeinn2tzigkeits- und Vereinsrecht. Du beantwortest nur Fragen zu diesem Thema und verweigerst h2flich jede Auskunft zu anderen juristischen, steuerlichen oder gesellschaftlichen Themen, insbesondere zu anderen L2ndern."],
                ];
                // Style & grammar instruction to avoid chatty openings and ensure proper German
                $prompt[] = ['role' => 'system', 'content' => 'Antworte ausschließlich auf Deutsch, in korrekter Rechtschreibung und Grammatik, ohne Begrüßungen, Floskeln oder Entschuldigungen. Formuliere präzise und klar.'];
                if (trim($context_text) !== '') {
                    $prompt[] = ['role' => 'assistant', 'content' => "Wissensausz2ge:\n" . $context_text];
                }
                $prompt[] = ['role' => 'user', 'content' => $message];
                $model = get_option(Dual_Chatbot_Plugin::OPTION_FAQ_MODEL, 'gpt-4o');
                $ret = $this->call_chat_api_stream($prompt, $model, $api_key, function($delta) use ($emit, &$full, $save_progress) {
                    if ($delta !== '') {
                        $full .= $delta;
                        $emit(['type'=>'delta','content'=>$delta]);
                        // Persist progress so reload/switch shows partial text
                        $save_progress();
                    }
                });
                if (is_string($ret) && strlen($ret) > strlen($full)) { $full = $ret; }
            } else { // advisor
                // Verify membership for anonymous
                if ($user_id === 0 && !$this->verify_membership_via_myaplefy($session_id)) {
                    $emit(['type'=>'error','message'=>__('Dein Mitgliedsstatus konnte nicht besttigt werden.', 'dual-chatbot')]);
                    $emit(['type'=>'done']);
                    exit;
                }
                $api_key = get_option(Dual_Chatbot_Plugin::OPTION_OPENAI_API_KEY, '');
                if (empty($api_key)) { throw new Exception(__('OpenAI API Key fehlt.', 'dual-chatbot')); }
                $history = $this->load_conversation_history($user_id, $session_id);
                $messages = [];
                $messages[] = ['role'=>'system','content'=>"Du bist ausschlieYlich ein Experte f2r 2sterreichisches Gemeinn2tzigkeits- und Vereinsrecht. Du beantwortest nur Fragen zu diesem Thema."];
                // Style & grammar instruction for advisor stream
                $messages[] = ['role' => 'system', 'content' => 'Antworte ausschließlich auf Deutsch, in korrekter Rechtschreibung und Grammatik, ohne Begrüßungen, Floskeln oder Entschuldigungen. Formuliere präzise und klar.'];
                foreach ($history as $entry) {
                    $messages[] = [ 'role' => $entry['sender'] === 'user' ? 'user' : 'assistant', 'content' => $entry['message_content'] ];
                }
                $messages[] = ['role'=>'user','content'=>$message];
                // TODO: optional web_search could be integrated here similar to non-stream
                $model = get_option(Dual_Chatbot_Plugin::OPTION_ADVISOR_MODEL, 'gpt-4o');
                $ret = $this->call_chat_api_stream($messages, $model, $api_key, function($delta) use ($emit, &$full, $save_progress) {
                    if ($delta !== '') {
                        $full .= $delta;
                        $emit(['type'=>'delta','content'=>$delta]);
                        $save_progress();
                    }
                });
                if (is_string($ret) && strlen($ret) > strlen($full)) { $full = $ret; }
            }

            // Final save of the full content to the placeholder row
            $this->update_history_message($bot_row_id, $full);
            $saved = true;
            $emit(['type'=>'done']);
        } catch (Exception $e) {
            $emit(['type'=>'error','message'=>$e->getMessage()]);
            $emit(['type'=>'done']);
        }

        // End the request now to avoid REST wrapping
        exit;
    }

    /**
     * Append a message (user or bot) to the history. Intended as safety persist
     * after streaming on clients that could not complete server-side insert.
     */
    public function handle_append_history(WP_REST_Request $request): WP_REST_Response {
        $session_id = sanitize_text_field($request->get_param('session_id'));
        $message    = sanitize_textarea_field($request->get_param('message'));
        $sender     = sanitize_text_field($request->get_param('sender'));
        $context    = sanitize_text_field($request->get_param('context')) ?: 'advisor';
        $client_id  = sanitize_text_field($request->get_param('client_msg_id'));
        $reply_id   = sanitize_text_field($request->get_param('reply_to_client_msg_id'));
        if (empty($session_id) || empty($sender)) {
            return new WP_REST_Response(['error' => 'session_id und sender erforderlich'], 400);
        }
        $GLOBALS['dual_chatbot_current_context'] = $context;
        $user_id = get_current_user_id();
        global $wpdb; $history_table = $wpdb->prefix . 'chatbot_history';
        try {
            if ($sender === 'bot' && !empty($reply_id)) {
                $existing_row = $wpdb->get_row($wpdb->prepare("SELECT id, message_content FROM $history_table WHERE session_id=%s AND sender='bot' AND reply_to_client_msg_id=%s LIMIT 1", $session_id, $reply_id), ARRAY_A);
                $new_text = wp_strip_all_tags($message);
                if ($existing_row) {
                    $old_text = (string)($existing_row['message_content'] ?? '');
                    // Nur aktualisieren, wenn der neue Text länger ist (finaler Stand)
                    if (strlen($new_text) > strlen($old_text)) {
                        $this->update_history_message(intval($existing_row['id']), $new_text);
                    }
                } else {
                    $this->insert_chat_history($user_id, $session_id, 'bot', $new_text, $context, null, $reply_id);
                }
                return new WP_REST_Response(['ok'=>true, 'idempotent'=>true]);
            }
            if ($sender === 'user' && !empty($client_id)) {
                $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM $history_table WHERE session_id=%s AND sender='user' AND client_msg_id=%s LIMIT 1", $session_id, $client_id));
                if (!$existing) {
                    $this->insert_chat_history($user_id, $session_id, 'user', $message, $context, $client_id, null);
                }
                return new WP_REST_Response(['ok'=>true, 'idempotent'=>true]);
            }
            // Fallback insert without ids
            $this->insert_chat_history($user_id, $session_id, $sender, $message, $context, null, null);
            return new WP_REST_Response(['ok' => true]);
        } catch (Exception $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 500);
        }
    }

    public function handle_edit_message(WP_REST_Request $request): WP_REST_Response {
        global $wpdb; $history_table = $wpdb->prefix . 'chatbot_history';
        $id = intval($request->get_param('id'));
        $session_id = sanitize_text_field($request->get_param('session_id'));
        $content = sanitize_textarea_field($request->get_param('content'));
        if (!$id || empty($session_id) || $content==='') return new WP_REST_Response(['error'=>'Ungültige Parameter'], 400);
        $row = $wpdb->get_row($wpdb->prepare("SELECT id,sender,session_id FROM $history_table WHERE id=%d", $id));
        if (!$row || $row->session_id !== $session_id) return new WP_REST_Response(['error'=>'Nachricht nicht gefunden'], 404);
        if ($row->sender !== 'user') return new WP_REST_Response(['error'=>'Nur Nutzer-Nachrichten können editiert werden'], 403);
        $wpdb->update($history_table, [ 'message_content' => $content ], [ 'id' => $id ], [ '%s' ], [ '%d' ]);
        return new WP_REST_Response(['ok'=>true]);
    }

    // Edit any message (user or bot). Mirrors edit_message but without sender restriction.
    public function handle_edit_bot_message(WP_REST_Request $request): WP_REST_Response {
        global $wpdb; $history_table = $wpdb->prefix . 'chatbot_history';
        $id = intval($request->get_param('id'));
        $session_id = sanitize_text_field($request->get_param('session_id'));
        $content = sanitize_textarea_field($request->get_param('content'));
        if (!$id || empty($session_id) || $content==='') return new WP_REST_Response(['error'=>'Ungültige Parameter'], 400);
        $row = $wpdb->get_row($wpdb->prepare("SELECT id,session_id FROM $history_table WHERE id=%d", $id));
        if (!$row || $row->session_id !== $session_id) return new WP_REST_Response(['error'=>'Nachricht nicht gefunden'], 404);
        $wpdb->update($history_table, [ 'message_content' => $content ], [ 'id' => $id ], [ '%s' ], [ '%d' ]);
        return new WP_REST_Response(['ok'=>true]);
    }

    public function handle_delete_message(WP_REST_Request $request): WP_REST_Response {
        global $wpdb; $history_table = $wpdb->prefix . 'chatbot_history';
        $id = intval($request->get_param('id'));
        $session_id = sanitize_text_field($request->get_param('session_id'));
        if (!$id || empty($session_id)) return new WP_REST_Response(['error'=>'Ungültige Parameter'], 400);
        $row = $wpdb->get_row($wpdb->prepare("SELECT id,session_id FROM $history_table WHERE id=%d", $id));
        if (!$row || $row->session_id !== $session_id) return new WP_REST_Response(['error'=>'Nachricht nicht gefunden'], 404);
        $wpdb->delete($history_table, [ 'id' => $id ], [ '%d' ]);
        return new WP_REST_Response(['ok'=>true]);
    }

    public function handle_react_message(WP_REST_Request $request): WP_REST_Response {
        $id = intval($request->get_param('id'));
        $reaction = sanitize_text_field($request->get_param('reaction'));
        $feedback = sanitize_textarea_field($request->get_param('feedback'));
        if (!$id || !in_array($reaction, ['up','down'], true)) return new WP_REST_Response(['error'=>'Ungültige Parameter'], 400);
        dual_chatbot_log('reaction: id=' . $id . ' reaction=' . $reaction . ' feedback=' . ($feedback ?: ''));
        return new WP_REST_Response(['ok' => true]);
    }

    /**
     * Streaming call to OpenAI Chat API using cURL with stream=true.
     * Invokes $on_delta for every content delta chunk and returns the full text.
     */
    private function call_chat_api_stream(array $messages, string $model, string $api_key, callable $on_delta): string {
        $endpoint = 'https://api.openai.com/v1/chat/completions';
        $body = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => 0.7,
            'stream' => true,
        ];
        $ch = curl_init($endpoint);
        $full = '';
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $api_key,
            ],
            CURLOPT_POSTFIELDS => wp_json_encode($body),
            CURLOPT_WRITEFUNCTION => function($ch, $data) use ($on_delta, &$full) {
                static $buffer = '';
                $buffer .= $data;
                // Split by newlines and parse "data: {json}" lines
                while (($pos = strpos($buffer, "\n")) !== false) {
                    // Preserve leading spaces inside payloads; only strip trailing CR
                    $line = substr($buffer, 0, $pos);
                    $buffer = substr($buffer, $pos + 1);
                    if ($line !== '' && substr($line, -1) === "\r") { $line = substr($line, 0, -1); }
                    if ($line === '' || strncmp($line, 'data:', 5) !== 0) { continue; }
                    // Remove only leading spaces after 'data:' label, do not trim end
                    $payload = ltrim(substr($line, 5));
                    if ($payload === '[DONE]') { continue; }
                    $json = json_decode($payload, true);
                    if (!is_array($json)) { continue; }
                    $delta = $json['choices'][0]['delta']['content'] ?? '';
                    if ($delta !== '') {
                        $full .= $delta;
                        try { $on_delta($delta); } catch (\Throwable $e) {}
                    }
                }
                return strlen($data);
            },
            CURLOPT_TIMEOUT => 90,
            CURLOPT_RETURNTRANSFER => false, // we stream directly via callback
        ]);
        curl_exec($ch);
        curl_close($ch);
        return trim($full);
    }

    /**
     * Handle incoming chat messages.  Depending on the context (faq or
     * advisor) this function orchestrates the appropriate pipeline and
     * persists the conversation.
     */
    public function handle_submit_message(WP_REST_Request $request): WP_REST_Response {
        $message = sanitize_textarea_field($request->get_param('message'));
        $context = sanitize_text_field($request->get_param('context'));
        $session_id = sanitize_text_field($request->get_param('session_id'));
        if (empty($session_id)) {
            $session_id = wp_generate_uuid4();
        }
        $user_id = get_current_user_id();
        $response_text = '';
        $this->ensure_history_columns();
        $client_msg_id = sanitize_text_field($request->get_param('client_msg_id'));
        try {
            // Allow per‑message web search toggle.  Accept as boolean or string.
            $web_search = false;
            $web_param  = $request->get_param('web_search');
            if (!is_null($web_param)) {
                // cast to boolean
                $web_search = filter_var($web_param, FILTER_VALIDATE_BOOLEAN);
            }
            // Conditionally persist the user message (skip when regenerating)
            global $wpdb; $history_table = $wpdb->prefix . 'chatbot_history';
            $no_user_insert = filter_var($request->get_param('no_user_insert'), FILTER_VALIDATE_BOOLEAN);
            $exists_user = null;
            if (!empty($client_msg_id)) {
                $exists_user = $wpdb->get_var($wpdb->prepare("SELECT id FROM $history_table WHERE session_id=%s AND sender='user' AND client_msg_id=%s LIMIT 1", $session_id, $client_msg_id));
            }
            if (!$no_user_insert && !$exists_user) {
                $this->create_or_update_session($session_id, $user_id, $context, $message);
                $this->insert_chat_history($user_id, $session_id, 'user', $message, $context, $client_msg_id, null);
            }
            // Existing assistant for this message?
            $existing_bot = null;
            if (!empty($client_msg_id)) {
                $existing_bot = $wpdb->get_row($wpdb->prepare("SELECT message_content FROM $history_table WHERE session_id=%s AND sender='bot' AND reply_to_client_msg_id=%s LIMIT 1", $session_id, $client_msg_id), ARRAY_A);
            }
            if ($existing_bot && isset($existing_bot['message_content'])) {
                return new WP_REST_Response(['session_id'=>$session_id,'response'=>$existing_bot['message_content'],'existing_message'=>true]);
            }
            if ($context === 'faq') {
                $response_text = $this->process_faq_message($message);
            } elseif ($context === 'advisor') {
                $response_text = $this->process_advisor_message($message, $user_id, $session_id, $web_search);
            }
            $this->insert_chat_history($user_id, $session_id, 'bot', $response_text, $context, null, $client_msg_id);
            return new WP_REST_Response(['session_id'=>$session_id,'response'=>$response_text,'existing_message'=>false]);
        } catch (Exception $e) {
            return new WP_REST_Response(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Retrieves chat history for a given session.  Returns an array of
     * objects with sender and message_content keyed by timestamp ascending.
     */
    public function handle_get_history(WP_REST_Request $request): WP_REST_Response {
        $session_id = sanitize_text_field($request->get_param('session_id'));
        global $wpdb;
        $history_table = $wpdb->prefix . 'chatbot_history';
        $this->ensure_history_columns();
        $rows = $wpdb->get_results($wpdb->prepare("SELECT id, sender, message_content, `timestamp`, client_msg_id, reply_to_client_msg_id FROM $history_table WHERE session_id = %s ORDER BY id ASC", $session_id), ARRAY_A);
        return new WP_REST_Response(['history' => $rows]);
    }

    /**
     * Handles audio transcription.  Accepts an uploaded audio file and sends
     * it to the Whisper API.  Returns the transcribed text.  Files are not
     * persisted on disk.
     */
    public function handle_transcribe_audio(WP_REST_Request $request): WP_REST_Response {
        // Attempt to fetch uploaded files.  WordPress populates this from a
        // multipart/form-data request.  If nothing is present we also
        // support a base64 encoded string via the "audio_data" parameter.
        $files = $request->get_file_params();
        // If an audio file is not provided via $_FILES, try to decode from a data URI
        if (empty($files['audio']['tmp_name'])) {
            $audio_data = $request->get_param('audio_data');
            if (!empty($audio_data)) {
                // Strip any data URI prefix and decode
                $base64 = preg_replace('#^data:.*;base64,#', '', $audio_data);
                $decoded = base64_decode($base64);
                if ($decoded !== false) {
                    // Write decoded bytes to a temporary file.  Use webm as default.
                    $tmp_file = tempnam(get_temp_dir(), 'dual_chatbot_audio_');
                    file_put_contents($tmp_file, $decoded);
                    $files['audio'] = [
                        'tmp_name' => $tmp_file,
                        'name'     => 'recording.webm',
                        'type'     => 'audio/webm',
                    ];
                }
            }
            // After attempting to decode, still no file?  Return error.
            if (empty($files['audio']['tmp_name'])) {
                return new WP_REST_Response(['error' => __('Keine Audiodatei erhalten.', 'dual-chatbot')], 400);
            }
        }
        $file_path = $files['audio']['tmp_name'];
        $filename  = $files['audio']['name'];
        $mime_type = $files['audio']['type'] ?? 'application/octet-stream';
        $api_key   = get_option(Dual_Chatbot_Plugin::OPTION_WHISPER_API_KEY, '');
        if (empty($api_key)) {
            return new WP_REST_Response(['error' => __('Whisper API Key fehlt.', 'dual-chatbot')], 400);
        }
        $endpoint = 'https://api.openai.com/v1/audio/transcriptions';
        // Use native cURL to avoid issues with WordPress remote post filtering which
        // may strip the "model" parameter.  Build a multipart form request with
        // file and model fields.
        dual_chatbot_log('whisper request: ' . $filename);
        $curl = curl_init($endpoint);
        $fields = [
            'file'  => new CURLFile($file_path, $mime_type, $filename),
            'model' => 'whisper-1',
        ];
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $api_key,
            ],
            CURLOPT_POSTFIELDS     => $fields,
            CURLOPT_TIMEOUT        => 60,
        ]);
        $body_raw = curl_exec($curl);
        $status   = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $errNo    = curl_errno($curl);
        $errMsg   = curl_error($curl);
        curl_close($curl);
        dual_chatbot_log('whisper response (' . $status . '): ' . $body_raw);
        if ($errNo) {
            // cURL error
            dual_chatbot_log('whisper curl error: ' . $errMsg);
            return new WP_REST_Response(['error' => $errMsg], 500);
        }
        if ($status !== 200) {
            // Attempt to decode error message from API response for better debugging
            $json = json_decode($body_raw, true);
            $err  = '';
            if (is_array($json) && isset($json['error']['message'])) {
                $err = $json['error']['message'];
            }
            if (empty($err)) {
                $err = __('Fehler bei der Transkription.', 'dual-chatbot');
            }
            return new WP_REST_Response(['error' => $err], $status);
        }
        $data = json_decode($body_raw, true);
        return new WP_REST_Response(['transcription' => $data['text'] ?? '']);
    }

    /**
     * List distinct chat sessions for the current user.  Anonymous users will
     * receive an empty list since sessions cannot be tied to them securely.
     */
    public function handle_list_sessions(WP_REST_Request $request): WP_REST_Response {
        $user_id = get_current_user_id();
        if (!$user_id) {
            return new WP_REST_Response(['sessions' => []]);
        }
        global $wpdb;
        $history_table = $wpdb->prefix . 'chatbot_history';
        // Get first and last message ids per session for this user.
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT session_id, MIN(id) as first_id, MAX(`timestamp`) as last_ts FROM $history_table WHERE user_id = %d GROUP BY session_id ORDER BY last_ts DESC",
            $user_id
        ), ARRAY_A);
        $sessions = [];
        foreach ($rows as $row) {
            // Fetch first message content to derive title.
            $first_msg = $wpdb->get_row($wpdb->prepare(
                "SELECT message_content FROM $history_table WHERE id = %d",
                $row['first_id']
            ), ARRAY_A);
            $title = '';
            if ($first_msg && !empty($first_msg['message_content'])) {
                // Use first few words (max 6) from first user message as title.
                $words = preg_split('/\s+/', wp_strip_all_tags($first_msg['message_content']));
                $title = implode(' ', array_slice($words, 0, 6));
                if (strlen($title) > 50) {
                    $title = mb_substr($title, 0, 50);
                }
            }
            if (empty($title)) {
                $title = 'Chat vom ' . mysql2date(get_option('date_format') . ' H:i', $row['last_ts']);
            }
            $sessions[] = [
                'session_id' => $row['session_id'],
                'last_ts'    => $row['last_ts'],
                'title'      => $title,
            ];
        }
        return new WP_REST_Response(['sessions' => $sessions]);
    }

    /**
     * Endpoint to determine if the current user is a verified member.  This
     * respects the simulate membership option and uses the myablefy
     * verification for logged in users.  Anonymous users always return
     * member = false.
     */
    public function handle_check_membership(WP_REST_Request $request): WP_REST_Response {
        // If simulate membership is enabled globally, always return true.
        if (get_option(Dual_Chatbot_Plugin::OPTION_SIMULATE_MEMBERSHIP, '0') === '1') {
            return new WP_REST_Response(['member' => true]);
        }
        // Only logged in users can be considered for membership.
        $user_id = get_current_user_id();
        if (!$user_id) {
            return new WP_REST_Response(['member' => false]);
        }
        // Attempt to verify membership via myablefy.  Use a random session id
        // because membership does not depend on conversation context here.
        $session_id = wp_generate_uuid4();
        $verified = $this->verify_membership_via_myaplefy($session_id);
        return new WP_REST_Response(['member' => $verified]);
    }

    /**
     * Process a FAQ message using retrieval‑augmented generation.  Fetches
     * similar chunks from the knowledge base, then prompts OpenAI to answer
     * strictly based on the retrieved context.
     */
private function process_faq_message(string $message): string {
    $api_key = get_option(Dual_Chatbot_Plugin::OPTION_OPENAI_API_KEY, '');
    if (empty($api_key)) {
        return __('OpenAI API Key fehlt.', 'dual-chatbot');
    }
    // Convert user query to embedding.
    $query_embedding = $this->create_embedding($message);
    // Retrieve top 3 similar chunks.
    $chunks = $this->get_similar_chunks($query_embedding, 3);
    $context_text = '';
    foreach ($chunks as $chunk) {
        $context_text .= $chunk['chunk_text'] . "\n";
    }
    // SYSTEMPROMPT
    $prompt_messages = [
        ['role' => 'system', 'content' => "Du bist ausschließlich ein Experte für österreichisches Gemeinnützigkeits- und Vereinsrecht. Du beantwortest nur Fragen zu diesem Thema und verweigerst höflich jede Auskunft zu anderen juristischen, steuerlichen oder gesellschaftlichen Themen, insbesondere zu anderen Ländern. Wenn eine Frage nichts mit dem österreichischen Gemeinnützigkeits- oder Vereinsrecht zu tun hat, bitte erkläre freundlich, dass du dazu keine Auskunft geben darfst und ausschließlich auf dieses Spezialgebiet beschränkt bist."]
    ];
    // Kontext als zusätzliche assistant-Message (nur wenn vorhanden)
    // Style & grammar instruction to improve output quality
    $prompt_messages[] = ['role' => 'system', 'content' => 'Antworte ausschließlich auf Deutsch, in korrekter Rechtschreibung und Grammatik, ohne Begrüßungen, Floskeln oder Entschuldigungen. Formuliere präzise und klar.'];
    if (trim($context_text) !== '') {
        $prompt_messages[] = ['role' => 'assistant', 'content' => "Wissensauszüge:\n" . $context_text];
    }
    $prompt_messages[] = ['role' => 'user', 'content' => $message];
    // Modell überall auf GPT-4o stellen:
    $model = get_option(Dual_Chatbot_Plugin::OPTION_FAQ_MODEL, 'gpt-4o');
    $response = $this->call_chat_api($prompt_messages, $model, $api_key);
    return $response;
}

    /**
     * Process a message in advisor context.  Loads existing conversation
     * history, appends the new user message, and calls GPT-4 to produce
     * a contextual response.
     */
private function process_advisor_message(string $message, int $user_id, string $session_id, bool $webSearch = false): string {
    // Verify membership.  If not logged in we still need to check via myaplefy.
    if ($user_id === 0) {
        $verified = $this->verify_membership_via_myaplefy($session_id);
        if (!$verified) {
            return __('Dein Mitgliedsstatus konnte nicht bestätigt werden.', 'dual-chatbot');
        }
    }

    $api_key = get_option(Dual_Chatbot_Plugin::OPTION_OPENAI_API_KEY, '');
    if (empty($api_key)) {
        return __('OpenAI API Key fehlt.', 'dual-chatbot');
    }
        // Load conversation history.
        $history = $this->load_conversation_history($user_id, $session_id);
        $messages = [];
        // Prepend a system message to instruct the model.  We additionally instruct
        // the assistant to rely on vorhandenes Wissen (existing knowledge) when
        // web search results are unavailable and to avoid mentioning that
        // internet search cannot be performed.
       $messages[] = [
    'role' => 'system',
    'content' => "Du bist ausschließlich ein Experte für österreichisches Gemeinnützigkeits- und Vereinsrecht. Du beantwortest nur Fragen zu diesem Thema und verweigerst höflich jede Auskunft zu anderen juristischen, steuerlichen oder gesellschaftlichen Themen, insbesondere zu anderen Ländern. Wenn eine Frage nichts mit dem österreichischen Gemeinnützigkeits- oder Vereinsrecht zu tun hat, bitte erkläre freundlich, dass du dazu keine Auskunft geben darfst und ausschließlich auf dieses Spezialgebiet beschränkt bist."
];
        // Style & grammar instruction for advisor responses
        $messages[] = ['role' => 'system', 'content' => 'Antworte ausschließlich auf Deutsch, in korrekter Rechtschreibung und Grammatik, ohne Begrüßungen, Floskeln oder Entschuldigungen. Formuliere präzise und klar.'];
        foreach ($history as $entry) {
            $messages[] = [
                'role'    => $entry['sender'] === 'user' ? 'user' : 'assistant',
                'content' => $entry['message_content'],
            ];
        }
        // Append current user message.
        $messages[] = ['role' => 'user', 'content' => $message];
        // Determine whether web search should be performed, either globally or on a per‑message basis.
        $search_enabled_global = get_option(Dual_Chatbot_Plugin::OPTION_WEB_SEARCH_ENABLED, '0') === '1';
        $doSearch = $webSearch || $search_enabled_global;
        $num_results = intval(get_option(Dual_Chatbot_Plugin::OPTION_WEB_SEARCH_RESULTS, 3));
        if ($num_results < 1) {
            $num_results = 1;
        }
        $results = [];
        $footnote_lines = [];
        $ref_tags = [];
        // If search is enabled, perform it before calling the language model so the results can
        // be incorporated into the prompt.  Any errors are silently logged and ignored.
        if ($doSearch) {
dual_chatbot_log('Triggering web search for: ' . $message . ' (user_id: ' . $user_id . ', session_id: ' . $session_id . ')');
            try {
                $results = $this->perform_web_search($message, $num_results);
                foreach ($results as $idx => $item) {
                    $refNum = $idx + 1;
                    $ref_tags[] = '[' . $refNum . ']';
                    $footnote_lines[] = '[' . $refNum . '] ' . $item['title'] . ' (' . $item['domain'] . ') - ' . $item['snippet'];
                }
                // Build a context string summarising the search results (if any).  The model will
                // reference these by index ([1], [2], etc.) when formulating its answer.  If no
                // results were returned, we still provide guidance to use existing knowledge.
                if (!empty($results)) {
                    $context_text = "Nutze die folgenden Websuchergebnisse, um die Frage bestmöglich zu beantworten. Jeder Eintrag ist mit einer Nummer gekennzeichnet. Erwähne diese Nummern als Quellenangaben in deiner Antwort, falls du Informationen daraus verwendest:\n";
                    foreach ($results as $idx => $item) {
                        $refNum = $idx + 1;
                        $context_text .= '[' . $refNum . '] ' . $item['title'] . ' (' . $item['domain'] . '): ' . $item['snippet'] . "\n";
                    }
                } else {
                    // No search results were available.  Instruct the model to rely on its
                    // existing training data and not to apologise for being unable to search.
                    $context_text = "Es konnten keine relevanten Websuchergebnisse gefunden werden. Beantworte die Frage daher anhand deines vorhandenen Wissens, ohne darauf hinzuweisen, dass du nicht im Internet suchen kannst.";
                }
                // Insert the context before the last message (the current user prompt) so
                // that the model processes search results prior to the user's question.
                $contextMessage = [ 'role' => 'system', 'content' => $context_text ];
                // $messages currently holds: initial system, history..., user message (last element)
                // Insert the context message right before the last entry (the user message).
                array_splice($messages, -1, 0, [ $contextMessage ]);
            } catch (Exception $ex) {
                dual_chatbot_log('web search error: ' . $ex->getMessage());
                // If an exception occurs we provide a fallback context instructing the
                // model to answer from its knowledge.
                $fallbackMessage = [ 'role' => 'system', 'content' => "Es gab ein Problem bei der Websuche. Bitte beantworte die Frage anhand deines vorhandenen Wissens, ohne zu erwähnen, dass eine Internetrecherche nicht möglich ist." ];
                array_splice($messages, -1, 0, [ $fallbackMessage ]);
            }
        }
        // Call GPT‑4 (or configured model) with the assembled messages.  If search results were
        // collected they are included as an additional system message above.
        $model = get_option(Dual_Chatbot_Plugin::OPTION_ADVISOR_MODEL, 'gpt-4o');
        $response = $this->call_chat_api($messages, $model, $api_key);
        // After obtaining the response from GPT, append footnote references and
        // descriptions if web search was performed.
        if (!empty($results)) {
            $response .= "\n\n" . implode(' ', $ref_tags) . "\n" . implode("\n", $footnote_lines);
        }
        return $response;
    }

    /**
     * Perform a web search using DuckDuckGo's instant answer API.  Returns an array of
     * associative arrays with keys: title, url, domain, snippet.  Limited to the
     * specified number of results.  Only invoked for advisor context when web search is enabled.
     *
     * @param string $query
     * @param int    $limit
     * @return array
     */
    private function perform_web_search(string $query, int $limit = 3): array {
        $limit = max(1, $limit);
        // Determine which search endpoint to use.  Site administrators may
        // specify a custom API endpoint and API key via the plugin settings.
        // If no endpoint is provided the plugin falls back to DuckDuckGo.
        $endpoint = trim(get_option(Dual_Chatbot_Plugin::OPTION_WEB_SEARCH_API_ENDPOINT, ''));
        $api_key  = trim(get_option(Dual_Chatbot_Plugin::OPTION_WEB_SEARCH_API_KEY, ''));
        if (!empty($endpoint)) {
            // Replace placeholders in the endpoint with the encoded query and API key.
            $url = str_replace(
                ['{query}', '{api_key}'],
                [urlencode($query), urlencode($api_key)],
                $endpoint
            );
            // If the endpoint did not specify a query placeholder, append q parameter.
            if (strpos($endpoint, '{query}') === false) {
                $url .= (str_contains($url, '?') ? '&' : '?') . 'q=' . urlencode($query);
            }
            // If an API key exists and the endpoint does not contain a placeholder, append it.
            if (!empty($api_key) && strpos($endpoint, '{api_key}') === false) {
                $url .= '&api_key=' . urlencode($api_key);
            }
        } else {
            // Use DuckDuckGo if no custom endpoint is provided.  DuckDuckGo does not require an API key.
            $url = 'https://api.duckduckgo.com/?format=json&no_html=1&no_redirect=1&q=' . urlencode($query);
        }
        $args = [ 'timeout' => 15 ];
        $response = wp_remote_get($url, $args);
        if (is_wp_error($response)) {
            throw new Exception($response->get_error_message());
        }
        $body_raw = wp_remote_retrieve_body($response);
        $json = json_decode($body_raw, true);
        if (!is_array($json)) {
            throw new Exception('Unerwartete Antwort von der Such-API.');
        }
        $results = [];
        // DuckDuckGo returns results in RelatedTopics array.  Each item may contain Text and FirstURL.
        $topics = $json['RelatedTopics'] ?? [];
        foreach ($topics as $topic) {
            if (isset($topic['Text']) && isset($topic['FirstURL'])) {
                $title = $topic['Text'];
                $urlItem = $topic['FirstURL'];
                $domain = parse_url($urlItem, PHP_URL_HOST) ?: '';
                $results[] = [
                    'title' => $title,
                    'url'   => $urlItem,
                    'domain'=> $domain,
                    'snippet' => $title,
                ];
            }
            // Some entries nest topics deeper; handle them recursively.
            if (isset($topic['Topics']) && is_array($topic['Topics'])) {
                foreach ($topic['Topics'] as $sub) {
                    if (isset($sub['Text']) && isset($sub['FirstURL'])) {
                        $title = $sub['Text'];
                        $urlItem = $sub['FirstURL'];
                        $domain = parse_url($urlItem, PHP_URL_HOST) ?: '';
                        $results[] = [
                            'title' => $title,
                            'url'   => $urlItem,
                            'domain'=> $domain,
                            'snippet' => $title,
                        ];
                    }
                }
            }
            if (count($results) >= $limit) {
                break;
            }
        }
        return array_slice($results, 0, $limit);
    }

    /**
     * Insert a single chat message into the history table.  Accepts null
     * user_id for anonymous sessions.
     */
    private function insert_chat_history(?int $user_id, string $session_id, string $sender, string $message, string $context = null, ?string $client_msg_id = null, ?string $reply_to_client_msg_id = null): void {
        global $wpdb;
        $history_table = $wpdb->prefix . 'chatbot_history';
        $data = [
            'user_id'         => $user_id ?: null,
            'session_id'      => $session_id,
            'sender'          => $sender,
            'message_content' => wp_strip_all_tags($message),
            'timestamp'       => current_time('mysql', 1),
            'context'         => $context ?? ($GLOBALS['dual_chatbot_current_context'] ?? 'faq'),
        ];
        if (!is_null($client_msg_id)) $data['client_msg_id'] = $client_msg_id;
        if (!is_null($reply_to_client_msg_id)) $data['reply_to_client_msg_id'] = $reply_to_client_msg_id;
        $wpdb->insert($history_table, $data);
    }

    /**
     * Insert an empty bot message and return its row ID so we can update as we stream.
     */
    private function insert_bot_placeholder(?int $user_id, string $session_id, string $context, ?string $reply_to_client_msg_id = null): int {
        global $wpdb;
        $history_table = $wpdb->prefix . 'chatbot_history';
        $wpdb->insert($history_table, [
            'user_id'         => $user_id ?: null,
            'session_id'      => $session_id,
            'sender'          => 'bot',
            'message_content' => '',
            'timestamp'       => current_time('mysql', 1),
            'context'         => $context,
            'reply_to_client_msg_id' => $reply_to_client_msg_id,
        ], [ '%d','%s','%s','%s','%s','%s' ]);
        return intval($wpdb->insert_id);
    }

    /**
     * Update an existing history row's message content (used to append streamed text).
     */
    private function update_history_message(int $row_id, string $text): void {
        global $wpdb;
        $history_table = $wpdb->prefix . 'chatbot_history';
        $wpdb->update($history_table, [
            'message_content' => wp_strip_all_tags($text),
            'timestamp'       => current_time('mysql', 1),
        ], [ 'id' => $row_id ], [ '%s','%s' ], [ '%d' ]);
    }

    /**
     * Create or update a session record.  If a session does not exist, insert it
     * with the provided context and an initial title derived from the first user
     * message.  If it exists and no title has been set (null), update title
     * using first few words of the message.
     */
    private function create_or_update_session(string $session_id, ?int $user_id, string $context, string $message): void {
        global $wpdb;
        $sessions_table = $wpdb->prefix . 'chatbot_sessions';
        // Derive a title from the first few words of the message (max 6 words, 50 chars).
        $title = '';
        $words = preg_split('/\s+/', wp_strip_all_tags($message));
        if ($words && is_array($words)) {
            $title = implode(' ', array_slice($words, 0, 6));
            if (strlen($title) > 50) {
                $title = mb_substr($title, 0, 50);
            }
        }
        // Check if session exists.
        $existing = $wpdb->get_row($wpdb->prepare("SELECT session_id, title FROM $sessions_table WHERE session_id = %s", $session_id));
        if ($existing) {
            // If no title yet and derived title exists, update it.
            if (empty($existing->title) && !empty($title)) {
                $wpdb->update($sessions_table, [ 'title' => $title, 'updated_at' => current_time('mysql', 1) ], [ 'session_id' => $session_id ], [ '%s', '%s' ], [ '%s' ]);
            } else {
                // Just update updated_at timestamp
                $wpdb->update($sessions_table, [ 'updated_at' => current_time('mysql', 1) ], [ 'session_id' => $session_id ], [ '%s' ], [ '%s' ]);
            }
        } else {
            // Insert new session record
            $wpdb->insert($sessions_table, [
                'session_id' => $session_id,
                'user_id'    => $user_id ?: null,
                'context'    => $context,
                'title'      => !empty($title) ? $title : null,
                'created_at' => current_time('mysql', 1),
                'updated_at' => current_time('mysql', 1),
            ], [ '%s', '%d', '%s', '%s', '%s', '%s' ]);
        }
        // Set global context for insert_chat_history to use.
        $GLOBALS['dual_chatbot_current_context'] = $context;
    }

    /**
     * Rename a session.  Only allows renaming sessions owned by the current user.
     */
    public function handle_rename_session(WP_REST_Request $request): WP_REST_Response {
        $session_id = sanitize_text_field($request->get_param('session_id'));
        $title      = sanitize_text_field($request->get_param('title'));
        $context    = sanitize_text_field($request->get_param('context'));
        $user_id = get_current_user_id();
        if (!$user_id) {
            return new WP_REST_Response(['error' => __('Nicht autorisiert.', 'dual-chatbot')], 403);
        }
        global $wpdb;
        $sessions_table = $wpdb->prefix . 'chatbot_sessions';
        // Ensure the session belongs to this user and context (if provided)
        $where = [ 'session_id' => $session_id, 'user_id' => $user_id ];
        $formats_where = [ '%s', '%d' ];
        if (!empty($context)) {
            $where['context'] = $context;
            $formats_where[] = '%s';
        }
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $sessions_table WHERE session_id = %s AND user_id = %d" . (!empty($context) ? " AND context = %s" : ''),
            !empty($context) ? [ $session_id, $user_id, $context ] : [ $session_id, $user_id ]
        ));
        if (!$exists) {
            return new WP_REST_Response(['error' => __('Session nicht gefunden.', 'dual-chatbot')], 404);
        }
        $wpdb->update($sessions_table, [ 'title' => $title, 'updated_at' => current_time('mysql', 1) ], $where);
        return new WP_REST_Response(['success' => true]);
    }

    /**
     * Delete a session and all associated messages.  Only allows deletion for
     * sessions owned by the current user.
     */
    public function handle_delete_session(WP_REST_Request $request): WP_REST_Response {
        $session_id = sanitize_text_field($request->get_param('session_id'));
        $context    = sanitize_text_field($request->get_param('context'));
        $user_id = get_current_user_id();
        if (!$user_id) {
            return new WP_REST_Response(['error' => __('Nicht autorisiert.', 'dual-chatbot')], 403);
        }
        global $wpdb;
        $sessions_table = $wpdb->prefix . 'chatbot_sessions';
        $history_table  = $wpdb->prefix . 'chatbot_history';
        // Verify ownership
        $owns = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $sessions_table WHERE session_id = %s AND user_id = %d" . (!empty($context) ? " AND context = %s" : ''),
            !empty($context) ? [ $session_id, $user_id, $context ] : [ $session_id, $user_id ]
        ));
        if (!$owns) {
            return new WP_REST_Response(['error' => __('Session nicht gefunden oder keine Berechtigung.', 'dual-chatbot')], 404);
        }
        // Delete from sessions and history
        $wpdb->delete($sessions_table, [ 'session_id' => $session_id, 'user_id' => $user_id ], [ '%s', '%d' ]);
        $wpdb->delete($history_table, [ 'session_id' => $session_id ], [ '%s' ]);
        return new WP_REST_Response(['success' => true]);
    }

    /**
     * Search sessions for the current user by title or message content.  Returns an
     * array of matching sessions with session_id, last_ts and title.  You can
     * filter by context to search only FAQ or advisor sessions.
     */
    public function handle_search_sessions(WP_REST_Request $request): WP_REST_Response {
        $query   = sanitize_text_field($request->get_param('query'));
        $context = sanitize_text_field($request->get_param('context'));
        $user_id = get_current_user_id();
        if (!$user_id) {
            return new WP_REST_Response(['sessions' => []]);
        }
        global $wpdb;
        $sessions_table = $wpdb->prefix . 'chatbot_sessions';
        $history_table  = $wpdb->prefix . 'chatbot_history';
        $query_like = '%' . $wpdb->esc_like($query) . '%';
        // Search sessions by title and optionally context
        $sql = "SELECT s.session_id, s.title, MAX(h.timestamp) AS last_ts
                FROM $sessions_table s
                LEFT JOIN $history_table h ON h.session_id = s.session_id
                WHERE s.user_id = %d AND (s.title LIKE %s OR h.message_content LIKE %s)";
        $params = [ $user_id, $query_like, $query_like ];
        if (!empty($context)) {
            $sql .= " AND s.context = %s";
            $params[] = $context;
        }
        $sql .= " GROUP BY s.session_id ORDER BY last_ts DESC";
        $rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
        $results = [];
        foreach ($rows as $row) {
            $title = $row['title'];
            if (empty($title)) {
                $title = 'Chat vom ' . mysql2date(get_option('date_format') . ' H:i', $row['last_ts']);
            }
            $results[] = [
                'session_id' => $row['session_id'],
                'last_ts'    => $row['last_ts'],
                'title'      => $title,
            ];
        }
        return new WP_REST_Response(['sessions' => $results]);
    }

    /**
     * Load entire conversation history from the database for a given user
     * and session.  Returns an array of associative arrays.
     */
    private function load_conversation_history(?int $user_id, string $session_id): array {
        global $wpdb;
        $history_table = $wpdb->prefix . 'chatbot_history';
        // If user_id is zero (anonymous), filter only by session_id.
        if (!$user_id) {
            $rows = $wpdb->get_results($wpdb->prepare("SELECT sender, message_content FROM $history_table WHERE session_id = %s ORDER BY id ASC", $session_id), ARRAY_A);
        } else {
            $rows = $wpdb->get_results($wpdb->prepare("SELECT sender, message_content FROM $history_table WHERE session_id = %s AND (user_id = %d OR user_id IS NULL) ORDER BY id ASC", $session_id, $user_id), ARRAY_A);
        }
        return $rows ?: [];
    }

    /**
     * Calls the OpenAI Chat API with the provided messages.  Selects the
     * specified model and returns the assistant's reply as plain text.  If
     * an error occurs, throws an exception.
     */
    private function call_chat_api(array $messages, string $model, string $api_key): string {
        $endpoint = 'https://api.openai.com/v1/chat/completions';
        $body = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => 0.7,
        ];
        $args = [
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ],
            'body' => wp_json_encode($body),
            'timeout' => 60,
        ];
        dual_chatbot_log('chat API request (' . $model . '): ' . wp_json_encode($body));
        $response = wp_remote_post($endpoint, $args);
        if (is_wp_error($response)) {
            dual_chatbot_log('chat API error: ' . $response->get_error_message());
            throw new Exception($response->get_error_message());
        }
        $status = wp_remote_retrieve_response_code($response);
        $body_raw = wp_remote_retrieve_body($response);
        dual_chatbot_log('chat API response (' . $status . '): ' . $body_raw);
        $json = json_decode($body_raw, true);
        if ($status !== 200 || !isset($json['choices'][0]['message']['content'])) {
            $err = $json['error']['message'] ?? __('Unbekannter API-Fehler', 'dual-chatbot');
            throw new Exception($err);
        }
        return trim($json['choices'][0]['message']['content']);
    }

    /**
     * Converts arbitrary text into an embedding by calling the OpenAI
     * embeddings API.  Returns an array of floats representing the vector.
     */
    private function create_embedding(string $text): array {
        $api_key = get_option(Dual_Chatbot_Plugin::OPTION_OPENAI_API_KEY, '');
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
        dual_chatbot_log('embedding request: ' . wp_json_encode($body));
        $response = wp_remote_post($endpoint, $args);
        if (is_wp_error($response)) {
            dual_chatbot_log('embedding error: ' . $response->get_error_message());
            throw new Exception($response->get_error_message());
        }
        $status = wp_remote_retrieve_response_code($response);
        $body_raw = wp_remote_retrieve_body($response);
        dual_chatbot_log('embedding response (' . $status . '): ' . $body_raw);
        $json = json_decode($body_raw, true);
        if ($status !== 200 || empty($json['data'][0]['embedding'])) {
            $err = $json['error']['message'] ?? __('Fehler bei der Erstellung des Embeddings.', 'dual-chatbot');
            throw new Exception($err);
        }
        return $json['data'][0]['embedding'];
    }

    /**
     * Retrieves the most similar knowledge chunks based on cosine similarity
     * between the given embedding and stored embeddings.  Returns an array
     * containing associative arrays with keys chunk_text and similarity.
     */
    private function get_similar_chunks(array $query_embedding, int $limit = 3): array {
        global $wpdb;
        $knowledge_table = $wpdb->prefix . 'chatbot_knowledge';
        // Fetch all embeddings from DB.  In a real implementation you might
        // perform vector search in a dedicated vector database or use
        // approximated search.  For simplicity we load and compute in PHP.
        $rows = $wpdb->get_results("SELECT id, chunk_text, embedding FROM $knowledge_table", ARRAY_A);
        $scores = [];
        foreach ($rows as $row) {
            $embedding = json_decode($row['embedding'], true);
            if (empty($embedding) || !is_array($embedding)) {
                continue;
            }
            $sim = $this->cosine_similarity($query_embedding, $embedding);
            $scores[] = [
                'chunk_text' => $row['chunk_text'],
                'similarity' => $sim,
            ];
        }
        usort($scores, function($a, $b) {
            return $a['similarity'] < $b['similarity'] ? 1 : -1;
        });
        return array_slice($scores, 0, $limit);
    }

    /**
     * Compute cosine similarity between two vectors.  Handles vectors of
     * differing lengths by aligning overlapping indices.
     */
    private function cosine_similarity(array $vec1, array $vec2): float {
        $dot = 0.0;
        $norm1 = 0.0;
        $norm2 = 0.0;
        $len = min(count($vec1), count($vec2));
        for ($i = 0; $i < $len; $i++) {
            $dot += $vec1[$i] * $vec2[$i];
            $norm1 += $vec1[$i] * $vec1[$i];
            $norm2 += $vec2[$i] * $vec2[$i];
        }
        if ($norm1 === 0 || $norm2 === 0) {
            return 0.0;
        }
        return $dot / (sqrt($norm1) * sqrt($norm2));
    }

    /**
     * Verify membership via myaplefy API.  Uses stored API key and endpoint.
     * Returns true if the API confirms membership, false otherwise.
     */
    private function verify_membership_via_myaplefy(string $session_id): bool {
        // Return true immediately if simulation is enabled. Do not auto-approve administrators
        // when simulation is disabled; membership should be verified via myablefy for all users.
        if (get_option(Dual_Chatbot_Plugin::OPTION_SIMULATE_MEMBERSHIP, '0') === '1') {
            return true;
        }
        $api_key = get_option(Dual_Chatbot_Plugin::OPTION_MYAPLEFY_API_KEY, '');
        $endpoint = get_option(Dual_Chatbot_Plugin::OPTION_MYAPLEFY_ENDPOINT, '');
        if (empty($api_key) || empty($endpoint)) {
            return false;
        }
        $body = [
            'session_id' => $session_id,
        ];
        $args = [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ],
            'body' => wp_json_encode($body),
            'timeout' => 20,
        ];
        dual_chatbot_log('myablefy request: ' . wp_json_encode($body));
        $response = wp_remote_post($endpoint, $args);
        if (is_wp_error($response)) {
            dual_chatbot_log('myablefy error: ' . $response->get_error_message());
            return false;
        }
        $status = wp_remote_retrieve_response_code($response);
        $data = wp_remote_retrieve_body($response);
        dual_chatbot_log('myablefy response (' . $status . '): ' . $data);
        if ($status !== 200) {
            return false;
        }
        $json = json_decode($data, true);
        return $json['member'] ?? false;
    }
}
// Plugin initialisieren (ganz am Ende der Datei):
Dual_Chatbot_Rest_API::get_instance();
