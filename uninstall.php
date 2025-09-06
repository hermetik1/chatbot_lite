<?php
declare(strict_types=1);

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Single-site only in Lite
delete_option('fl_auto_localize');
delete_option('fl_fallback_font');
delete_option('fl_status_map');

