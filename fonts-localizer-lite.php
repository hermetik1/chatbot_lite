<?php
declare(strict_types=1);

/*
Plugin Name: Fonts Localizer Lite
Description: Localize Google Fonts by downloading WOFF2 files and serving them from your server. GDPR-friendly, no third-party requests after localization.
Version: 0.1.0
Author: Your Name
Text Domain: fonts-localizer-lite
Requires PHP: 8.2
*/

// Security check
if (!defined('ABSPATH')) {
    exit;
}

define('FLLITE_VERSION', '0.1.0');
define('FLLITE_FILE', __FILE__);
define('FLLITE_DIR', plugin_dir_path(__FILE__));
define('FLLITE_URL', plugin_dir_url(__FILE__));
define('FLLITE_NS', 'FL\\Lite');

// PSR-4 Autoloader (no composer)
require_once FLLITE_DIR . 'src/Autoloader.php';
\FL\Lite\Autoloader::register([
    'FL\\Lite\\' => FLLITE_DIR . 'src/',
]);

// Bootstrap the plugin
add_action('plugins_loaded', static function (): void {
    $plugin = new \FL\Lite\Plugin();
    $plugin->init();
});

// Activation: ensure defaults exist
register_activation_hook(__FILE__, static function (): void {
    if (false === get_option('fl_auto_localize')) {
        add_option('fl_auto_localize', false);
    }
    if (false === get_option('fl_fallback_font')) {
        add_option('fl_fallback_font', 'system-ui');
    }
    if (false === get_option('fl_status_map')) {
        add_option('fl_status_map', []);
    }
});

// Uninstall handled by uninstall.php
