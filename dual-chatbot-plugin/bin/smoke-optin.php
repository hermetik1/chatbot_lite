<?php
// Dev-only smoke test for analytics opt-in gate.
// Usage: php bin/smoke-optin.php (run from plugin root)

define('SHORTINIT', false);
$wp = dirname(__DIR__, 3) . '/wp-load.php';
if (!file_exists($wp)) {
    // Fallback: if plugin is in a different depth
    $wp = dirname(__DIR__) . '/../../wp-load.php';
}
require $wp;

global $wpdb;

if (!function_exists('dcb_table_analytics')) {
    require_once dirname(__DIR__) . '/includes/db.php';
}

$table = dcb_table_analytics();

// Provide a dev action to emit a tiny analytics row (CLI-only)
if (!has_action('dual_chatbot_test_emit_analytics')) {
    add_action('dual_chatbot_test_emit_analytics', function () use ($table) {
        if (PHP_SAPI !== 'cli' && !defined('WP_CLI')) { return; }
        if ((int) get_option('dcb_analytics_opt_in', 0) !== 1) { return; }
        global $wpdb;
        $wpdb->insert($table, [
            'ts'         => current_time('mysql'),
            'session_id' => 'smoke',
            'user_id'    => null,
            'client_id'  => '',
            'type'       => 'perf_server',
            'latency_ms' => rand(1, 999),
            'kb_hit'     => null,
        ]);
    });
}

update_option('dcb_analytics_opt_in', 0);
$before = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
do_action('dual_chatbot_test_emit_analytics');
$after = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
echo "OFF: {$before} -> {$after}\n";

update_option('dcb_analytics_opt_in', 1);
do_action('dual_chatbot_test_emit_analytics');
$after2 = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
echo "ON: {$after} -> {$after2}\n";

