<?php
if (!defined('ABSPATH')) { exit; }

require_once dirname(__DIR__) . '/db.php';

add_action('admin_init', function (): void {
    // Settings (use existing plugin group so values save via the main form)
    register_setting('dual_chatbot_options', 'dcb_analytics_opt_in', [
        'type'              => 'boolean',
        'sanitize_callback' => 'absint',
        'default'           => 0,
        'show_in_rest'      => false,
    ]);
    register_setting('dual_chatbot_options', 'dcb_retention_days', [
        'type'              => 'integer',
        'sanitize_callback' => function($v){ $v=(int)$v; return max(7, min(730, $v ?: 180)); },
        'default'           => 180,
        'show_in_rest'      => false,
    ]);

    // Safer cleanup mode: delete in small batches instead of TRUNCATE
    register_setting('dual_chatbot_options', 'dcb_safe_cleanup', [
        'type'              => 'boolean',
        'sanitize_callback' => 'absint',
        'default'           => 0,
        'show_in_rest'      => false,
    ]);

    // Force German fallback labels (only if .mo missing)
    register_setting('dual_chatbot_options', 'dcb_force_de_labels', [
        'type'              => 'boolean',
        'sanitize_callback' => 'absint',
        'default'           => 0,
        'show_in_rest'      => false,
    ]);

    // Whether to purge all plugin data on uninstall
    register_setting('dual_chatbot_options', 'dcb_uninstall_purge', [
        'type'              => 'boolean',
        'sanitize_callback' => 'absint',
        'default'           => 0,
        'show_in_rest'      => false,
    ]);

    add_settings_section(
        'dcb_privacy_section',
        '',
        function(){
            echo '<h2>' . esc_html__('Data & Privacy', 'dual-chatbot') . '</h2>';
            echo '<p>' . esc_html__('Control analytics, data retention and user data requests (GDPR).', 'dual-chatbot') . '</p>';
            echo '<p><small>' . esc_html__('We store chat metadata and messages for operation and quality. Analytics is optional (opt-in). You can export or delete a user’s data at any time.', 'dual-chatbot') . '</small></p>';
        },
        'dual-chatbot-settings-privacy'
    );

    add_settings_field(
        'dcb_analytics_opt_in',
        '',
        function(){
            $val = (int) get_option('dcb_analytics_opt_in', 0);
            echo '<p><strong>' . esc_html__('Enable usage analytics (opt-in)', 'dual-chatbot') . '</strong></p>';
            echo '<label><input type="hidden" name="dcb_analytics_opt_in" value="0" />';
            echo '<input type="checkbox" name="dcb_analytics_opt_in" value="1" ' . checked(1, $val, false) . ' /> ';
            echo esc_html__('Allow anonymized usage analytics (costs, tokens, latency).', 'dual-chatbot') . '</label>';
        },
        'dual-chatbot-settings-privacy',
        'dcb_privacy_section'
    );

    add_settings_field(
        'dcb_retention_days',
        '',
        function(){
            $val = (int) get_option('dcb_retention_days', 180);
            echo '<label for="dcb_retention_days">' . esc_html__('Retention (days)', 'dual-chatbot') . '</label><br />';
            echo '<input type="number" min="7" max="730" step="1" name="dcb_retention_days" value="' . esc_attr($val) . '" />';
            echo '<p class="description">' . esc_html__('Older records will be purged automatically by a daily cleanup job.', 'dual-chatbot') . '</p>';
        },
        'dual-chatbot-settings-privacy',
        'dcb_privacy_section'
    );

    // Safer cleanup checkbox (affects cache/history truncation handlers)
    add_settings_field(
        'dcb_safe_cleanup',
        '',
        function(){
            $val = (int) get_option('dcb_safe_cleanup', 0);
            echo '<p><strong>' . esc_html__('Sichere Bereinigung verwenden', 'dual-chatbot') . '</strong></p>';
            echo '<label><input type="hidden" name="dcb_safe_cleanup" value="0" />';
            echo '<input type="checkbox" name="dcb_safe_cleanup" value="1" ' . checked(1, $val, false) . ' /> ';
            echo esc_html__('Statt TRUNCATE in Batches löschen (500 pro Schritt, max. 5 Sekunden).', 'dual-chatbot') . '</label>';
        },
        'dual-chatbot-settings-privacy',
        'dcb_privacy_section'
    );

    // Force German fallback labels checkbox
    add_settings_field(
        'dcb_force_de_labels',
        '',
        function(){
            $val = (int) get_option('dcb_force_de_labels', 0);
            echo '<p><strong>' . esc_html__('Deutsche Fallback-Übersetzungen erzwingen', 'dual-chatbot') . '</strong></p>';
            echo '<label><input type="hidden" name="dcb_force_de_labels" value="0" />';
            echo '<input type="checkbox" name="dcb_force_de_labels" value="1" ' . checked(1, $val, false) . ' /> ';
            echo esc_html__('Aktiviert interne Gettext-Filter, falls keine .mo-Dateien vorhanden sind. Standard: aus.', 'dual-chatbot') . '</label>';
        },
        'dual-chatbot-settings-privacy',
        'dcb_privacy_section'
    );

    // Uninstall purge checkbox
    add_settings_field(
        'dcb_uninstall_purge',
        '',
        function(){
            $val = (int) get_option('dcb_uninstall_purge', 0);
            echo '<p><strong>' . esc_html__('Beim Deinstallieren Daten entfernen', 'dual-chatbot') . '</strong></p>';
            echo '<label><input type="hidden" name="dcb_uninstall_purge" value="0" />';
            echo '<input type="checkbox" name="dcb_uninstall_purge" value="1" ' . checked(1, $val, false) . ' /> ';
            echo esc_html__('Alle Plugin-Daten (Tabellen, Einstellungen, Wissensdatenbank) beim Deinstallieren unwiderruflich loeschen.', 'dual-chatbot') . '</label>';
        },
        'dual-chatbot-settings-privacy',
        'dcb_privacy_section'
    );

    add_settings_field(
        'dcb_dsar_tools',
        '',
        function(){
            $nonce = wp_create_nonce('dcb_privacy_nonce');
            $base  = esc_url(admin_url('admin-post.php'));
            echo '<div class="dcb-dsar-box">';
            echo '<h3>' . esc_html__('User Data Requests (DSAR)', 'dual-chatbot') . '</h3>';
            echo '<p><label>' . esc_html__('User E-mail or User ID', 'dual-chatbot') . '</label><br />';
            echo '<input type="text" name="dcb_user_identifier" form="dcb-dsar-export" placeholder="user@example.com or 123" style="min-width:280px" />';
            echo '</p>';
            // Export
            echo '<form id="dcb-dsar-export" method="post" action="' . $base . '">';
            echo '<input type="hidden" name="action" value="dcb_dsar_export" />';
            echo '<input type="hidden" name="dcb_privacy_field" value="' . esc_attr($nonce) . '" />';
            echo '<button class="button button-primary">' . esc_html__('Export User Data (JSON)', 'dual-chatbot') . '</button>';
            echo '</form> ';
            // Delete
            echo '<form id="dcb-dsar-delete" method="post" action="' . $base . '" onsubmit="return confirm(\'' . esc_js(__('Delete all saved records for this user? This cannot be undone.', 'dual-chatbot')) . '\')">';
            echo '<input type="hidden" name="action" value="dcb_dsar_delete" />';
            echo '<input type="hidden" name="dcb_privacy_field" value="' . esc_attr($nonce) . '" />';
            echo '<input type="hidden" name="dcb_user_identifier" />';
            echo '<button class="button button-secondary">' . esc_html__('Delete User Data', 'dual-chatbot') . '</button>';
            echo '</form>';
            // Sync
            echo "<script>(function(){try{var input=document.querySelector('input[name=\"dcb_user_identifier\"][form=\"dcb-dsar-export\"]');var del=document.querySelector('#dcb-dsar-delete input[name=\"dcb_user_identifier\"]');if(input&&del){input.addEventListener('input',function(){del.value=input.value;});del.value=input.value;}}catch(e){}})();</script>";
            echo '</div>';
        },
        'dual-chatbot-settings-privacy',
        'dcb_privacy_section'
    );
});

