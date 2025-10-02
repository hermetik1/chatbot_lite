<?php
if (!defined('ABSPATH')) { exit; }

class DCB_Log {
    public static function event(string $type, array $data = []): void {
        $enabled = (bool) apply_filters('dual_chatbot_analytics_enabled', true);
        if (!$enabled && !WP_DEBUG_LOG) { return; }
        $scrub = static function($v) {
            if (is_string($v)) {
                if (stripos($v, 'sk-') === 0) return '[redacted]';
                if (strlen($v) > 128) return '[redacted]';
            }
            return $v;
        };
        array_walk_recursive($data, function (&$v) use ($scrub) { $v = $scrub($v); });
        $payload = wp_json_encode(['ts' => gmdate('c'), 'type' => $type, 'data' => $data]);
        if (WP_DEBUG_LOG) { @error_log('[dual-chatbot] ' . $payload); }
    }
}

