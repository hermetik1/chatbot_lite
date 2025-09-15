<?php
/**
 * Plugin Name: Dual Chatbot Plugin
 * Plugin URI:  https://example.com/
 * Description: Provides a dual chatbot for FAQ and member advisory within WordPress. Implements server‑side RAG retrieval and integration with OpenAI for conversational support.
 * Version:     1.0.0
 * Author:      Nemanja Stojakovic
 * Author URI:  https://example.com/
 * Text Domain: dual-chatbot
 * Domain Path: /languages
 * Requires PHP: 8.1
 * Requires at least: 6.8
 * Tested up to: 6.8
 *
 * This plugin exposes a visitor FAQ bot and a members only advisor bot.  It indexes
 * uploaded knowledge base files into vector embeddings and stores conversation
 * history in a custom database table.  All API calls are performed server side
 * and secured via WordPress nonces.
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class Dual_Chatbot_Plugin {
    /**
     * Option keys used throughout the plugin.  These are defined once here to
     * avoid typos and allow for easy refactoring later.
     */
    const OPTION_ENABLED          = 'dual_chatbot_enabled';
    const OPTION_FAQ_ENABLED      = 'dual_chatbot_faq_enabled';
    const OPTION_ADVISOR_ENABLED  = 'dual_chatbot_advisor_enabled';
    const OPTION_PRIMARY_COLOR    = 'dual_chatbot_primary_color';
    // New minimal design options
    const OPTION_THEME            = 'dual_chatbot_theme'; // 'light' | 'dark' | 'auto'
    const OPTION_ICON_COLOR       = 'dual_chatbot_icon_color'; // empty = inherit
    const OPTION_OPENAI_API_KEY   = 'dual_chatbot_openai_api_key';
    const OPTION_WHISPER_API_KEY  = 'dual_chatbot_whisper_api_key';
    const OPTION_MYAPLEFY_API_KEY = 'dual_chatbot_myaplefy_api_key';
    const OPTION_MYAPLEFY_ENDPOINT= 'dual_chatbot_myaplefy_endpoint';
    // Dynamic profile URL settings
    const OPTION_PROFILE_URL_OVERRIDE = 'myplugin_profile_url_override';
    const OPTION_ACCOUNT_PAGE_ID      = 'myplugin_account_page_id';
    // Profile redirect option
    // Deprecated legacy option (kept for backward compatibility)
    const OPTION_PROFILE_URL     = 'dual_chatbot_profile_url';

    // Text color option

    // Additional color options for advanced customization

    // Sidebar text color

    // Appearance & popup customization options
    const OPTION_BUBBLE_SHAPE     = 'dual_chatbot_bubble_shape';
    const OPTION_BUBBLE_SIZE      = 'dual_chatbot_bubble_size';
    const OPTION_BUBBLE_POSITION  = 'dual_chatbot_bubble_position';
    const OPTION_BUBBLE_COLOR     = 'dual_chatbot_bubble_color';
    const OPTION_POPUP_WIDTH      = 'dual_chatbot_popup_width';
    const OPTION_POPUP_HEIGHT     = 'dual_chatbot_popup_height';
    const OPTION_POPUP_GREETING   = 'dual_chatbot_popup_greeting';
    // Header customization options
    const OPTION_FAQ_HEADER_TITLE     = 'dual_chatbot_faq_header_title';
    const OPTION_ADVISOR_HEADER_TITLE = 'dual_chatbot_advisor_header_title';
    const OPTION_HEADER_TEXT_COLOR    = 'dual_chatbot_header_text_color';

    // Additional appearance & behavior options
    const OPTION_BUBBLE_ICON_URL       = 'dual_chatbot_bubble_icon_url';
    const OPTION_SEND_BTN_TEXT_COLOR   = 'dual_chatbot_send_btn_text_color';
    const OPTION_NEWCHAT_BTN_TEXT_COLOR= 'dual_chatbot_newchat_btn_text_color';
    const OPTION_INPUT_PLACEHOLDER     = 'dual_chatbot_input_placeholder';
    
    // Icon colors (SVG recolor options)
    const OPTION_SEND_ICON_COLOR       = 'dual_chatbot_send_icon_color';
    const OPTION_MIC_ICON_COLOR        = 'dual_chatbot_mic_icon_color';

    // Behavior options
    // Removed: auto-open delay option
    const OPTION_PRECHAT_ENABLED       = 'dual_chatbot_prechat_enabled';
    const OPTION_PRECHAT_REQUIRE_NAME  = 'dual_chatbot_prechat_require_name';
    const OPTION_PRECHAT_REQUIRE_EMAIL = 'dual_chatbot_prechat_require_email';

    // Tools and debug options.
    const OPTION_SIMULATE_MEMBERSHIP = 'dual_chatbot_simulate_membership';
    const OPTION_DEBUG_LOG          = 'dual_chatbot_debug_log';

    // Web search options for advisor mode
    const OPTION_WEB_SEARCH_ENABLED   = 'dual_chatbot_web_search_enabled';
    const OPTION_WEB_SEARCH_RESULTS   = 'dual_chatbot_web_search_results';

    // Optional API key and endpoint for custom web search.  These allow the
    // site administrator to configure a third‑party search service.  If left
    // blank the plugin will default to using DuckDuckGo without an API key.
    const OPTION_WEB_SEARCH_API_KEY      = 'dual_chatbot_web_search_api_key';
    const OPTION_WEB_SEARCH_API_ENDPOINT = 'dual_chatbot_web_search_api_endpoint';

    // Popup customisation options
    const OPTION_POPUP_HEADER_BG_COLOR  = 'dual_chatbot_popup_header_bg_color';
    const OPTION_POPUP_HEADER_TEXT_COLOR= 'dual_chatbot_popup_header_text_color';
    const OPTION_POPUP_BODY_BG_COLOR    = 'dual_chatbot_popup_body_bg_color';
    const OPTION_POPUP_BORDER_RADIUS    = 'dual_chatbot_popup_border_radius';
    const OPTION_POPUP_BORDER_COLOR     = 'dual_chatbot_popup_border_color';

    // Label displayed when advisor chat is minimized
    const OPTION_MINIMIZED_LABEL        = 'dual_chatbot_minimized_label';

    // ChatGPT model options for FAQ and advisor modes
    // These allow the site administrator to select which OpenAI model should be used
    // when generating answers.  Default values fall back to the models previously
    // hard‑coded in the plugin (gpt‑3.5‑turbo for FAQ and gpt‑4o for advisor).
    const OPTION_FAQ_MODEL           = 'dual_chatbot_faq_model';
    const OPTION_ADVISOR_MODEL       = 'dual_chatbot_advisor_model';

    /**
     * Centralized defaults for key options.
     * Used for register_setting defaults, reset actions and migration fallback.
     */
    const DEFAULTS = [
        // General
        'dual_chatbot_enabled'          => '1',
        'dual_chatbot_faq_enabled'      => '1',
        'dual_chatbot_advisor_enabled'  => '1',
        // removed: auto-open delay
        // Design
        'dual_chatbot_theme'            => 'auto',
        'dual_chatbot_primary_color'    => '#4e8cff',
        'dual_chatbot_icon_color'       => '',
        // Profile URL settings defaults
        'myplugin_profile_url_override' => '',
        'myplugin_account_page_id'      => 0,
        // Profile URL default (used in advisor sidebar profile link)
        'dual_chatbot_profile_url'      => '/profil',
        // Header customization defaults
        'dual_chatbot_faq_header_title'     => 'FAQ',
        'dual_chatbot_advisor_header_title' => 'Berater-Chat',
        'dual_chatbot_header_text_color'    => '',
        // UI text defaults
        'dual_chatbot_popup_greeting'   => 'Hallo! Wie können wir Ihnen heute helfen?',
        'dual_chatbot_minimized_label'  => 'Förderverband Chat',
        // API defaults empty by default
        'dual_chatbot_openai_api_key'   => '',
        'dual_chatbot_whisper_api_key'  => '',
        'dual_chatbot_myaplefy_api_key' => '',
        'dual_chatbot_myaplefy_endpoint'=> '',
        'dual_chatbot_web_search_api_key'      => '',
        'dual_chatbot_web_search_api_endpoint' => '',
        // Models
        'dual_chatbot_faq_model'        => 'gpt-3.5-turbo',
        'dual_chatbot_advisor_model'    => 'gpt-4o',
    ];

    /**
     * Constructor registers all of the hooks necessary to set up the plugin.
     */
    public function __construct() {
        register_activation_hook(__FILE__, [self::class, 'activate']);
        register_deactivation_hook(__FILE__, [self::class, 'deactivate']);

        add_action('init', [$this, 'register_assets']);
        add_action('init', [$this, 'load_textdomain']);
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        // Admin assets (color picker for settings)
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        // Migrate to new design options on admin
        add_action('admin_init', [$this, 'maybe_migrate_design_options']);

        // REST API endpoints are registered in their own class.
        require_once __DIR__ . '/includes/rest-api.php';
        Dual_Chatbot_Rest_API::get_instance();

        // Enqueue scripts on the front end when enabled.
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
        // Add body class for theme forcing
        add_filter('body_class', [$this, 'add_theme_body_class']);
        // Admin notice when manual override is active
        add_action('admin_notices', [$this, 'maybe_notice_profile_override']);
    }

    /**
     * Activation callback.  Creates custom database tables used by the
     * plugin.  Utilises dbDelta to safely add or modify columns without
     * destroying data.
     */
    public static function activate(): void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset_collate = $wpdb->get_charset_collate();

        // Table for storing chat history.
        $history_table = $wpdb->prefix . 'chatbot_history';
        $sql = "CREATE TABLE $history_table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NULL,
            session_id VARCHAR(255) NOT NULL,
            `timestamp` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            sender ENUM('user','bot') NOT NULL,
            message_content LONGTEXT NOT NULL,
            context VARCHAR(20) NOT NULL DEFAULT 'faq',
            PRIMARY KEY  (id),
            KEY session_id (session_id),
            KEY user_id (user_id),
            KEY context (context)
        ) $charset_collate;";
        dbDelta($sql);

        // Table for storing knowledge base chunks and embeddings.
        $knowledge_table = $wpdb->prefix . 'chatbot_knowledge';
        $sql2 = "CREATE TABLE $knowledge_table (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            file_name VARCHAR(255) NOT NULL,
            chunk_index INT NOT NULL,
            chunk_text LONGTEXT NOT NULL,
            embedding LONGTEXT NULL,
            PRIMARY KEY  (id),
            KEY file_name (file_name)
        ) $charset_collate;";
        dbDelta($sql2);

        // Table for storing chat sessions with titles and context.  One row per session.
        $sessions_table = $wpdb->prefix . 'chatbot_sessions';
        $sql3 = "CREATE TABLE $sessions_table (
            session_id VARCHAR(255) NOT NULL,
            user_id BIGINT UNSIGNED NULL,
            context VARCHAR(20) NOT NULL,
            title VARCHAR(255) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (session_id),
            KEY user_id (user_id),
            KEY context (context)
        ) $charset_collate;";
        dbDelta($sql3);
        // Ensure rewrite rules reflect any custom pages/permalinks
        flush_rewrite_rules();
    }

    /**
     * Deactivation callback.  Currently does nothing; custom tables and data
     * remain until the user deletes them manually via the admin panel.
     */
    public static function deactivate(): void {
        // We intentionally do not drop tables on deactivation to avoid data loss.
        flush_rewrite_rules();
    }

    /**
 * Register and localise the frontend assets.
 * Enqueue nur, wenn Plugin und mind. ein Modus aktiv ist.
 */