// Admin-post handlers
add_action('admin_post_dcb_dsar_export', 'dcb_handle_dsar_export');
add_action('admin_post_dcb_dsar_delete',  'dcb_handle_dsar_delete');

function dcb_handle_dsar_export(): void {
    if (!current_user_can('manage_options')) { wp_die(__('Insufficient permissions.', 'dual-chatbot'), 403); }
    if (empty($_POST['dcb_privacy_field']) || !wp_verify_nonce($_POST['dcb_privacy_field'], 'dcb_privacy_nonce')) { wp_die(__('Invalid nonce.', 'dual-chatbot'), 403); }

    $id = sanitize_text_field($_POST['dcb_user_identifier'] ?? '');
    $user = is_email($id) ? get_user_by('email', $id) : get_user_by('id', (int)$id);
    if (!$user) { wp_die(__('User not found.', 'dual-chatbot'), 404); }

    global $wpdb;
    $uid = (int) $user->ID;
    $data = [
        'exported_at' => gmdate('c'),
        'user_id'     => $uid,
        'user_login'  => $user->user_login,
        'user_email'  => $user->user_email,
        'records'     => [],
    ];

    // History
    $tableH = dcb_table_history();
    if ($tableH) {
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$tableH} WHERE user_id = %d ORDER BY created_at ASC", $uid), ARRAY_A);
        $data['records']['history'] = $rows ?: [];
    }

    // Analytics
    $tableA = dcb_table_analytics();
    if ($tableA) {
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$tableA} WHERE user_id = %d ORDER BY created_at ASC", $uid), ARRAY_A);
        $data['records']['analytics'] = $rows ?: [];
    }

    nocache_headers();
    header('Content-Type: application/json; charset=' . get_bloginfo('charset'));
    header('Content-Disposition: attachment; filename="dual-chatbot-user-' . $uid . '-export.json"');
    echo wp_json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

