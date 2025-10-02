<?php
// If uninstall not called from WordPress, then exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Unschedule cron task if present
if (function_exists('wp_next_scheduled')) {
    $ts = wp_next_scheduled('dual_chatbot_daily_maintenance');
    if ($ts) { wp_unschedule_event($ts, 'dual_chatbot_daily_maintenance'); }
}

// Respect admin setting: only purge data if explicitly enabled
$purge = (int) get_option('dcb_uninstall_purge', 0);
if (!$purge) { return; }

// Drop plugin tables (best-effort)
global $wpdb;
if (isset($wpdb)) {
    $tables = [
        'dual_chatbot_analytics',
        'chatbot_history',
        'chatbot_knowledge',
        'chatbot_sessions',
    ];
    foreach ($tables as $t) {
        $full = $wpdb->prefix . $t;
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $wpdb->query("DROP TABLE IF EXISTS `$full`");
    }
}

// Remove options (explicit list, no wildcard)
$options = [
    // Uninstall preference
    'dcb_uninstall_purge',
    // I18n fallback preference
    'dcb_force_de_labels',
    // Core enablement and modes
    'dual_chatbot_enabled',
    'dual_chatbot_faq_enabled',
    'dual_chatbot_advisor_enabled',
    // Design
    'dual_chatbot_theme',
    'dual_chatbot_primary_color',
    'dual_chatbot_icon_color',
    'dual_chatbot_faq_header_title',
    'dual_chatbot_advisor_header_title',
    'dual_chatbot_header_text_color',
    // UI text
    'dual_chatbot_greeting_faq',
    'dual_chatbot_greeting_members',
    'dual_chatbot_popup_greeting',
    'dual_chatbot_minimized_label',
    // Behavior
    'dual_chatbot_prechat_enabled',
    'dual_chatbot_prechat_require_name',
    'dual_chatbot_prechat_require_email',
    // API keys and endpoints
    'dual_chatbot_openai_api_key',
    'dual_chatbot_whisper_api_key',
    'dual_chatbot_myaplefy_api_key',
    'dual_chatbot_myaplefy_endpoint',
    'dual_chatbot_web_search_api_key',
    'dual_chatbot_web_search_api_endpoint',
    // Profile URL options
    'myplugin_profile_url_override',
    'myplugin_account_page_id',
    'dual_chatbot_profile_url',
    // Appearance & popup
    'dual_chatbot_bubble_shape',
    'dual_chatbot_bubble_size',
    'dual_chatbot_bubble_position',
    'dual_chatbot_bubble_color',
    'dual_chatbot_popup_width',
    'dual_chatbot_popup_height',
    'dual_chatbot_popup_header_bg_color',
    'dual_chatbot_popup_header_text_color',
    'dual_chatbot_popup_body_bg_color',
    'dual_chatbot_popup_border_radius',
    'dual_chatbot_popup_border_color',
    'dual_chatbot_bubble_icon_url',
    'dual_chatbot_send_btn_text_color',
    'dual_chatbot_newchat_btn_text_color',
    'dual_chatbot_input_placeholder',
    'dual_chatbot_send_icon_color',
    'dual_chatbot_mic_icon_color',
    // Tools & debug
    'dual_chatbot_simulate_membership',
    'dual_chatbot_debug_log',
    // Web search
    'dual_chatbot_web_search_enabled',
    'dual_chatbot_web_search_results',
    // Analytics/privacy
    'dcb_analytics_opt_in',
    'dcb_retention_days',
    'dual_chatbot_analytics_optout',
    'dual_chatbot_analytics_salt',
    // Models
    'dual_chatbot_faq_model',
    'dual_chatbot_advisor_model',
    // Validation
    'dual_chatbot_max_message_length',
    // Uploaded files list
    'dual_chatbot_uploaded_files',
];
foreach ($options as $opt) {
    delete_option($opt);
}
