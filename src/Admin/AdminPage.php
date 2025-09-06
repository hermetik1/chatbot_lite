<?php
declare(strict_types=1);

namespace FL\Lite\Admin;

use FL\Lite\Options\Settings;
use FL\Lite\Services\Downloader;
use FL\Lite\Services\Logger;
use FL\Lite\Services\Scanner;

final class AdminPage
{
    private Settings $settings;
    private Scanner $scanner;
    private Downloader $downloader;
    private Logger $logger;

    public function __construct(Settings $settings, Scanner $scanner, Downloader $downloader, Logger $logger)
    {
        $this->settings = $settings;
        $this->scanner = $scanner;
        $this->downloader = $downloader;
        $this->logger = $logger;
    }

    public function register_menu(): void
    {
        add_menu_page(
            __('Fonts Localizer', 'fonts-localizer-lite'),
            __('Fonts Localizer', 'fonts-localizer-lite'),
            'manage_options',
            'fonts-localizer-lite',
            [$this, 'render_page'],
            'dashicons-editor-underline',
            84
        );
    }

    public function enqueue_assets(string $hook): void
    {
        if ($hook !== 'toplevel_page_fonts-localizer-lite') {
            return;
        }
        wp_enqueue_style('fonts-localizer-lite-admin', plugins_url('assets/admin.css', \FLLITE_FILE), [], \FLLITE_VERSION);
        wp_enqueue_script('fonts-localizer-lite-admin', plugins_url('assets/admin.js', \FLLITE_FILE), [], \FLLITE_VERSION, true);
        wp_localize_script('fonts-localizer-lite-admin', 'FLLite', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('fl_admin_nonce'),
        ]);
    }

    public function render_page(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        $active = isset($_GET['tab']) ? sanitize_key((string) $_GET['tab']) : 'overview';
        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Fonts Localizer Lite', 'fonts-localizer-lite') . '</h1>';
        echo '<h2 class="nav-tab-wrapper">';
        $tabs = [
            'overview' => __('Übersicht', 'fonts-localizer-lite'),
            'settings' => __('Einstellungen', 'fonts-localizer-lite'),
            'license' => __('Lizenz', 'fonts-localizer-lite'),
        ];
        foreach ($tabs as $slug => $label) {
            $class = 'nav-tab' . ($active === $slug ? ' nav-tab-active' : '');
            $url = admin_url('admin.php?page=fonts-localizer-lite&tab=' . $slug);
            echo '<a href="' . esc_url($url) . '" class="' . esc_attr($class) . '">' . esc_html($label) . '</a>';
        }
        echo '</h2>';

        echo '<div class="fl-admin-content">';
        switch ($active) {
            case 'settings':
                $this->render_settings();
                break;
            case 'license':
                $this->render_license();
                break;
            case 'overview':
            default:
                $this->render_overview();
                break;
        }
        echo '</div></div>';
    }

    private function render_overview(): void
    {
        if (!class_exists('WP_List_Table')) {
            require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
        }
        $table = new ListTable();
        $data = $this->get_overview_rows();
        $table->set_items($data);
        echo '<p>' . esc_html__('Erkannte Google Fonts Quellen und Status. Klicke auf „Fonts lokal speichern“ für eine Ein-Klick-Lokalisierung.', 'fonts-localizer-lite') . '</p>';
        echo '<p><button id="fl-localize-now" class="button button-primary">' . esc_html__('Fonts lokal speichern', 'fonts-localizer-lite') . '</button></p>';
        echo '<div id="fl-localize-result"></div>';
        echo '<form method="post">';
        $table->prepare_items();
        $table->display();
        echo '</form>';
    }

    private function get_overview_rows(): array
    {
        $map = get_option('fl_status_map', []);
        if (!is_array($map)) {
            $map = [];
        }
        $rows = [];
        foreach ($map as $key => $entry) {
            $status = is_array($entry) ? ($entry['status'] ?? 'external') : 'external';
            $rows[] = [
                'url' => (string) $key,
                'status' => $status,
                'css_url' => is_array($entry) ? (string) ($entry['css_url'] ?? '') : '',
            ];
        }
        // If none, offer scan suggestions
        if (empty($rows)) {
            $urls = $this->scanner->scanHomePage();
            foreach ($urls as $u) {
                $rows[] = [
                    'url' => $u,
                    'status' => 'external',
                    'css_url' => '',
                ];
            }
        }
        return $rows;
    }

    private function render_settings(): void
    {
        echo '<form method="post" action="options.php">';
        settings_fields('fl_settings_group');
        do_settings_sections('fl_settings_group');

        echo '<table class="form-table" role="presentation">';
        echo '<tr><th scope="row">' . esc_html__('Automatisches Lokalisieren aktivieren', 'fonts-localizer-lite') . '</th><td>';
        $checked = checked(true, (bool) get_option('fl_auto_localize', false), false);
        echo '<label><input type="checkbox" name="fl_auto_localize" value="1" ' . $checked . '> ' . esc_html__('Ja', 'fonts-localizer-lite') . '</label>';
        echo '</td></tr>';

        echo '<tr><th scope="row">' . esc_html__('Fallback-Font', 'fonts-localizer-lite') . '</th><td>';
        $current = esc_attr((string) get_option('fl_fallback_font', 'system-ui'));
        echo '<select name="fl_fallback_font">';
        $options = [
            'system-ui' => 'system-ui',
            'Arial, Helvetica, sans-serif' => 'Arial',
            'Segoe UI, Tahoma, Geneva, Verdana, sans-serif' => 'Segoe UI',
            'Roboto, Helvetica Neue, Arial, sans-serif' => 'Roboto',
        ];
        foreach ($options as $value => $label) {
            $sel = selected($current, $value, false);
            echo '<option value="' . esc_attr($value) . '" ' . $sel . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">' . esc_html__('Wird genutzt, falls ein Download fehlschlägt.', 'fonts-localizer-lite') . '</p>';
        echo '</td></tr>';
        echo '</table>';

        submit_button(__('Speichern', 'fonts-localizer-lite'));
        echo '</form>';
    }

    private function render_license(): void
    {
        echo '<p>' . esc_html__('Lizenzverwaltung ist in der Pro-Version verfügbar.', 'fonts-localizer-lite') . '</p>';
    }
}