public function enqueue_frontend_assets(): void {
    $enabled         = get_option(self::OPTION_ENABLED, '0');
    $faq_enabled     = get_option(self::OPTION_FAQ_ENABLED, '0');
    $advisor_enabled = get_option(self::OPTION_ADVISOR_ENABLED, '0');

    if ($enabled !== '1' || ($faq_enabled !== '1' && $advisor_enabled !== '1')) {
        return;
    }

    // Determine asset versions via file modification time for cache busting
    $style_rel   = 'assets/css/chatbot.css';
    $script_rel  = 'assets/js/chatbot.js';
    $style_path  = plugin_dir_path(__FILE__) . $style_rel;
    $script_path = plugin_dir_path(__FILE__) . $script_rel;
    $style_ver   = file_exists($style_path) ? (string) filemtime($style_path) : '1.0.0';
    $script_ver  = file_exists($script_path) ? (string) filemtime($script_path) : '1.0.0';

    // CSS
    wp_enqueue_style(
        'dual-chatbot-style',
        plugins_url($style_rel, __FILE__),
        [],
        $style_ver
    );

    // Icon URLs as CSS variables (bubble, send, mic)
    $bubble_icon = plugins_url('assets/img/chatbot-icon.svg', __FILE__);
    $send_icon   = plugins_url('assets/img/send.svg', __FILE__);
    $mic_icon    = plugins_url('assets/img/mic.svg', __FILE__);
    wp_add_inline_style(
        'dual-chatbot-style',
        ":root{--dual-chatbot-icon:url('{$bubble_icon}');--dual-chatbot-send-icon:url('{$send_icon}');--dual-chatbot-mic-icon:url('{$mic_icon}');}"
    );

    // Inline CSS variables: only theme, primary, optional icon color
    $theme   = get_option(self::OPTION_THEME, self::DEFAULTS[self::OPTION_THEME] ?? 'auto');
    $primary = get_option(self::OPTION_PRIMARY_COLOR, self::DEFAULTS[self::OPTION_PRIMARY_COLOR] ?? '#4e8cff');
    $icon    = get_option(self::OPTION_ICON_COLOR, self::DEFAULTS[self::OPTION_ICON_COLOR] ?? '');

    $sanitize = static function(string $v): string {
        $v = trim($v);
        if ($v === '') return '';
        if (preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $v)) return $v;
        if (preg_match('/^rgba?\([\d\s.,%]+\)$/i', $v)) return $v;
        if (in_array($v, ['light','dark','auto'], true)) return $v;
        return '';
    };
    $pairs = [];
    $t = $sanitize((string)$theme);
    $p = $sanitize((string)$primary);
    $i = $sanitize((string)$icon);
    if ($t !== '') { $pairs[] = '--dual-chatbot-theme:' . $t; }
    if ($p !== '') { $pairs[] = '--dual-chatbot-primary:' . $p; }
    if ($i !== '') { $pairs[] = '--dual-chatbot-icon-color:' . $i; }
    if (!empty($pairs)) {
        wp_add_inline_style('dual-chatbot-style', ':root{' . implode(';', $pairs) . '}');
    }
    // Provide simplified primary variable expected by new CSS mapping
    wp_add_inline_style('dual-chatbot-style', ':root{--your-primary-color:' . esc_attr($primary) . ';}');
    // Header text color: admin override or automatic contrast vs primary
    $hdr_color_opt = self::sanitize_hex_or_empty(get_option(self::OPTION_HEADER_TEXT_COLOR, ''));
    $hdr_text = $hdr_color_opt !== '' ? $hdr_color_opt : self::contrast_color((string)$primary);
    wp_add_inline_style('dual-chatbot-style', ':root{--dual-chatbot-header-text-color:' . esc_attr($hdr_text) . ';}');

    // JS
    wp_enqueue_script(
        'dual-chatbot-script',
        plugins_url($script_rel, __FILE__),
        [],
        $script_ver,
        true
    );

    // ==== Daten für das Frontend vorbereiten ====
    $mic_icon_url = plugins_url('assets/img/mic.svg', __FILE__);

    $current_user = wp_get_current_user();
    $user_name    = '';
    $user_avatar  = '';
    if ($current_user && !empty($current_user->ID)) {
        $user_name   = $current_user->display_name ?: $current_user->user_login;
        $user_avatar = get_avatar_url($current_user->ID, ['size' => 64]);
    }

    // ALLE Daten in EINEM Aufruf bündeln

    wp_localize_script(
        'dual-chatbot-script',
        'DualChatbotConfig',
        [
            'restUrl'           => esc_url_raw(rest_url('chatbot/v1')),
            'nonce'             => wp_create_nonce('wp_rest'),
            'faqEnabled'        => ($faq_enabled === '1'),
            'advisorEnabled'    => ($advisor_enabled === '1'),
            'mode'              => ($advisor_enabled === '1' ? 'advisor' : (($faq_enabled === '1') ? 'faq' : 'none')),
            'whisperApiUrl'     => esc_url_raw(rest_url('chatbot/v1/whisper')),
                        'bubbleIconUrl'     => esc_url_raw(plugins_url('assets/img/chatbot-icon.svg', __FILE__)),
            'faqHeaderTitle'    => get_option(Dual_Chatbot_Plugin::OPTION_FAQ_HEADER_TITLE, Dual_Chatbot_Plugin::DEFAULTS[Dual_Chatbot_Plugin::OPTION_FAQ_HEADER_TITLE] ?? 'FAQ'),
            'advisorHeaderTitle'=> get_option(Dual_Chatbot_Plugin::OPTION_ADVISOR_HEADER_TITLE, Dual_Chatbot_Plugin::DEFAULTS[Dual_Chatbot_Plugin::OPTION_ADVISOR_HEADER_TITLE] ?? 'Berater-Chat'),
            'micIconUrl'        => esc_url_raw($mic_icon_url),
            'sendIconUrl'       => esc_url_raw(plugins_url('assets/img/send.svg', __FILE__)),
            'closeIconUrl'      => esc_url_raw(plugins_url('assets/img/close.svg', __FILE__)),
            'minimizeIconUrl'   => esc_url_raw(plugins_url('assets/img/minimize.svg', __FILE__)),
            'searchIconUrl'     => esc_url_raw(plugins_url('assets/img/search.svg', __FILE__)),
            'userName'          => $user_name,
            'userAvatar'        => $user_avatar,
            'profileUrl'        => esc_url_raw( ( is_user_logged_in() && ( $ov = get_option(self::OPTION_PROFILE_URL_OVERRIDE, '') ) ) ? $ov : myplugin_get_profile_url( $current_user ? $current_user->ID : 0 ) ),

            // Design is handled via CSS variables injected above

            // Bubble & Popup
            'bubbleShape'       => get_option(self::OPTION_BUBBLE_SHAPE, 'circle'),
            'bubbleSize'        => get_option(self::OPTION_BUBBLE_SIZE, 'medium'),
            'bubblePosition'    => get_option(self::OPTION_BUBBLE_POSITION, 'right'),
            'bubbleColor'       => get_option(self::OPTION_BUBBLE_COLOR, ''),
            'popupWidth'        => intval(get_option(self::OPTION_POPUP_WIDTH, 380)),
            'popupHeight'       => intval(get_option(self::OPTION_POPUP_HEIGHT, 500)),
            'popupGreeting'     => get_option(self::OPTION_POPUP_GREETING, self::DEFAULTS[self::OPTION_POPUP_GREETING] ?? ''),
            'popupHeaderBgColor'=> get_option(self::OPTION_POPUP_HEADER_BG_COLOR, ''),
            'popupHeaderTextColor'=> get_option(self::OPTION_POPUP_HEADER_TEXT_COLOR, ''),
            'popupBodyBgColor'  => get_option(self::OPTION_POPUP_BODY_BG_COLOR, ''),
            'popupBorderRadius' => intval(get_option(self::OPTION_POPUP_BORDER_RADIUS, 8)),
            'popupBorderColor'  => get_option(self::OPTION_POPUP_BORDER_COLOR, ''),

            // Verhalten
            'isLoggedIn'        => is_user_logged_in(),
            'inputPlaceholder'  => get_option(self::OPTION_INPUT_PLACEHOLDER, __('Nachricht…', 'dual-chatbot')),
            // removed: autoOpenDelay – option deprecated
            'preChatEnabled'    => get_option(self::OPTION_PRECHAT_ENABLED, '0') === '1',
            'preChatRequireName'=> get_option(self::OPTION_PRECHAT_REQUIRE_NAME, '0') === '1',
            'preChatRequireEmail'=> get_option(self::OPTION_PRECHAT_REQUIRE_EMAIL, '0') === '1',
            'simulateMembership'=> get_option(self::OPTION_SIMULATE_MEMBERSHIP, '0') === '1',
            'webSearchEnabled'  => get_option(self::OPTION_WEB_SEARCH_ENABLED, '0') === '1',
            'webSearchResults'  => intval(get_option(self::OPTION_WEB_SEARCH_RESULTS, 3)),
            'minimizedLabel'    => get_option(self::OPTION_MINIMIZED_LABEL, self::DEFAULTS[self::OPTION_MINIMIZED_LABEL] ?? __('Chat', 'dual-chatbot')),
        ]
    );
}


 /**
     * Register any additional scripts (none needed at this stage).  Kept for
     * completeness should future assets be needed.
     */
    public function register_assets(): void {
        // Placeholder for potential future asset registration.
    }

    /**
     * Show a small notice when a manual profile URL override is active.
     */
    public function maybe_notice_profile_override(): void {
        if (!current_user_can('manage_options')) { return; }
        if (empty(get_option(self::OPTION_PROFILE_URL_OVERRIDE, ''))) { return; }
        if (!isset($_GET['page']) || $_GET['page'] !== 'dual-chatbot-settings') { return; }
        echo '<div class="notice notice-warning is-dismissible"><p><strong>' . esc_html__('Hinweis:', 'dual-chatbot') . '</strong> ' . esc_html__('Manuelles Profil-URL-Override ist aktiv. Standard ist die automatische Ermittlung.', 'dual-chatbot') . '</p></div>';
    }

    /**
     * Sanitize a hex color or allow empty string to mean "use default".
     */
    public static function sanitize_hex_or_empty($value): string {
        $value = is_string($value) ? trim($value) : '';
        if ($value === '') {
            return '';
        }
        $hex = sanitize_hex_color($value);
        return is_string($hex) ? $hex : '';
    }

    /**
     * Compute a contrasting text color (#000000 or #ffffff) for the given hex background.
     */
    public static function contrast_color(string $hex): string {
        $hex = trim($hex);
        if ($hex === '' || !preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/i', $hex)) {
            return '#000000';
        }
        if (strlen($hex) === 4) {
            $hex = '#' . $hex[1].$hex[1] . $hex[2].$hex[2] . $hex[3].$hex[3];
        }
        $r = hexdec(substr($hex, 1, 2));
        $g = hexdec(substr($hex, 3, 2));
        $b = hexdec(substr($hex, 5, 2));
        $yiq = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;
        return ($yiq >= 128) ? '#000000' : '#ffffff';
    }

    /**
     * Default color values mapped to their option keys.
     * These mirror the CSS tokens in assets/css/chatbot.css :root.
     */
    public static function get_default_color_options(): array {
        // Only primary is kept; all other granular colors are derived in CSS.
        return [ self::OPTION_PRIMARY_COLOR => '#4e8cff' ];
    }

    /**
     * Enqueue admin-only assets for the settings page.
     * Loads WordPress's standard color picker and our init script.
     */
    public function enqueue_admin_assets(string $hook): void {
        // Only load on our settings page (match even on multisite/network screens)
        if (strpos($hook, 'dual-chatbot-settings') === false) {
            return;
        }
        // Core WordPress color picker
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');

        // Our tiny initializer for all color fields
        $admin_js_rel  = 'assets/js/admin-colorpicker.js';
        $admin_js_path = plugin_dir_path(__FILE__) . $admin_js_rel;
        $admin_js_ver  = file_exists($admin_js_path) ? (string) filemtime($admin_js_path) : '1.0.0';
        wp_enqueue_script(
            'dual-chatbot-admin-colorpicker',
            plugins_url($admin_js_rel, __FILE__),
            ['wp-color-picker', 'jquery'],
            $admin_js_ver,
            true
        );
        // Small admin CSS for color swatches and tidy descriptions
        $inline_css = '.dual-chatbot-color-swatch{display:inline-block;width:18px;height:18px;border:1px solid #ccd0d4;margin-left:8px;border-radius:3px;vertical-align:middle;box-sizing:border-box}.form-table .description{margin-top:4px;color:#555}';
        wp_add_inline_style('wp-color-picker', $inline_css);
    }

    /**
     * Load plugin text domain for translations.
     */
    public function load_textdomain(): void {
        load_plugin_textdomain(
            'dual-chatbot',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages'
        );
    }

    /**
     * Add the Chatbot settings page to the WordPress admin menu.
     */
    public function add_admin_menu(): void {
        add_options_page(
            __('Chatbot Einstellungen', 'dual-chatbot'),
            __('Chatbot Einstellungen', 'dual-chatbot'),
            'manage_options',
            'dual-chatbot-settings',
            [$this, 'render_settings_page']
        );
    }

    /**
     * Register settings using the WordPress Settings API.
     */
    public function register_settings(): void {
        // General section.
        register_setting('dual_chatbot_options', self::OPTION_ENABLED, ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field']);
        register_setting('dual_chatbot_options', self::OPTION_FAQ_ENABLED, ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field']);
        register_setting('dual_chatbot_options', self::OPTION_ADVISOR_ENABLED, ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field']);
        register_setting('dual_chatbot_options', self::OPTION_PRIMARY_COLOR, ['type' => 'string', 'sanitize_callback' => [self::class, 'sanitize_hex_or_empty']]);
        // New minimal design settings
        register_setting('dual_chatbot_options', self::OPTION_THEME, ['type' => 'string', 'sanitize_callback' => function($v){
            $v = is_string($v) ? trim($v) : 'auto';
            return in_array($v, ['light','dark','auto'], true) ? $v : 'auto';
        }]);
        register_setting('dual_chatbot_options', self::OPTION_ICON_COLOR, ['type' => 'string', 'sanitize_callback' => [self::class, 'sanitize_hex_or_empty']]);
        register_setting('dual_chatbot_options', self::OPTION_OPENAI_API_KEY, ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field']);
        register_setting('dual_chatbot_options', self::OPTION_WHISPER_API_KEY, ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field']);
        register_setting('dual_chatbot_options', self::OPTION_MYAPLEFY_API_KEY, ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field']);
        register_setting('dual_chatbot_options', self::OPTION_MYAPLEFY_ENDPOINT, ['type' => 'string', 'sanitize_callback' => 'esc_url_raw']);
        // Dynamic profile URL settings
        register_setting('dual_chatbot_options', self::OPTION_PROFILE_URL_OVERRIDE, ['type' => 'string', 'sanitize_callback' => 'esc_url_raw']);
        register_setting('dual_chatbot_options', self::OPTION_ACCOUNT_PAGE_ID, ['type' => 'integer', 'sanitize_callback' => 'absint']);
        // Profile URL setting
        register_setting('dual_chatbot_options', self::OPTION_PROFILE_URL, ['type' => 'string', 'sanitize_callback' => 'esc_url_raw']);

        // Header titles & color
        register_setting('dual_chatbot_options', self::OPTION_FAQ_HEADER_TITLE, ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field']);
        register_setting('dual_chatbot_options', self::OPTION_ADVISOR_HEADER_TITLE, ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field']);
        register_setting('dual_chatbot_options', self::OPTION_HEADER_TEXT_COLOR, ['type' => 'string', 'sanitize_callback' => [self::class, 'sanitize_hex_or_empty']]);
        // Removed legacy granular color settings
        // (Send/Mic icon recolor moved out to CSS defaults; not configurable)

        // Appearance customization settings
        register_setting('dual_chatbot_options', self::OPTION_BUBBLE_SHAPE, ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field']);
        register_setting('dual_chatbot_options', self::OPTION_BUBBLE_SIZE, ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field']);
        register_setting('dual_chatbot_options', self::OPTION_BUBBLE_POSITION, ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field']);
        // Bubble color removed to simplify design; use primary color / theme
        register_setting('dual_chatbot_options', self::OPTION_POPUP_WIDTH, ['type' => 'integer', 'sanitize_callback' => 'absint']);
        register_setting('dual_chatbot_options', self::OPTION_POPUP_HEIGHT, ['type' => 'integer', 'sanitize_callback' => 'absint']);
        register_setting('dual_chatbot_options', self::OPTION_POPUP_GREETING, ['type' => 'string', 'sanitize_callback' => 'wp_kses_post']);

        // Additional appearance & behaviour settings
        register_setting('dual_chatbot_options', self::OPTION_BUBBLE_ICON_URL, ['type' => 'string', 'sanitize_callback' => 'esc_url_raw']);
        register_setting('dual_chatbot_options', self::OPTION_INPUT_PLACEHOLDER, ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field']);

        // Behaviour settings
        // removed: auto-open delay option registration
        register_setting('dual_chatbot_options', self::OPTION_PRECHAT_ENABLED, ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field']);
        register_setting('dual_chatbot_options', self::OPTION_PRECHAT_REQUIRE_NAME, ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field']);
        register_setting('dual_chatbot_options', self::OPTION_PRECHAT_REQUIRE_EMAIL, ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field']);

        // Tools settings.
        register_setting('dual_chatbot_options', self::OPTION_SIMULATE_MEMBERSHIP, ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field']);
        register_setting('dual_chatbot_options', self::OPTION_DEBUG_LOG, ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field']);

        // Web search settings
        register_setting('dual_chatbot_options', self::OPTION_WEB_SEARCH_ENABLED, ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field']);
        register_setting('dual_chatbot_options', self::OPTION_WEB_SEARCH_RESULTS, ['type' => 'integer', 'sanitize_callback' => 'absint']);

        // Optional custom search API settings.  These provide the ability to
        // specify an API key and endpoint for a third‑party search service.
        // If both fields are empty the plugin falls back to the default
        // DuckDuckGo search without authentication.
        register_setting('dual_chatbot_options', self::OPTION_WEB_SEARCH_API_KEY, ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field']);
        register_setting('dual_chatbot_options', self::OPTION_WEB_SEARCH_API_ENDPOINT, ['type' => 'string', 'sanitize_callback' => 'esc_url_raw']);

        // Popup customisation color settings removed; rely on theme/CSS

        // Minimized label setting
        register_setting('dual_chatbot_options', self::OPTION_MINIMIZED_LABEL, ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field']);

        // ChatGPT model settings
        register_setting('dual_chatbot_options', self::OPTION_FAQ_MODEL, ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field']);
        register_setting('dual_chatbot_options', self::OPTION_ADVISOR_MODEL, ['type' => 'string', 'sanitize_callback' => 'sanitize_text_field']);

        // Sections for each tab.  We will render only the relevant sections based on the current tab.
        add_settings_section('dual_chatbot_general', __('Allgemeine Einstellungen', 'dual-chatbot'), function() {
            echo '<p>' . esc_html__('Aktiviere oder deaktiviere die verschiedenen Chatbot-Modi und passe die Farben an.', 'dual-chatbot') . '</p>';
        }, 'dual-chatbot-settings-general');

        add_settings_field(self::OPTION_ENABLED, __('Plugin aktivieren', 'dual-chatbot'), function() {
            $value = get_option(self::OPTION_ENABLED, '0');
            // Hidden field to ensure a value is always sent
            echo '<input type="hidden" name="' . esc_attr(self::OPTION_ENABLED) . '" value="0" />';
            echo '<input type="checkbox" name="' . esc_attr(self::OPTION_ENABLED) . '" value="1" ' . checked($value, '1', false) . ' />';
        }, 'dual-chatbot-settings-general', 'dual_chatbot_general');

        add_settings_field(self::OPTION_FAQ_ENABLED, __('FAQ-Bot aktivieren', 'dual-chatbot'), function() {
            $value = get_option(self::OPTION_FAQ_ENABLED, '0');
            echo '<input type="hidden" name="' . esc_attr(self::OPTION_FAQ_ENABLED) . '" value="0" />';
            echo '<input type="checkbox" name="' . esc_attr(self::OPTION_FAQ_ENABLED) . '" value="1" ' . checked($value, '1', false) . ' />';
        }, 'dual-chatbot-settings-general', 'dual_chatbot_general');

        add_settings_field(self::OPTION_ADVISOR_ENABLED, __('Berater-Bot aktivieren', 'dual-chatbot'), function() {
            $value = get_option(self::OPTION_ADVISOR_ENABLED, '0');
            echo '<input type="hidden" name="' . esc_attr(self::OPTION_ADVISOR_ENABLED) . '" value="0" />';
            echo '<input type="checkbox" name="' . esc_attr(self::OPTION_ADVISOR_ENABLED) . '" value="1" ' . checked($value, '1', false) . ' />';
        }, 'dual-chatbot-settings-general', 'dual_chatbot_general');

        // Profile URL (override) field
        add_settings_field(self::OPTION_PROFILE_URL_OVERRIDE, __('Profil-URL (optional)', 'dual-chatbot'), function() {
            $value = get_option(self::OPTION_PROFILE_URL_OVERRIDE, self::DEFAULTS[self::OPTION_PROFILE_URL_OVERRIDE]);
            echo '<input type="url" class="regular-text" name="' . esc_attr(self::OPTION_PROFILE_URL_OVERRIDE) . '" value="' . esc_attr($value) . '" placeholder="https://example.com/account" />';
            echo '<p class="description">' . esc_html__('Nur für Sonderfälle – Standard ist die automatische Ermittlung über UM/BuddyPress/Account-Seite.', 'dual-chatbot') . '</p>';
        }, 'dual-chatbot-settings-general', 'dual_chatbot_general');

        // Account page selection (optional)
        add_settings_field(self::OPTION_ACCOUNT_PAGE_ID, __('Account-Seite (optional)', 'dual-chatbot'), function() {
            $value = (int) get_option(self::OPTION_ACCOUNT_PAGE_ID, self::DEFAULTS[self::OPTION_ACCOUNT_PAGE_ID]);
            wp_dropdown_pages([
                'name'              => self::OPTION_ACCOUNT_PAGE_ID,
                'echo'              => 1,
                'show_option_none'  => __('-- Keine --', 'dual-chatbot'),
                'option_none_value' => '0',
                'selected'          => $value,
            ]);
            echo '<p class="description">' . esc_html__('Falls gesetzt, wird das Permalink dieser Seite als Profilziel genutzt (sprachspezifisch).', 'dual-chatbot') . '</p>';
        }, 'dual-chatbot-settings-general', 'dual_chatbot_general');

        

        // Auto-open delay (removed)




        // Hide legacy color controls under General (migrated to Design)
        if (false) {

        add_settings_field(self::OPTION_PRIMARY_COLOR, __('Primäre Farbe', 'dual-chatbot'), function() {
            $value = get_option(self::OPTION_PRIMARY_COLOR, '');
            echo '<input type="text" class="regular-text" name="' . esc_attr(self::OPTION_PRIMARY_COLOR) . '" value="' . esc_attr($value) . '" placeholder="#4e8cff" data-default-color="#4e8cff" />';
            echo '<p class="description">' . esc_html__('Akzent-/Markenfarbe: beeinflusst u.a. aktive Zustände und Header-Farbe (falls konfiguriert).', 'dual-chatbot') . '</p>';
        }, 'dual-chatbot-settings-general', 'dual_chatbot_general');

        // Text color field
        /* BEGIN LEGACY COLOR FIELDS REMOVED
        add_settings_field(self::OPTION_TEXT_COLOR, __('Schriftfarbe', 'dual-chatbot'), function() {
            $value = get_option(self::OPTION_TEXT_COLOR, '');
            echo '<input type="text" class="regular-text" name="' . esc_attr(self::OPTION_TEXT_COLOR) . '" value="' . esc_attr($value) . '" placeholder="#222" data-default-color="#222" />';
            echo '<p class="description">' . esc_html__('Wähle die Farbe für den Text der Chatnachrichten (Hex-Code, z.B. #333333).', 'dual-chatbot') . '</p>';
        }, 'dual-chatbot-settings-general', 'dual_chatbot_general');

        // Additional color fields
        add_settings_field(self::OPTION_BOT_BG_COLOR, __('Bot-Hintergrundfarbe', 'dual-chatbot'), function() {
            $value = get_option(self::OPTION_BOT_BG_COLOR, '');
            echo '<input type="text" class="regular-text" name="' . esc_attr(self::OPTION_BOT_BG_COLOR) . '" value="' . esc_attr($value) . '" placeholder="#f0f4fb" data-default-color="#f0f4fb" />';
            echo '<p class="description">' . esc_html__('Farbe für die Hintergrundflächen der Bot-Nachrichten (z.B. #e0e0e0).', 'dual-chatbot') . '</p>';
        }, 'dual-chatbot-settings-general', 'dual_chatbot_general');
        add_settings_field(self::OPTION_USER_BG_COLOR, __('Benutzer-Hintergrundfarbe', 'dual-chatbot'), function() {
            $value = get_option(self::OPTION_USER_BG_COLOR, '');
            echo '<input type="text" class="regular-text" name="' . esc_attr(self::OPTION_USER_BG_COLOR) . '" value="' . esc_attr($value) . '" placeholder="#e1f5e6" data-default-color="#e1f5e6" />';
            echo '<p class="description">' . esc_html__('Farbe für die Hintergrundflächen der Benutzer-Nachrichten.', 'dual-chatbot') . '</p>';
        }, 'dual-chatbot-settings-general', 'dual_chatbot_general');
        add_settings_field(self::OPTION_SIDEBAR_BG_COLOR, __('Sidebar-Hintergrundfarbe', 'dual-chatbot'), function() {
            $value = get_option(self::OPTION_SIDEBAR_BG_COLOR, '');
            echo '<input type="text" class="regular-text" name="' . esc_attr(self::OPTION_SIDEBAR_BG_COLOR) . '" value="' . esc_attr($value) . '" placeholder="#202738" data-default-color="#202738" />';
            echo '<p class="description">' . esc_html__('Farbe für die Hintergrundfläche der Seitenleiste im Berater-Chat.', 'dual-chatbot') . '</p>';
        }, 'dual-chatbot-settings-general', 'dual_chatbot_general');
        add_settings_field(self::OPTION_MAIN_BG_COLOR, __('Hintergrundfarbe Chatbereich', 'dual-chatbot'), function() {
            $value = get_option(self::OPTION_MAIN_BG_COLOR, '');
            echo '<input type="text" class="regular-text" name="' . esc_attr(self::OPTION_MAIN_BG_COLOR) . '" value="' . esc_attr($value) . '" placeholder="#ffffff" data-default-color="#ffffff" />';
            echo '<p class="description">' . esc_html__('Farbe für die Hintergrundfläche des Chatbereichs.', 'dual-chatbot') . '</p>';
        }, 'dual-chatbot-settings-general', 'dual_chatbot_general');
        add_settings_field(self::OPTION_SEND_BTN_COLOR, __('Senden-Button Farbe', 'dual-chatbot'), function() {
            $value = get_option(self::OPTION_SEND_BTN_COLOR, '');
            echo '<input type="text" class="regular-text" name="' . esc_attr(self::OPTION_SEND_BTN_COLOR) . '" value="' . esc_attr($value) . '" placeholder="#4e8cff" data-default-color="#4e8cff" />';
            echo '<p class="description">' . esc_html__('Hintergrund des Senden-Buttons (Eingabebereich).', 'dual-chatbot') . '</p>';
        }, 'dual-chatbot-settings-general', 'dual_chatbot_general');
        add_settings_field(self::OPTION_NEWCHAT_BTN_COLOR, __('Neuer-Chat Button Farbe', 'dual-chatbot'), function() {
            $value = get_option(self::OPTION_NEWCHAT_BTN_COLOR, '');
            echo '<input type="text" class="regular-text" name="' . esc_attr(self::OPTION_NEWCHAT_BTN_COLOR) . '" value="' . esc_attr($value) . '" placeholder="#e8eaf6" data-default-color="#e8eaf6" />';
            echo '<p class="description">' . esc_html__('Hintergrund des „Neuer Chat“-Buttons in der Sidebar.', 'dual-chatbot') . '</p>';
        }, 'dual-chatbot-settings-general', 'dual_chatbot_general');

        add_settings_field(self::OPTION_SIDEBAR_TEXT_COLOR, __('Sidebar Schriftfarbe', 'dual-chatbot'), function() {
            $value = get_option(self::OPTION_SIDEBAR_TEXT_COLOR, '');
            echo '<input type="text" class="regular-text" name="' . esc_attr(self::OPTION_SIDEBAR_TEXT_COLOR) . '" value="' . esc_attr($value) . '" placeholder="#ffffff" data-default-color="#ffffff" />';
            echo '<p class="description">' . esc_html__('Farbe für den Text in der Seitenleiste.', 'dual-chatbot') . '</p>';
        }, 'dual-chatbot-settings-general', 'dual_chatbot_general');

        add_settings_field(self::OPTION_SECONDARY_COLOR, __('Sekundäre Farbe', 'dual-chatbot'), function() {
            $value = get_option(self::OPTION_SECONDARY_COLOR, '');
            echo '<input type="text" class="regular-text" name="' . esc_attr(self::OPTION_SECONDARY_COLOR) . '" value="' . esc_attr($value) . '" placeholder="#f5f6fa" data-default-color="#f5f6fa" />';
            echo '<p class="description">' . esc_html__('Helle Sekundärflächen (z. B. Hintergründe oder Karten).', 'dual-chatbot') . '</p>';
        }, 'dual-chatbot-settings-general', 'dual_chatbot_general');

        */
        }
        // Design section (new consolidated design tab)
        add_settings_section('dual_chatbot_design', __('Design', 'dual-chatbot'), function() {
            echo '<p>' . esc_html__('Thema, Primärfarbe und (optional) Icon-Farbe. Alle weiteren Farben werden automatisch abgeleitet.', 'dual-chatbot') . '</p>';
        }, 'dual-chatbot-settings-design');
        add_settings_field(self::OPTION_THEME, __('Thema', 'dual-chatbot'), function() {
            $value = get_option(self::OPTION_THEME, self::DEFAULTS[self::OPTION_THEME]);
            echo '<select name="' . esc_attr(self::OPTION_THEME) . '">';
            foreach ([
                'auto' => __('Auto (System)', 'dual-chatbot'),
                'light' => __('Hell', 'dual-chatbot'),
                'dark' => __('Dunkel', 'dual-chatbot'),
            ] as $k => $label) {
                echo '<option value="' . esc_attr($k) . '"' . selected($value, $k, false) . '>' . esc_html($label) . '</option>';
            }
            echo '</select>';
        }, 'dual-chatbot-settings-design', 'dual_chatbot_design');
        add_settings_field(self::OPTION_PRIMARY_COLOR, __('Primärfarbe', 'dual-chatbot'), function() {
            $value = get_option(self::OPTION_PRIMARY_COLOR, self::DEFAULTS[self::OPTION_PRIMARY_COLOR]);
            echo '<input type="text" class="regular-text" '
                . 'name="' . esc_attr(self::OPTION_PRIMARY_COLOR) . '" '
                . 'value="' . esc_attr($value) . '" '
                . 'placeholder="#4e8cff" data-default-color="#4e8cff" />';
        }, 'dual-chatbot-settings-design', 'dual_chatbot_design');
        add_settings_field(self::OPTION_ICON_COLOR, __('Icon-Farbe (optional)', 'dual-chatbot'), function() {
            $value = get_option(self::OPTION_ICON_COLOR, self::DEFAULTS[self::OPTION_ICON_COLOR]);
            echo '<input type="text" class="regular-text" name="' . esc_attr(self::OPTION_ICON_COLOR) . '" value="' . esc_attr($value) . '" placeholder="' . esc_attr__('leer = erben', 'dual-chatbot') . '" />';
            echo '<p class="description">' . esc_html__('Leer lassen, um von der Textfarbe zu erben.', 'dual-chatbot') . '</p>';
        }, 'dual-chatbot-settings-design', 'dual_chatbot_design');
        add_settings_field(self::OPTION_FAQ_HEADER_TITLE, __('FAQ Header-Titel', 'dual-chatbot'), function() {
            $value = get_option(self::OPTION_FAQ_HEADER_TITLE, self::DEFAULTS[self::OPTION_FAQ_HEADER_TITLE]);
            echo '<input type="text" class="regular-text" name="' . esc_attr(self::OPTION_FAQ_HEADER_TITLE) . '" value="' . esc_attr($value) . '" />';
        }, 'dual-chatbot-settings-design', 'dual_chatbot_design');
        add_settings_field(self::OPTION_ADVISOR_HEADER_TITLE, __('Berater Header-Titel', 'dual-chatbot'), function() {
            $value = get_option(self::OPTION_ADVISOR_HEADER_TITLE, self::DEFAULTS[self::OPTION_ADVISOR_HEADER_TITLE]);
            echo '<input type="text" class="regular-text" name="' . esc_attr(self::OPTION_ADVISOR_HEADER_TITLE) . '" value="' . esc_attr($value) . '" />';
        }, 'dual-chatbot-settings-design', 'dual_chatbot_design');
        add_settings_field(self::OPTION_HEADER_TEXT_COLOR, __('Header-Textfarbe', 'dual-chatbot'), function() {
            $value = get_option(self::OPTION_HEADER_TEXT_COLOR, '');
            echo '<input type="text" class="regular-text" name="' . esc_attr(self::OPTION_HEADER_TEXT_COLOR) . '" value="' . esc_attr($value) . '" placeholder="#ffffff" data-default-color="" />';
            echo '<p class="description">' . esc_html__('Leer lassen = automatische Kontrastfarbe zur Primärfarbe.', 'dual-chatbot') . '</p>';
        }, 'dual-chatbot-settings-design', 'dual_chatbot_design');
        add_settings_field('dual_chatbot_reset_colors', __('Farben auf Standard', 'dual-chatbot'), [$this, 'render_reset_colors_button'], 'dual-chatbot-settings-design', 'dual_chatbot_design');

        // API section.
        add_settings_section('dual_chatbot_api', __('API Schlüssel', 'dual-chatbot'), function() {
            echo '<p>' . esc_html__('Hinterlege hier die benötigten API-Schlüssel. Diese werden ausschließlich serverseitig verwendet.', 'dual-chatbot') . '</p>';
        }, 'dual-chatbot-settings-api');
        add_settings_field(self::OPTION_OPENAI_API_KEY, __('OpenAI API-Key', 'dual-chatbot'), function() {
            $value = get_option(self::OPTION_OPENAI_API_KEY, '');
            echo '<input type="password" class="regular-text" name="' . esc_attr(self::OPTION_OPENAI_API_KEY) . '" value="' . esc_attr($value) . '" autocomplete="off" />';
        }, 'dual-chatbot-settings-api', 'dual_chatbot_api');
        add_settings_field(self::OPTION_WHISPER_API_KEY, __('Whisper API-Key', 'dual-chatbot'), function() {
            $value = get_option(self::OPTION_WHISPER_API_KEY, '');
            echo '<input type="password" class="regular-text" name="' . esc_attr(self::OPTION_WHISPER_API_KEY) . '" value="' . esc_attr($value) . '" autocomplete="off" />';
        }, 'dual-chatbot-settings-api', 'dual_chatbot_api');
        add_settings_field(self::OPTION_MYAPLEFY_API_KEY, __('myablefy API-Key', 'dual-chatbot'), function() {
            $value = get_option(self::OPTION_MYAPLEFY_API_KEY, '');
            echo '<input type="password" class="regular-text" name="' . esc_attr(self::OPTION_MYAPLEFY_API_KEY) . '" value="' . esc_attr($value) . '" autocomplete="off" />';
        }, 'dual-chatbot-settings-api', 'dual_chatbot_api');
        add_settings_field(self::OPTION_MYAPLEFY_ENDPOINT, __('myablefy API-Endpunkt', 'dual-chatbot'), function() {
            $value = get_option(self::OPTION_MYAPLEFY_ENDPOINT, '');
            echo '<input type="text" class="regular-text" name="' . esc_attr(self::OPTION_MYAPLEFY_ENDPOINT) . '" value="' . esc_attr($value) . '" />';
        }, 'dual-chatbot-settings-api', 'dual_chatbot_api');

        // Custom web search API fields
        add_settings_field(self::OPTION_WEB_SEARCH_API_KEY, __('Websuche API-Key', 'dual-chatbot'), function() {
            $value = get_option(self::OPTION_WEB_SEARCH_API_KEY, '');
            echo '<input type="password" class="regular-text" name="' . esc_attr(self::OPTION_WEB_SEARCH_API_KEY) . '" value="' . esc_attr($value) . '" autocomplete="off" />';
            echo '<p class="description">' . esc_html__('Optional: API-Schlüssel für eine benutzerdefinierte Websuche. Wird leer gelassen, wenn keine Authentifizierung erforderlich ist.', 'dual-chatbot') . '</p>';
        }, 'dual-chatbot-settings-api', 'dual_chatbot_api');
        add_settings_field(self::OPTION_WEB_SEARCH_API_ENDPOINT, __('Websuche API-Endpunkt', 'dual-chatbot'), function() {
            $value = get_option(self::OPTION_WEB_SEARCH_API_ENDPOINT, '');
            echo '<input type="text" class="regular-text" name="' . esc_attr(self::OPTION_WEB_SEARCH_API_ENDPOINT) . '" value="' . esc_attr($value) . '" />';
            echo '<p class="description">' . esc_html__('Optional: Basis-URL für die Websuche. Verwende {query} als Platzhalter für die Anfrage und {api_key} für den API-Schlüssel, falls erforderlich. Wenn leer, wird DuckDuckGo als Standard verwendet.', 'dual-chatbot') . '</p>';
        }, 'dual-chatbot-settings-api', 'dual_chatbot_api');

        // ChatGPT model selection for FAQ and advisor modes
        add_settings_field(self::OPTION_FAQ_MODEL, __('FAQ ChatGPT Modell', 'dual-chatbot'), function() {
            $value = get_option(self::OPTION_FAQ_MODEL, 'gpt-3.5-turbo');
            $models = [
                'gpt-3.5-turbo' => 'gpt-3.5-turbo',
                'gpt-4'        => 'gpt-4',
                'gpt-4o'       => 'gpt-4o',
            ];
            echo '<select name="' . esc_attr(self::OPTION_FAQ_MODEL) . '">';
            foreach ($models as $model_value => $label) {
                echo '<option value="' . esc_attr($model_value) . '"' . selected($value, $model_value, false) . '>' . esc_html($label) . '</option>';
            }
            echo '</select>';
            echo '<p class="description">' . esc_html__('Wähle das ChatGPT-Modell für den FAQ-Bot.', 'dual-chatbot') . '</p>';
        }, 'dual-chatbot-settings-api', 'dual_chatbot_api');

        add_settings_field(self::OPTION_ADVISOR_MODEL, __('Berater ChatGPT Modell', 'dual-chatbot'), function() {
            $value = get_option(self::OPTION_ADVISOR_MODEL, 'gpt-4o');
            $models = [
                'gpt-3.5-turbo' => 'gpt-3.5-turbo',
                'gpt-4'        => 'gpt-4',
                'gpt-4o'       => 'gpt-4o',
            ];
            echo '<select name="' . esc_attr(self::OPTION_ADVISOR_MODEL) . '">';
            foreach ($models as $model_value => $label) {
                echo '<option value="' . esc_attr($model_value) . '"' . selected($value, $model_value, false) . '>' . esc_html($label) . '</option>';
            }
            echo '</select>';
            echo '<p class="description">' . esc_html__('Wähle das ChatGPT-Modell für den Berater-Bot.', 'dual-chatbot') . '</p>';
        }, 'dual-chatbot-settings-api', 'dual_chatbot_api');

        // Knowledge base section.
        add_settings_section('dual_chatbot_knowledge', __('Wissensdatenbank', 'dual-chatbot'), function() {
            echo '<p>' . esc_html__('Lade Text- oder PDF-Dateien hoch, die der FAQ-Bot als Wissensquelle nutzen soll. Nachdem Dateien hochgeladen wurden, kannst Du sie indizieren.', 'dual-chatbot') . '</p>';
        }, 'dual-chatbot-settings-knowledge');
        add_settings_field('dual_chatbot_file_upload', __('FAQ-Dokumente', 'dual-chatbot'), [$this, 'render_file_upload_field'], 'dual-chatbot-settings-knowledge', 'dual_chatbot_knowledge');
        add_settings_field('dual_chatbot_index_button', __('Wissen neu indizieren', 'dual-chatbot'), [$this, 'render_index_button'], 'dual-chatbot-settings-knowledge', 'dual_chatbot_knowledge');
        add_settings_field('dual_chatbot_cleanup_button', __('Gesprächsverläufe löschen', 'dual-chatbot'), [$this, 'render_cleanup_button'], 'dual-chatbot-settings-knowledge', 'dual_chatbot_knowledge');

        // Tools section.
        add_settings_section('dual_chatbot_tools', __('Werkzeuge', 'dual-chatbot'), function() {
            echo '<p>' . esc_html__('Nützliche Werkzeuge für Tests, Logging und Wartung.', 'dual-chatbot') . '</p>';
        }, 'dual-chatbot-settings-tools');
        add_settings_field(self::OPTION_SIMULATE_MEMBERSHIP, __('Mitglieder-Verifizierung simulieren', 'dual-chatbot'), function() {
            $value = get_option(self::OPTION_SIMULATE_MEMBERSHIP, '0');
            // Hidden input ensures a value is always submitted when unchecked
            echo '<input type="hidden" name="' . esc_attr(self::OPTION_SIMULATE_MEMBERSHIP) . '" value="0" />';
            echo '<input type="checkbox" name="' . esc_attr(self::OPTION_SIMULATE_MEMBERSHIP) . '" value="1" ' . checked($value, '1', false) . ' /> ';
            echo '<span class="description">' . esc_html__('Wenn aktiv, werden alle Benutzer als Mitglieder behandelt (Testmodus).', 'dual-chatbot') . '</span>';
        }, 'dual-chatbot-settings-tools', 'dual_chatbot_tools');
        add_settings_field(self::OPTION_DEBUG_LOG, __('Debug-Log aktivieren', 'dual-chatbot'), function() {
            $value = get_option(self::OPTION_DEBUG_LOG, '0');
            echo '<input type="hidden" name="' . esc_attr(self::OPTION_DEBUG_LOG) . '" value="0" />';
            echo '<input type="checkbox" name="' . esc_attr(self::OPTION_DEBUG_LOG) . '" value="1" ' . checked($value, '1', false) . ' /> ';
            echo '<span class="description">' . esc_html__('Protokolliere API-Aufrufe und Fehler in eine Log-Datei.', 'dual-chatbot') . '</span>';
        }, 'dual-chatbot-settings-tools', 'dual_chatbot_tools');
        add_settings_field('dual_chatbot_clear_cache', __('Cache leeren', 'dual-chatbot'), [$this, 'render_clear_cache_button'], 'dual-chatbot-settings-tools', 'dual_chatbot_tools');
        add_settings_field('dual_chatbot_reset_settings', __('Einstellungen zurücksetzen', 'dual-chatbot'), [$this, 'render_reset_settings_button'], 'dual-chatbot-settings-tools', 'dual_chatbot_tools');

        // Behaviour section: controls when the popup opens and pre-chat form.
        add_settings_section('dual_chatbot_behavior', __('Verhalten & Trigger', 'dual-chatbot'), function() {
            echo '<p>' . esc_html__('Steuere, wann das Chatfenster automatisch geöffnet wird und ob ein Vorab-Formular angezeigt wird.', 'dual-chatbot') . '</p>';
        }, 'dual-chatbot-settings-behavior');
        // Auto-open delay field
        // removed: auto-open delay field (deprecated)
        // Pre-chat enabled
        add_settings_field(self::OPTION_PRECHAT_ENABLED, __('Vorab-Formular aktivieren', 'dual-chatbot'), function() {
            $value = get_option(self::OPTION_PRECHAT_ENABLED, '0');
            echo '<input type="hidden" name="' . esc_attr(self::OPTION_PRECHAT_ENABLED) . '" value="0" />';
            echo '<input type="checkbox" name="' . esc_attr(self::OPTION_PRECHAT_ENABLED) . '" value="1" ' . checked($value, '1', false) . ' />';
            echo '<p class="description">' . esc_html__('Wenn aktiv, müssen Besucher vor dem Chat ihren Namen und/oder E-Mail angeben.', 'dual-chatbot') . '</p>';
        }, 'dual-chatbot-settings-behavior', 'dual_chatbot_behavior');
        // Require name
        add_settings_field(self::OPTION_PRECHAT_REQUIRE_NAME, __('Name erforderlich', 'dual-chatbot'), function() {
            $value = get_option(self::OPTION_PRECHAT_REQUIRE_NAME, '0');
            echo '<input type="hidden" name="' . esc_attr(self::OPTION_PRECHAT_REQUIRE_NAME) . '" value="0" />';
            echo '<input type="checkbox" name="' . esc_attr(self::OPTION_PRECHAT_REQUIRE_NAME) . '" value="1" ' . checked($value, '1', false) . ' />';
            echo '<p class="description">' . esc_html__('Fordere den Benutzer auf, seinen Namen anzugeben.', 'dual-chatbot') . '</p>';
        }, 'dual-chatbot-settings-behavior', 'dual_chatbot_behavior');
        // Require email
        add_settings_field(self::OPTION_PRECHAT_REQUIRE_EMAIL, __('E-Mail erforderlich', 'dual-chatbot'), function() {
            $value = get_option(self::OPTION_PRECHAT_REQUIRE_EMAIL, '0');
            echo '<input type="hidden" name="' . esc_attr(self::OPTION_PRECHAT_REQUIRE_EMAIL) . '" value="0" />';
            echo '<input type="checkbox" name="' . esc_attr(self::OPTION_PRECHAT_REQUIRE_EMAIL) . '" value="1" ' . checked($value, '1', false) . ' />';
            echo '<p class="description">' . esc_html__('Fordere den Benutzer auf, seine E-Mail-Adresse anzugeben.', 'dual-chatbot') . '</p>';
        }, 'dual-chatbot-settings-behavior', 'dual_chatbot_behavior');

        // Web search enable checkbox (advisor only)
        add_settings_field(self::OPTION_WEB_SEARCH_ENABLED, __('Websuche aktivieren (nur Mitglieder)', 'dual-chatbot'), function() {
            $value = get_option(self::OPTION_WEB_SEARCH_ENABLED, '0');
            echo '<input type="hidden" name="' . esc_attr(self::OPTION_WEB_SEARCH_ENABLED) . '" value="0" />';
            echo '<input type="checkbox" name="' . esc_attr(self::OPTION_WEB_SEARCH_ENABLED) . '" value="1" ' . checked($value, '1', false) . ' />';
            echo '<p class="description">' . esc_html__('Wenn aktiviert, führt der Berater-Bot bei Bedarf eine Websuche durch und fügt Quellen als Fußnoten hinzu.', 'dual-chatbot') . '</p>';
        }, 'dual-chatbot-settings-behavior', 'dual_chatbot_behavior');
        // Web search results count
        add_settings_field(self::OPTION_WEB_SEARCH_RESULTS, __('Anzahl Suchergebnisse', 'dual-chatbot'), function() {
            $value = get_option(self::OPTION_WEB_SEARCH_RESULTS, 3);
            echo '<input type="number" class="small-text" name="' . esc_attr(self::OPTION_WEB_SEARCH_RESULTS) . '" value="' . esc_attr($value) . '" min="1" max="5" />';
            echo '<p class="description">' . esc_html__('Wie viele Suchergebnisse sollen maximal als Quellen erscheinen? (1–5)', 'dual-chatbot') . '</p>';
        }, 'dual-chatbot-settings-behavior', 'dual_chatbot_behavior');

        // Appearance section: controls the look and feel of the popup and icon.
        add_settings_section('dual_chatbot_appearance', __('Popup & Erscheinungsbild', 'dual-chatbot'), function() {
            echo '<p>' . esc_html__('Passe Aussehen und Verhalten des Popup-Buttons sowie des Chatfensters an.', 'dual-chatbot') . '</p>';
            echo '<p class="description">' . esc_html__('Hinweis: Leere Farbfelder nutzen automatisch die im Stylesheet definierten Standardfarben. „Auf Standard zurücksetzen“ löscht alle Overrides.', 'dual-chatbot') . '</p>';
        }, 'dual-chatbot-settings-appearance');
        // Bubble shape select field
        add_settings_field(self::OPTION_BUBBLE_SHAPE, __('Form der Chat-Blase', 'dual-chatbot'), function() {
            $value = get_option(self::OPTION_BUBBLE_SHAPE, 'circle');
            echo '<select name="' . esc_attr(self::OPTION_BUBBLE_SHAPE) . '">';
            $options = ['circle' => __('Rund', 'dual-chatbot'), 'square' => __('Quadratisch', 'dual-chatbot')];
            foreach ($options as $val => $label) {
                echo '<option value="' . esc_attr($val) . '"' . selected($value, $val, false) . '>' . esc_html($label) . '</option>';
            }
            echo '</select>';
        }, 'dual-chatbot-settings-appearance', 'dual_chatbot_appearance');
        // Bubble size select field
        add_settings_field(self::OPTION_BUBBLE_SIZE, __('Größe der Chat-Blase', 'dual-chatbot'), function() {
            $value = get_option(self::OPTION_BUBBLE_SIZE, 'medium');
            echo '<select name="' . esc_attr(self::OPTION_BUBBLE_SIZE) . '">';
            $options = ['small' => __('Klein', 'dual-chatbot'), 'medium' => __('Mittel', 'dual-chatbot'), 'large' => __('Groß', 'dual-chatbot')];
            foreach ($options as $val => $label) {
                echo '<option value="' . esc_attr($val) . '"' . selected($value, $val, false) . '>' . esc_html($label) . '</option>';
            }
            echo '</select>';
            echo '<p class="description">' . esc_html__('Bestimmt Breite und Höhe des Chat-Icons.', 'dual-chatbot') . '</p>';
        }, 'dual-chatbot-settings-appearance', 'dual_chatbot_appearance');
        // Bubble position select field
        add_settings_field(self::OPTION_BUBBLE_POSITION, __('Position des Chat-Icons', 'dual-chatbot'), function() {
            $value = get_option(self::OPTION_BUBBLE_POSITION, 'right');
            echo '<select name="' . esc_attr(self::OPTION_BUBBLE_POSITION) . '">';
            $options = ['right' => __('Unten rechts', 'dual-chatbot'), 'left' => __('Unten links', 'dual-chatbot')];
            foreach ($options as $val => $label) {
                echo '<option value="' . esc_attr($val) . '"' . selected($value, $val, false) . '>' . esc_html($label) . '</option>';
            }
            echo '</select>';
            echo '<p class="description">' . esc_html__('Bestimmt, ob das Icon rechts oder links unten angezeigt wird.', 'dual-chatbot') . '</p>';
        }, 'dual-chatbot-settings-appearance', 'dual_chatbot_appearance');
        // Bubble and icon colors removed; rely on theme and CSS defaults
        // Popup width field
        add_settings_field(self::OPTION_POPUP_WIDTH, __('Popup-Breite (px)', 'dual-chatbot'), function() {
            $value = get_option(self::OPTION_POPUP_WIDTH, 380);
            echo '<input type="number" class="small-text" name="' . esc_attr(self::OPTION_POPUP_WIDTH) . '" value="' . esc_attr($value) . '" min="200" max="800" />';
            echo '<p class="description">' . esc_html__('Breite des FAQ-Popups in Pixel.', 'dual-chatbot') . '</p>';
        }, 'dual-chatbot-settings-appearance', 'dual_chatbot_appearance');
        // Popup height field
        add_settings_field(self::OPTION_POPUP_HEIGHT, __('Popup-Höhe (px)', 'dual-chatbot'), function() {
            $value = get_option(self::OPTION_POPUP_HEIGHT, 500);
            echo '<input type="number" class="small-text" name="' . esc_attr(self::OPTION_POPUP_HEIGHT) . '" value="' . esc_attr($value) . '" min="200" max="1000" />';
            echo '<p class="description">' . esc_html__('Höhe des FAQ-Popups in Pixel.', 'dual-chatbot') . '</p>';
        }, 'dual-chatbot-settings-appearance', 'dual_chatbot_appearance');
        // Greeting message field
        add_settings_field(self::OPTION_POPUP_GREETING, __('Begrüßungsnachricht', 'dual-chatbot'), function() {
            $value = get_option(self::OPTION_POPUP_GREETING, self::DEFAULTS[self::OPTION_POPUP_GREETING] ?? '');
            echo '<textarea name="' . esc_attr(self::OPTION_POPUP_GREETING) . '" rows="3" cols="50" class="large-text">' . esc_textarea($value) . '</textarea>';
            echo '<p class="description">' . esc_html__('Text, der automatisch als Begrüßung angezeigt wird, wenn der FAQ-Chat geöffnet wird.', 'dual-chatbot') . '</p>';
        }, 'dual-chatbot-settings-appearance', 'dual_chatbot_appearance');

        // Custom bubble icon URL field
        add_settings_field(self::OPTION_BUBBLE_ICON_URL, __('Eigenes Icon (URL)', 'dual-chatbot'), function() {
            $value = get_option(self::OPTION_BUBBLE_ICON_URL, '');
            echo '<input type="text" class="regular-text" name="' . esc_attr(self::OPTION_BUBBLE_ICON_URL) . '" value="' . esc_attr($value) . '" placeholder="https://" />';
            echo '<p class="description">' . esc_html__('Optional: URL zu einem eigenen Icon. Wenn angegeben, ersetzt das Bild den Standard-Sprechblasen-Button.', 'dual-chatbot') . '</p>';
        }, 'dual-chatbot-settings-appearance', 'dual_chatbot_appearance');
        // Removed: button text color fields (simplified UI)
        // Input placeholder text
        add_settings_field(self::OPTION_INPUT_PLACEHOLDER, __('Eingabe-Platzhalter', 'dual-chatbot'), function() {
            $value = get_option(self::OPTION_INPUT_PLACEHOLDER, __('Nachricht…', 'dual-chatbot'));
            echo '<input type="text" class="regular-text" name="' . esc_attr(self::OPTION_INPUT_PLACEHOLDER) . '" value="' . esc_attr($value) . '" />';
            echo '<p class="description">' . esc_html__('Platzhaltertext im Eingabefeld des Chatfensters.', 'dual-chatbot') . '</p>';
        }, 'dual-chatbot-settings-appearance', 'dual_chatbot_appearance');

        // Removed: popup header colors (use theme/primary)

        // Removed: popup body/background/border controls (simplified UI)

        // Minimized chat label
        add_settings_field(self::OPTION_MINIMIZED_LABEL, __('Titel im minimierten Chat', 'dual-chatbot'), function() {
            $value = get_option(self::OPTION_MINIMIZED_LABEL, self::DEFAULTS[self::OPTION_MINIMIZED_LABEL] ?? __('Chat', 'dual-chatbot'));
            echo '<input type="text" class="regular-text" name="' . esc_attr(self::OPTION_MINIMIZED_LABEL) . '" value="' . esc_attr($value) . '" />';
            echo '<p class="description">' . esc_html__('Beschriftung des minimierten Chatfensters.', 'dual-chatbot') . '</p>';
        }, 'dual-chatbot-settings-appearance', 'dual_chatbot_appearance');

        // Reset colors to defaults (stylesheet tokens)
        add_settings_field('dual_chatbot_reset_colors', __('Farben auf Standard zurücksetzen', 'dual-chatbot'), [$this, 'render_reset_colors_button'], 'dual-chatbot-settings-appearance', 'dual_chatbot_appearance');
    }

    /**
     * Render the settings page HTML.
     */
    public function render_settings_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $current_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'general';
        $tabs = [
            'general'   => __('Allgemein', 'dual-chatbot'),
            'design'    => __('Design', 'dual-chatbot'),
            'api'       => __('API', 'dual-chatbot'),
            'knowledge' => __('Wissensdatenbank', 'dual-chatbot'),
            'tools'     => __('Werkzeuge', 'dual-chatbot'),
        ];
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Chatbot Einstellungen', 'dual-chatbot') . '</h1>';
        // Tab navigation
        echo '<h2 class="nav-tab-wrapper">';
        foreach ($tabs as $key => $label) {
            $class = ($current_tab === $key) ? ' nav-tab-active' : '';
            $url = admin_url('options-general.php?page=dual-chatbot-settings&tab=' . $key);
            echo '<a href="' . esc_url($url) . '" class="nav-tab' . esc_attr($class) . '">' . esc_html($label) . '</a>';
        }
        echo '</h2>';
        // Single form containing all sections so values persist across tab changes.
        echo '<form method="post" action="options.php" enctype="multipart/form-data">';
        settings_fields('dual_chatbot_options');
        foreach ($tabs as $key => $label) {
            $style = ($current_tab === $key) ? '' : 'style="display:none;"';
            echo '<div class="dual-chatbot-tab-content" id="dual-chatbot-tab-' . esc_attr($key) . '" ' . $style . '>'; 
            do_settings_sections('dual-chatbot-settings-' . $key);
            echo '</div>';
        }
        submit_button();
        echo '</form>';
        echo '</div>';
        // JavaScript to toggle tab content without reloading all settings values
        ?>
        <script type="text/javascript">
        (function($){
            $(function(){
                $('.nav-tab-wrapper a').on('click', function(e){
                    // allow navigation reload (the query param is preserved) but still show/hide sections to preserve data
                    var href = $(this).attr('href');
                    // Show/hide sections only; not preventing default as the page reload will still occur
                    var tab = href.split('tab=')[1];
                    $('.nav-tab-wrapper a').removeClass('nav-tab-active');
                    $(this).addClass('nav-tab-active');
                    $('.dual-chatbot-tab-content').hide();
                    $('#dual-chatbot-tab-' + tab).show();
                });
                // Reset colors button handler
                $('#dual-chatbot-reset-colors-button').on('click', function(){
                    if(!confirm('<?php echo esc_js(__('Alle Farben auf Standard (Stylesheet) zurücksetzen?', 'dual-chatbot')); ?>')){ return; }
                    var $btn = $(this);
                    $btn.prop('disabled', true);
                    $.post(ajaxurl, {
                        action: 'dual_chatbot_reset_colors',
                        _wpnonce: '<?php echo wp_create_nonce('dual_chatbot_reset_colors'); ?>'
                    }).done(function(resp){
                        alert(resp && resp.data && resp.data.message ? resp.data.message : '<?php echo esc_js(__('Farben zurückgesetzt.', 'dual-chatbot')); ?>');
                        location.reload();
                    }).fail(function(){
                        alert('<?php echo esc_js(__('Fehler beim Zurücksetzen.', 'dual-chatbot')); ?>');
                    }).always(function(){
                        $btn.prop('disabled', false);
                    });
                });
            });
        })(jQuery);
        </script>
        <?php
    }

    /**
     * Render the "Reset Colors" button (appearance tab).
     */
    public function render_reset_colors_button(): void {
        echo '<button type="button" class="button" id="dual-chatbot-reset-colors-button">' . esc_html__('Auf Standard zurücksetzen', 'dual-chatbot') . '</button>';
        echo '<p class="description">' . esc_html__('Setzt alle Farboptionen zurück, sodass die im Stylesheet definierten Standardwerte wieder greifen.', 'dual-chatbot') . '</p>';
    }

    /**
     * Add a theme class to the body to force light/dark or auto behavior via CSS.
     */
    public function add_theme_body_class(array $classes): array {
        $theme = get_option(self::OPTION_THEME, self::DEFAULTS[self::OPTION_THEME] ?? 'auto');
        $classes[] = 'dual-chatbot-theme-' . (in_array($theme, ['light','dark'], true) ? $theme : 'auto');
        return $classes;
    }

    /**
     * One-time migration to new design options. If the new keys are unset, seed them.
     */
    public function maybe_migrate_design_options(): void {
        $has_theme   = get_option(self::OPTION_THEME, null);
        $has_primary = get_option(self::OPTION_PRIMARY_COLOR, null);
        $has_icon    = get_option(self::OPTION_ICON_COLOR, null);
        if ($has_theme !== null && $has_primary !== null && $has_icon !== null) {
            return; // Already migrated/initialized
        }
        $primary = is_string($has_primary) && $has_primary !== '' ? $has_primary : (self::DEFAULTS[self::OPTION_PRIMARY_COLOR] ?? '#4e8cff');
        update_option(self::OPTION_THEME, self::DEFAULTS[self::OPTION_THEME] ?? 'auto');
        update_option(self::OPTION_PRIMARY_COLOR, $primary);
        update_option(self::OPTION_ICON_COLOR, self::DEFAULTS[self::OPTION_ICON_COLOR] ?? '');
    }

    /**
     * Render the file upload field for knowledge documents.  Supports multiple
     * files and stores them in the WordPress uploads directory.  Files are not
     * automatically processed until the admin clicks "Wissen neu indizieren".
     */
    public function render_file_upload_field(): void {
        echo '<input type="file" name="dual_chatbot_documents[]" multiple="multiple" accept=".txt,.pdf" />';
        // Show existing uploaded files list (if any).
        $uploads = get_option('dual_chatbot_uploaded_files', []);
        if (!empty($uploads)) {
            echo '<ul class="dual-chatbot-upload-list">';
            foreach ($uploads as $index => $file) {
                $basename = basename($file);
                echo '<li data-index="' . esc_attr($index) . '">' . esc_html($basename) . ' <a href="#" class="dual-chatbot-delete-file" data-index="' . esc_attr($index) . '">' . esc_html__('Entfernen', 'dual-chatbot') . '</a></li>';
            }
            echo '</ul>';
            ?>
            <script type="text/javascript">
            (function($){
                $(function(){
                    $('.dual-chatbot-upload-list').on('click', '.dual-chatbot-delete-file', function(e){
                        e.preventDefault();
                        var index = $(this).data('index');
                        if (confirm('<?php echo esc_js(__('Datei wirklich löschen?', 'dual-chatbot')); ?>')) {
                            $.post(ajaxurl, {
                                action: 'dual_chatbot_delete_file',
                                _wpnonce: '<?php echo wp_create_nonce('dual_chatbot_delete_file'); ?>',
                                index: index
                            }, function(response){
                                if (response.success) {
                                    $('li[data-index="' + index + '"]').remove();
                                } else {
                                    alert(response.data.message);
                                }
                            });
                        }
                    });
                });
            })(jQuery);
            </script>
            <?php
        }
    }

    /**
     * Render the "Reindex Knowledge" button.  When clicked it triggers
     * knowledge indexing via a custom action.  We use a nonce to secure the
     * request.
     */
    public function render_index_button(): void {
        echo '<button type="button" class="button button-secondary" id="dual-chatbot-reindex-button">' . esc_html__('Wissen neu indizieren', 'dual-chatbot') . '</button>';
        // Inline script to handle the index button click via AJAX.
        ?>
        <script type="text/javascript">
        (function($){
            $(function(){
                $('#dual-chatbot-reindex-button').on('click', function(){
                    if(confirm('<?php echo esc_js(__('Möchtest Du die Wissensdatenbank neu indizieren? Dies kann einige Minuten dauern.', 'dual-chatbot')); ?>')){
                        var $btn = $(this);
                        $btn.prop('disabled', true).text('<?php echo esc_js(__('Indizierung läuft…', 'dual-chatbot')); ?>');
                        $.post(ajaxurl, {
                            action: 'dual_chatbot_reindex',
                            _wpnonce: '<?php echo wp_create_nonce('dual_chatbot_reindex'); ?>'
                        }, function(response){
                            alert(response.data.message);
                            $btn.prop('disabled', false).text('<?php echo esc_js(__('Wissen neu indizieren', 'dual-chatbot')); ?>');
                        }).fail(function(xhr){
                            alert(xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data.message : '<?php echo esc_js(__('Ein Fehler ist aufgetreten.', 'dual-chatbot')); ?>');
                            $btn.prop('disabled', false).text('<?php echo esc_js(__('Wissen neu indizieren', 'dual-chatbot')); ?>');
                        });
                    }
                });
            });
        })(jQuery);
        </script>
        <?php
    }

    /**
     * Render the "Delete chat logs" button.  This triggers deletion of all
     * chat history in the custom table.  Secured via nonce.
     */
    public function render_cleanup_button(): void {
        echo '<button type="button" class="button button-danger" id="dual-chatbot-cleanup-button">' . esc_html__('Gesprächsverläufe löschen', 'dual-chatbot') . '</button>';
        ?>
        <script type="text/javascript">
        (function($){
            $(function(){
                $('#dual-chatbot-cleanup-button').on('click', function(){
                    if(confirm('<?php echo esc_js(__('Alle Gesprächsverläufe werden gelöscht. Fortfahren?', 'dual-chatbot')); ?>')){
                        var $btn = $(this);
                        $btn.prop('disabled', true).text('<?php echo esc_js(__('Lösche…', 'dual-chatbot')); ?>');
                        $.post(ajaxurl, {
                            action: 'dual_chatbot_cleanup',
                            _wpnonce: '<?php echo wp_create_nonce('dual_chatbot_cleanup'); ?>'
                        }, function(response){
                            alert(response.data.message);
                            $btn.prop('disabled', false).text('<?php echo esc_js(__('Gesprächsverläufe löschen', 'dual-chatbot')); ?>');
                        }).fail(function(xhr){
                            alert(xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data.message : '<?php echo esc_js(__('Ein Fehler ist aufgetreten.', 'dual-chatbot')); ?>');
                            $btn.prop('disabled', false).text('<?php echo esc_js(__('Gesprächsverläufe löschen', 'dual-chatbot')); ?>');
                        });
                    }
                });
            });
        })(jQuery);
        </script>
        <?php
    }

    /**
     * Render the "Clear Cache" button.  This triggers deletion of all cached
     * knowledge entries and embeddings.  Secured via nonce.
     */
    public function render_clear_cache_button(): void {
        echo '<button type="button" class="button" id="dual-chatbot-clear-cache-button">' . esc_html__('Cache der Wissensdatenbank leeren', 'dual-chatbot') . '</button>';
        ?>
        <script type="text/javascript">
        (function($){
            $(function(){
                $('#dual-chatbot-clear-cache-button').on('click', function(){
                    if(confirm('<?php echo esc_js(__('Bist Du sicher, dass Du alle Wissensdatenbank-Daten löschen möchtest?', 'dual-chatbot')); ?>')){
                        var $btn = $(this);
                        $btn.prop('disabled', true).text('<?php echo esc_js(__('Leere Cache…', 'dual-chatbot')); ?>');
                        $.post(ajaxurl, {
                            action: 'dual_chatbot_clear_cache',
                            _wpnonce: '<?php echo wp_create_nonce('dual_chatbot_clear_cache'); ?>'
                        }, function(response){
                            alert(response.data.message);
                            $btn.prop('disabled', false).text('<?php echo esc_js(__('Cache der Wissensdatenbank leeren', 'dual-chatbot')); ?>');
                        }).fail(function(xhr){
                            alert(xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data.message : '<?php echo esc_js(__('Ein Fehler ist aufgetreten.', 'dual-chatbot')); ?>');
                            $btn.prop('disabled', false).text('<?php echo esc_js(__('Cache der Wissensdatenbank leeren', 'dual-chatbot')); ?>');
                        });
                    }
                });            });
        })(jQuery);
        </script>
        <?php
    }

    /**
     * Render the "Reset Settings" button.  Resets plugin settings to defaults.
     */
    public function render_reset_settings_button(): void {
        echo '<button type="button" class="button" id="dual-chatbot-reset-settings-button">' . esc_html__('Alle Einstellungen zurücksetzen', 'dual-chatbot') . '</button>';
        ?>
        <script type="text/javascript">
        (function($){
            $(function(){
                $('#dual-chatbot-reset-settings-button').on('click', function(){
                    if(confirm('<?php echo esc_js(__('Alle Einstellungen (aber nicht die Daten) zurücksetzen?', 'dual-chatbot')); ?>')){
                        var $btn = $(this);
                        $btn.prop('disabled', true).text('<?php echo esc_js(__('Setze zurück…', 'dual-chatbot')); ?>');
                        $.post(ajaxurl, {
                            action: 'dual_chatbot_reset_settings',
                            _wpnonce: '<?php echo wp_create_nonce('dual_chatbot_reset_settings'); ?>'
                        }, function(response){
                            alert(response.data.message);
                            $btn.prop('disabled', false).text('<?php echo esc_js(__('Alle Einstellungen zurücksetzen', 'dual-chatbot')); ?>');
                            if (response.success) {
                                // Reload to reflect defaults
                                location.reload();
                            }
                        }).fail(function(xhr){
                            alert(xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data.message : '<?php echo esc_js(__('Ein Fehler ist aufgetreten.', 'dual-chatbot')); ?>');
                            $btn.prop('disabled', false).text('<?php echo esc_js(__('Alle Einstellungen zurücksetzen', 'dual-chatbot')); ?>');
                        });
                    }
                });
            });
        })(jQuery);
        </script>
        <?php
    }
}

// Initialise the plugin.
new Dual_Chatbot_Plugin();

/**
 * Handle AJAX actions defined in the settings page.  These functions reside
 * outside of the class because WordPress hooks expect global functions for
 * AJAX actions.  Each handler validates a nonce to ensure the request is
 * legitimate, then performs the requested operation.
 */
add_action('wp_ajax_dual_chatbot_reindex', 'dual_chatbot_handle_reindex');
function dual_chatbot_handle_reindex(): void {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Keine Berechtigung.', 'dual-chatbot')], 403);
    }
    if (!check_ajax_referer('dual_chatbot_reindex', '_wpnonce', false)) {
        wp_send_json_error(['message' => __('Ungültige Anfrage.', 'dual-chatbot')], 400);
    }
    // Process uploaded files and index knowledge.
    require_once __DIR__ . '/includes/knowledge-indexer.php';
    try {
        $indexer = new Dual_Chatbot_Knowledge_Indexer();
        $count = $indexer->process_uploaded_files();
        wp_send_json_success(['message' => sprintf(__('Indizierung abgeschlossen. %d Abschnitte verarbeitet.', 'dual-chatbot'), $count)]);
    } catch (Exception $e) {
        wp_send_json_error(['message' => $e->getMessage()], 500);
    }
}

add_action('wp_ajax_dual_chatbot_cleanup', 'dual_chatbot_handle_cleanup');
function dual_chatbot_handle_cleanup(): void {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Keine Berechtigung.', 'dual-chatbot')], 403);
    }
    if (!check_ajax_referer('dual_chatbot_cleanup', '_wpnonce', false)) {
        wp_send_json_error(['message' => __('Ungültige Anfrage.', 'dual-chatbot')], 400);
    }
    global $wpdb;
    $history_table = $wpdb->prefix . 'chatbot_history';
    $deleted = $wpdb->query("TRUNCATE TABLE $history_table");
    if ($deleted === false) {
        wp_send_json_error(['message' => __('Datenbankfehler beim Löschen.', 'dual-chatbot')], 500);
    } else {
        wp_send_json_success(['message' => __('Alle Gesprächsverläufe wurden gelöscht.', 'dual-chatbot')]);
    }
}

// Delete a single uploaded file from the knowledge base list and DB.
add_action('wp_ajax_dual_chatbot_delete_file', 'dual_chatbot_handle_delete_file');
function dual_chatbot_handle_delete_file(): void {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Keine Berechtigung.', 'dual-chatbot')], 403);
    }
    if (!check_ajax_referer('dual_chatbot_delete_file', '_wpnonce', false)) {
        wp_send_json_error(['message' => __('Ungültige Anfrage.', 'dual-chatbot')], 400);
    }
    $index = isset($_POST['index']) ? intval($_POST['index']) : -1;
    $files = get_option('dual_chatbot_uploaded_files', []);
    if ($index < 0 || !isset($files[$index])) {
        wp_send_json_error(['message' => __('Datei nicht gefunden.', 'dual-chatbot')]);
    }
    $file_path = $files[$index];
    // Remove from list.
    unset($files[$index]);
    $files = array_values($files);
    update_option('dual_chatbot_uploaded_files', $files);
    // Delete DB entries for this file.
    global $wpdb;
    $knowledge_table = $wpdb->prefix . 'chatbot_knowledge';
    $wpdb->delete($knowledge_table, ['file_name' => basename($file_path)], ['%s']);
    // Optionally delete physical file.
    if (file_exists($file_path)) {
        @unlink($file_path);
    }
    wp_send_json_success(['message' => __('Datei gelöscht.', 'dual-chatbot')]);
}

/**
 * Write a log message to the debug file if debug logging is enabled.  Log
 * entries are timestamped and appended to wp-content/uploads/chatbot-debug.log.
 *
 * @param string $message The message to log.
 */
function dual_chatbot_log(string $message): void {
    $enabled = get_option(Dual_Chatbot_Plugin::OPTION_DEBUG_LOG, '0');
    if ($enabled !== '1') {
        return;
    }
    $upload = wp_upload_dir();
    $file = trailingslashit($upload['basedir']) . 'chatbot-debug.log';
    $timestamp = date('c');
    // Prepend microtime for precise ordering.
    $line = '[' . $timestamp . '] ' . $message . PHP_EOL;
    // Use file_put_contents with FILE_APPEND and locking to avoid race conditions.
    @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
}

// Clear knowledge base cache (embeddings & file list).
add_action('wp_ajax_dual_chatbot_clear_cache', 'dual_chatbot_handle_clear_cache');
function dual_chatbot_handle_clear_cache(): void {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Keine Berechtigung.', 'dual-chatbot')], 403);
    }
    if (!check_ajax_referer('dual_chatbot_clear_cache', '_wpnonce', false)) {
        wp_send_json_error(['message' => __('Ungültige Anfrage.', 'dual-chatbot')], 400);
    }
    global $wpdb;
    $knowledge_table = $wpdb->prefix . 'chatbot_knowledge';
    $wpdb->query("TRUNCATE TABLE $knowledge_table");
    // Reset uploaded file list.
    update_option('dual_chatbot_uploaded_files', []);
    wp_send_json_success(['message' => __('Die Wissensdatenbank wurde geleert.', 'dual-chatbot')]);
}

// Reset plugin settings to defaults.
add_action('wp_ajax_dual_chatbot_reset_settings', 'dual_chatbot_handle_reset_settings');
function dual_chatbot_handle_reset_settings(): void {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Keine Berechtigung.', 'dual-chatbot')], 403);
    }
    if (!check_ajax_referer('dual_chatbot_reset_settings', '_wpnonce', false)) {
        wp_send_json_error(['message' => __('Ungültige Anfrage.', 'dual-chatbot')], 400);
    }
    // Colors → set explicit Plugin-Defaults
    $color_defaults = Dual_Chatbot_Plugin::get_default_color_options();
    foreach ($color_defaults as $opt => $val) {
        update_option($opt, $val);
    }
    // Ensure minimal design keys are set
    update_option(Dual_Chatbot_Plugin::OPTION_THEME,         Dual_Chatbot_Plugin::DEFAULTS[Dual_Chatbot_Plugin::OPTION_THEME]);
    update_option(Dual_Chatbot_Plugin::OPTION_ICON_COLOR,    Dual_Chatbot_Plugin::DEFAULTS[Dual_Chatbot_Plugin::OPTION_ICON_COLOR]);
    // Non-token color options → remove
    $extra_color_opts = [
        Dual_Chatbot_Plugin::OPTION_SEND_BTN_TEXT_COLOR,
        Dual_Chatbot_Plugin::OPTION_NEWCHAT_BTN_TEXT_COLOR,
        Dual_Chatbot_Plugin::OPTION_BUBBLE_COLOR,
        Dual_Chatbot_Plugin::OPTION_POPUP_HEADER_BG_COLOR,
        Dual_Chatbot_Plugin::OPTION_POPUP_HEADER_TEXT_COLOR,
        Dual_Chatbot_Plugin::OPTION_POPUP_BODY_BG_COLOR,
        Dual_Chatbot_Plugin::OPTION_POPUP_BORDER_COLOR,
        Dual_Chatbot_Plugin::OPTION_SEND_ICON_COLOR,
        Dual_Chatbot_Plugin::OPTION_MIC_ICON_COLOR,
    ];
    foreach ($extra_color_opts as $opt) { delete_option($opt); }

    // Booleans & misc: clear to defaults
    $other_opts = [
        Dual_Chatbot_Plugin::OPTION_ENABLED,
        Dual_Chatbot_Plugin::OPTION_FAQ_ENABLED,
        Dual_Chatbot_Plugin::OPTION_ADVISOR_ENABLED,
        Dual_Chatbot_Plugin::OPTION_OPENAI_API_KEY,
        Dual_Chatbot_Plugin::OPTION_WHISPER_API_KEY,
        Dual_Chatbot_Plugin::OPTION_MYAPLEFY_API_KEY,
        Dual_Chatbot_Plugin::OPTION_MYAPLEFY_ENDPOINT,
        Dual_Chatbot_Plugin::OPTION_SIMULATE_MEMBERSHIP,
        Dual_Chatbot_Plugin::OPTION_DEBUG_LOG,Dual_Chatbot_Plugin::OPTION_PRECHAT_ENABLED,
        Dual_Chatbot_Plugin::OPTION_PRECHAT_REQUIRE_NAME,
        Dual_Chatbot_Plugin::OPTION_PRECHAT_REQUIRE_EMAIL,
        Dual_Chatbot_Plugin::OPTION_WEB_SEARCH_ENABLED,
        Dual_Chatbot_Plugin::OPTION_WEB_SEARCH_RESULTS,
        Dual_Chatbot_Plugin::OPTION_BUBBLE_SHAPE,
        Dual_Chatbot_Plugin::OPTION_BUBBLE_SIZE,
        Dual_Chatbot_Plugin::OPTION_BUBBLE_POSITION,
        Dual_Chatbot_Plugin::OPTION_BUBBLE_ICON_URL,
        Dual_Chatbot_Plugin::OPTION_POPUP_WIDTH,
        Dual_Chatbot_Plugin::OPTION_POPUP_HEIGHT,
        Dual_Chatbot_Plugin::OPTION_POPUP_GREETING,
        Dual_Chatbot_Plugin::OPTION_INPUT_PLACEHOLDER,
        Dual_Chatbot_Plugin::OPTION_MINIMIZED_LABEL,
        Dual_Chatbot_Plugin::OPTION_FAQ_MODEL,
        Dual_Chatbot_Plugin::OPTION_ADVISOR_MODEL,
        Dual_Chatbot_Plugin::OPTION_WEB_SEARCH_API_KEY,
        Dual_Chatbot_Plugin::OPTION_WEB_SEARCH_API_ENDPOINT,
    ];
    foreach ($other_opts as $opt) { delete_option($opt); }

    wp_send_json_success(['message' => __('Alle Einstellungen wurden auf Plugin-Defaults gesetzt.', 'dual-chatbot')]);
}
// Reset only color-related options to stylesheet defaults (by deleting overrides)
add_action('wp_ajax_dual_chatbot_reset_colors', 'dual_chatbot_handle_reset_colors');
function dual_chatbot_handle_reset_colors(): void {
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => __('Keine Berechtigung.', 'dual-chatbot')], 403);
    }
    if (!check_ajax_referer('dual_chatbot_reset_colors', '_wpnonce', false)) {
        wp_send_json_error(['message' => __('Ungültige Anfrage.', 'dual-chatbot')], 400);
    }
    // Reset minimal design keys (theme, primary, icon) and return early
    update_option(Dual_Chatbot_Plugin::OPTION_THEME,         Dual_Chatbot_Plugin::DEFAULTS[Dual_Chatbot_Plugin::OPTION_THEME]);
    update_option(Dual_Chatbot_Plugin::OPTION_PRIMARY_COLOR, Dual_Chatbot_Plugin::DEFAULTS[Dual_Chatbot_Plugin::OPTION_PRIMARY_COLOR]);
    update_option(Dual_Chatbot_Plugin::OPTION_ICON_COLOR,    Dual_Chatbot_Plugin::DEFAULTS[Dual_Chatbot_Plugin::OPTION_ICON_COLOR]);
    wp_send_json_success(['message' => __('Farben wurden auf Standard zurückgesetzt.', 'dual-chatbot')]);
    return;
    /*
    // Remove non-token color overrides so they fall back to theme/CSS behavior
    $all_colors = [
        Dual_Chatbot_Plugin::OPTION_PRIMARY_COLOR,
        Dual_Chatbot_Plugin::OPTION_SECONDARY_COLOR,
        Dual_Chatbot_Plugin::OPTION_TEXT_COLOR,
        Dual_Chatbot_Plugin::OPTION_BOT_BG_COLOR,
        Dual_Chatbot_Plugin::OPTION_USER_BG_COLOR,
        Dual_Chatbot_Plugin::OPTION_SIDEBAR_BG_COLOR,
        Dual_Chatbot_Plugin::OPTION_SIDEBAR_TEXT_COLOR,
        Dual_Chatbot_Plugin::OPTION_MAIN_BG_COLOR,
        Dual_Chatbot_Plugin::OPTION_SEND_BTN_COLOR,
        Dual_Chatbot_Plugin::OPTION_NEWCHAT_BTN_COLOR,
        Dual_Chatbot_Plugin::OPTION_SEND_BTN_TEXT_COLOR,
        Dual_Chatbot_Plugin::OPTION_NEWCHAT_BTN_TEXT_COLOR,
        Dual_Chatbot_Plugin::OPTION_BUBBLE_COLOR,
        Dual_Chatbot_Plugin::OPTION_POPUP_HEADER_BG_COLOR,
        Dual_Chatbot_Plugin::OPTION_POPUP_HEADER_TEXT_COLOR,
        Dual_Chatbot_Plugin::OPTION_POPUP_BODY_BG_COLOR,
        Dual_Chatbot_Plugin::OPTION_POPUP_BORDER_COLOR,
        Dual_Chatbot_Plugin::OPTION_SEND_ICON_COLOR,
        Dual_Chatbot_Plugin::OPTION_MIC_ICON_COLOR,
    ];
    foreach ($all_colors as $opt) {
        if (!array_key_exists($opt, $defaults)) {
            delete_option($opt);
        }
    }
    wp_send_json_success(['message' => __('Farben wurden exakt auf die Plugin-Defaults gesetzt.', 'dual-chatbot')]);
    */
}
// === Whisper Speech-to-Text Endpoint (für OpenAI Whisper v1 Integration) ===
// Dummy/Beispiel: Antwortet immer mit transcript "Speech-to-text-Test!"

add_action('rest_api_init', function () {
    register_rest_route('chatbot/v1', '/whisper', [
        'methods' => 'POST',
        'callback' => 'dual_chatbot_whisper_endpoint',
        'permission_callback' => '__return_true', // Für Produktiv ggf. absichern!
    ]);
});
function dual_chatbot_whisper_endpoint(WP_REST_Request $request) {
    // 1. Check & get file from request
    $files = $request->get_file_params();
    if (empty($files['audio']) || empty($files['audio']['tmp_name'])) {
        return new WP_REST_Response(['error' => 'Keine Audiodatei empfangen.'], 400);
    }
    $audio_path = $files['audio']['tmp_name'];
    $audio_name = $files['audio']['name'];
    $audio_type = $files['audio']['type'] ?: 'audio/webm';

    // 2. Lade OpenAI Whisper API-Key aus WP-Option
    $openai_api_key = get_option(Dual_Chatbot_Plugin::OPTION_WHISPER_API_KEY, '');
    if (!$openai_api_key) {
        return new WP_REST_Response(['error' => 'Kein OpenAI Whisper API-Key gesetzt.'], 400);
    }

    // 3. Baue Request für Whisper v1 API (https://api.openai.com/v1/audio/transcriptions)
    $endpoint = "https://api.openai.com/v1/audio/transcriptions";
    $cfile = new CURLFile($audio_path, $audio_type, $audio_name);

    $post_fields = [
        'file' => $cfile,
        'model' => 'whisper-1',   // Modellname: whisper-1 für OpenAI Whisper v1
        'response_format' => 'json',
        // 'language' => 'de', // optional für deutsche Sprache
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $openai_api_key,
    ]);
    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        return new WP_REST_Response(['error' => 'Fehler bei Whisper-Anfrage: ' . $err], 500);
    }
    $data = json_decode($response, true);

    if (!isset($data['text'])) {
        return new WP_REST_Response(['error' => 'Transkript nicht erhalten: ' . $response], 500);
    }
    // Rückgabe wie bisher (Frontend erwartet: { transcript: "..." })
    return new WP_REST_Response(['transcript' => $data['text']], 200);
}




/**
 * Resolve a robust profile URL for a user with sensible fallbacks.
 *
 * Order:
 * 1) If not logged in: return login URL with redirect_to current page
 * 2) Ultimate Member (if active): user profile URL
 * 3) BuddyPress/BuddyBoss (if active): user domain
 * 4) Account page option (Page ID): its permalink (language-aware)
 * 5) Logged-in fallback: WP admin profile.php
 * 6) Final fallback: site home
 *
 * Result is filtered via 'myplugin_profile_url'.
 */
function myplugin_get_profile_url(int $user_id = 0): string {
    // Build current URL for redirect target.
    $scheme = is_ssl() ? 'https://' : 'http://';
    $host   = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : parse_url(home_url('/'), PHP_URL_HOST);
    $uri    = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
    $current_url = esc_url_raw($scheme . $host . $uri);

    if ($user_id <= 0) {
        $user_id = get_current_user_id();
    }

    if (!is_user_logged_in() || !$user_id || !user_can($user_id, 'read')) {
        $login = wp_login_url($current_url);
        return apply_filters('myplugin_profile_url', $login, 0);
    }

    // 2) Ultimate Member
    if (function_exists('um_user_profile_url')) {
        $um_url = um_user_profile_url($user_id);
        if (!empty($um_url)) {
            return apply_filters('myplugin_profile_url', esc_url_raw($um_url), $user_id);
        }
    }

    // 3) BuddyPress / BuddyBoss
    if (function_exists('bp_core_get_user_domain')) {
        $bp_url = bp_core_get_user_domain($user_id);
        if (!empty($bp_url)) {
            return apply_filters('myplugin_profile_url', esc_url_raw($bp_url), $user_id);
        }
    }

    // 4) Custom account page (Page ID)
    $page_id = (int) get_option(Dual_Chatbot_Plugin::OPTION_ACCOUNT_PAGE_ID, 0);
    if ($page_id > 0) {
        $permalink = get_permalink($page_id);
        if ($permalink) {
            return apply_filters('myplugin_profile_url', esc_url_raw($permalink), $user_id);
        }
    }

    // 5) Admin profile for logged-in users
    $admin_profile = admin_url('profile.php');
    if ($admin_profile) {
        return apply_filters('myplugin_profile_url', esc_url_raw($admin_profile), $user_id);
    }

    // 6) Final fallback
    return apply_filters('myplugin_profile_url', esc_url_raw(home_url('/')), $user_id);
}