function dcb_handle_dsar_delete(): void {
    if (!current_user_can('manage_options')) { wp_die(__('Insufficient permissions.', 'dual-chatbot'), 403); }
    if (empty($_POST['dcb_privacy_field']) || !wp_verify_nonce($_POST['dcb_privacy_field'], 'dcb_privacy_nonce')) { wp_die(__('Invalid nonce.', 'dual-chatbot'), 403); }

    $id = sanitize_text_field($_POST['dcb_user_identifier'] ?? '');
    $user = is_email($id) ? get_user_by('email', $id) : get_user_by('id', (int)$id);
    if (!$user) { wp_die(__('User not found.', 'dual-chatbot'), 404); }

    global $wpdb;
    $uid = (int) $user->ID;
    $deleted_total = 0;

    $tableH = dcb_table_history();
    if ($tableH) {
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $deleted_total += (int) $wpdb->query($wpdb->prepare("DELETE FROM {$tableH} WHERE user_id = %d", $uid));
    }
    $tableA = dcb_table_analytics();
    if ($tableA) {
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $deleted_total += (int) $wpdb->query($wpdb->prepare("DELETE FROM {$tableA} WHERE user_id = %d", $uid));
    }
    if (!empty($wpdb->last_error)) {
        error_log('[dual-chatbot] sql_error rid=' . wp_generate_uuid4() . ' err=' . $wpdb->last_error);
    }

    $redirect = add_query_arg([
        'page'             => 'dual-chatbot-settings',
        'settings-updated' => 'true',
        'tab'              => 'privacy',
        'dcb_dsar'         => 'deleted',
        'dcb_count'        => $deleted_total,
        'dcb_user'         => $uid,
    ], admin_url('options-general.php'));
    wp_safe_redirect($redirect);
    exit;
}
