<?php
if (!defined('ABSPATH')) { exit; }

require_once __DIR__ . '/db.php';

add_action('dcb_retention_cleanup_daily', function (): void {
    global $wpdb;
    $days = max(7, (int) get_option('dcb_retention_days', 180));
    $cutoff = gmdate('Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS);

    $tableH = dcb_table_history();
    $tableA = dcb_table_analytics();

    // Start transaction to keep tables consistent on failure
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching -- administrative maintenance, no caching desired
    $wpdb->query('START TRANSACTION');
    $ok1 = 0;
    $ok2 = 0;
    if (!empty($tableH)) {
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name is trusted (prefix-based constant)
        $ok1 = $wpdb->query($wpdb->prepare("DELETE FROM {$tableH} WHERE `created_at` < %s", $cutoff));
    }
    if (!empty($tableA)) {
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- table name is trusted (prefix-based constant)
        $ok2 = $wpdb->query($wpdb->prepare("DELETE FROM {$tableA} WHERE `created_at` < %s", $cutoff));
    }

    if ($ok1 !== false && $ok2 !== false) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query('COMMIT');
    } else {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query('ROLLBACK');
        if (!empty($wpdb->last_error)) {
            error_log('[dual-chatbot] retention failed: ' . $wpdb->last_error);
        }
    }
});

function dcb_maybe_schedule_cleanup(): void {
    if (!wp_next_scheduled('dcb_retention_cleanup_daily')) {
        wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'dcb_retention_cleanup_daily');
    }
}
add_action('admin_init', 'dcb_maybe_schedule_cleanup');
