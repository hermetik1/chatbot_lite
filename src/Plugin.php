<?php
declare(strict_types=1);

namespace FL\Lite;

use FL\Lite\Options\Settings;
use FL\Lite\Services\CssRewriter;
use FL\Lite\Services\Downloader;
use FL\Lite\Services\Logger;
use FL\Lite\Services\Scanner;
use FL\Lite\Services\Storage;
use FL\Lite\Admin\AdminPage;

final class Plugin
{
    private Settings $settings;
    private Logger $logger;
    private Storage $storage;
    private CssRewriter $rewriter;
    private Downloader $downloader;
    private Scanner $scanner;
    private AdminPage $adminPage;

    public function init(): void
    {
        $this->settings = new Settings();
        $this->logger = new Logger('FLLite');
        $this->storage = new Storage($this->logger);
        $this->rewriter = new CssRewriter($this->logger, $this->storage, $this->settings);
        $this->downloader = new Downloader($this->logger, $this->storage, $this->rewriter, $this->settings);
        $this->scanner = new Scanner($this->logger);
        $this->adminPage = new AdminPage($this->settings, $this->scanner, $this->downloader, $this->logger);

        add_action('admin_menu', [$this->adminPage, 'register_menu']);
        add_action('admin_enqueue_scripts', [$this->adminPage, 'enqueue_assets']);

        add_action('wp_ajax_fl_localize_now', [$this, 'ajax_localize_now']);

        add_action('rest_api_init', [$this, 'register_rest']);

        add_action('wp_enqueue_scripts', [$this, 'replace_enqueued_google_fonts'], 20);
        add_action('template_redirect', [$this, 'buffer_start']);
        add_action('shutdown', [$this, 'buffer_end'], 0);

        add_action('wp_footer', [$this, 'frontend_admin_notice']);
    }

    public function ajax_localize_now(): void
    {
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Unauthorized', 'fonts-localizer-lite')], 403);
        }
        check_ajax_referer('fl_admin_nonce', 'nonce');

        $urls = $this->scanner->scanHomePage();
        $results = [];
        foreach ($urls as $url) {
            $results[$url] = $this->downloader->localize($url);
        }

        wp_send_json_success([
            'results' => $results,
        ]);
    }

    public function register_rest(): void
    {
        register_rest_route('fl/v1', '/localize', [
            'methods' => 'POST',
            'permission_callback' => static function (): bool {
                return current_user_can('manage_options');
            },
            'callback' => function (\WP_REST_Request $request) {
                $url = (string) $request->get_param('url');
                if (!$url || !current_user_can('manage_options')) {
                    return new \WP_REST_Response(['error' => 'invalid'], 400);
                }
                $result = $this->downloader->localize($url);
                return new \WP_REST_Response(['result' => $result], 200);
            },
        ]);
    }

    public function replace_enqueued_google_fonts(): void
    {
        global $wp_styles;
        if (!isset($wp_styles) || !($wp_styles instanceof \WP_Styles)) {
            return;
        }

        $auto = (bool) get_option('fl_auto_localize', false);
        $map = get_option('fl_status_map', []);
        if (!is_array($map)) {
            $map = [];
        }

        foreach ((array) $wp_styles->queue as $handle) {
            if (!isset($wp_styles->registered[$handle])) {
                continue;
            }
            $style = $wp_styles->registered[$handle];
            $src = $style->src;
            if (!$src) {
                continue;
            }
            $full = $this->to_absolute_url($src);
            if (strpos($full, 'fonts.googleapis.com/css2') === false) {
                continue;
            }

            $key = $this->normalize_key($full);
            $entry = $map[$key] ?? null;

            if (!$entry && $auto) {
                $entry = $this->downloader->localize($full);
                $map[$key] = $entry;
                update_option('fl_status_map', $map, false);
            }

            if (is_array($entry) && ($entry['status'] ?? '') === 'local') {
                $cssUrl = (string) ($entry['css_url'] ?? '');
                $cssPath = (string) ($entry['css_path'] ?? '');
                if ($cssUrl && is_file($cssPath)) {
                    $ver = (string) @filemtime($cssPath);
                    $cssUrlVer = add_query_arg('ver', $ver, $cssUrl);
                    $newHandle = 'fl-localized-' . $handle;
                    wp_register_style($newHandle, $cssUrlVer, $style->deps, null);
                    wp_dequeue_style($handle);
                    wp_deregister_style($handle);
                    wp_enqueue_style($newHandle);
                }
            }
        }
    }

    private function to_absolute_url(string $src): string
    {
        if (str_starts_with($src, '//')) {
            return (is_ssl() ? 'https:' : 'http:') . $src;
        }
        if (preg_match('#^https?://#i', $src)) {
            return $src;
        }
        return home_url($src);
    }

    private function normalize_key(string $url): string
    {
        // Normalize by removing cache-busters/order
        $parts = wp_parse_url($url);
        if (!$parts) {
            return $url;
        }
        $scheme = isset($parts['scheme']) ? $parts['scheme'] . '://' : '';
        $host = $parts['host'] ?? '';
        $path = $parts['path'] ?? '';
        $query = [];
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
            ksort($query);
        }
        return $scheme . $host . $path . '?' . http_build_query($query);
    }

    public function buffer_start(): void
    {
        ob_start([$this, 'buffer_rewrite']);
    }

    public function buffer_end(): void
    {
        if (ob_get_level() > 0) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo ob_get_clean();
        }
    }

    public function buffer_rewrite(string $html): string
    {
        // Replace <link href="https://fonts.googleapis.com/css2..."> with local when available
        $map = get_option('fl_status_map', []);
        if (!is_array($map) || empty($map)) {
            return $html;
        }

        $html = (string) $html;
        $matches = [];
        if (preg_match_all('#<link[^>]+href=["\']([^"\']+fonts\.googleapis\.com/css2[^"\']+)["\'][^>]*>#i', $html, $matches)) {
            foreach ($matches[1] as $foundUrl) {
                $key = $this->normalize_key($this->to_absolute_url($foundUrl));
                $entry = $map[$key] ?? null;
                if (is_array($entry) && ($entry['status'] ?? '') === 'local') {
                    $cssUrl = (string) ($entry['css_url'] ?? '');
                    $cssPath = (string) ($entry['css_path'] ?? '');
                    if ($cssUrl && is_file($cssPath)) {
                        $ver = (string) @filemtime($cssPath);
                        $cssUrlVer = add_query_arg('ver', $ver, $cssUrl);
                        $html = str_replace((string) $foundUrl, $cssUrlVer, $html);
                    }
                }
            }
        }
        return $html;
    }

    public function frontend_admin_notice(): void
    {
        if (!is_user_logged_in() || !current_user_can('manage_options')) {
            return;
        }
        $map = get_option('fl_status_map', []);
        $anyLocal = false;
        if (is_array($map)) {
            foreach ($map as $entry) {
                if (is_array($entry) && ($entry['status'] ?? '') === 'local') {
                    $anyLocal = true;
                    break;
                }
            }
        }
        if ($anyLocal) {
            echo '<div class="fl-notice" style="position:fixed;bottom:10px;right:10px;background:#1e8e3e;color:#fff;padding:8px 12px;border-radius:4px;z-index:99999;">' . esc_html__('Google Fonts wurden erfolgreich lokalisiert ✅', 'fonts-localizer-lite') . '</div>';
        }
    }
}

