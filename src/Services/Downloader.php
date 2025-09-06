<?php
declare(strict_types=1);

namespace FL\Lite\Services;

use FL\Lite\Options\Settings;

final class Downloader
{
    private Logger $logger;
    private Storage $storage;
    private CssRewriter $rewriter;
    private Settings $settings;

    public function __construct(Logger $logger, Storage $storage, CssRewriter $rewriter, Settings $settings)
    {
        $this->logger = $logger;
        $this->storage = $storage;
        $this->rewriter = $rewriter;
        $this->settings = $settings;
    }

    /**
     * Download and localize the CSS for a Google Fonts css2 URL.
     *
     * @return array{status:string,css_url?:string,css_path?:string,last_error?:string}
     */
    public function localize(string $googleCssUrl): array
    {
        $key = $this->normalizeKey($googleCssUrl);

        $resp = wp_remote_get($googleCssUrl, [
            'timeout' => 20,
            'user-agent' => 'FontsLocalizerLite/' . (defined('FLLITE_VERSION') ? FLLITE_VERSION : '0') . '; ' . home_url('/'),
            'headers' => [
                'Accept' => 'text/css,*/*;q=0.1',
            ],
        ]);

        if (is_wp_error($resp)) {
            $err = $resp->get_error_message();
            $this->logger->error('Failed to fetch Google CSS', ['url' => $googleCssUrl, 'error' => $err]);
            $entry = ['status' => 'error', 'last_error' => $err];
            $this->persistStatus($key, $entry);
            return $entry;
        }

        $code = (int) wp_remote_retrieve_response_code($resp);
        $css = (string) wp_remote_retrieve_body($resp);
        if ($code !== 200 || trim($css) === '') {
            $err = 'HTTP ' . $code;
            $this->logger->error('Empty/invalid Google CSS', ['url' => $googleCssUrl, 'code' => $code]);
            $entry = ['status' => 'error', 'last_error' => $err];
            $this->persistStatus($key, $entry);
            return $entry;
        }

        $urls = $this->rewriter->extractGstaticUrls($css);
        $mapping = [];
        foreach ($urls as $u) {
            $local = $this->downloadWoff2($u);
            if ($local) {
                $mapping[$u] = $local;
            }
        }

        if (empty($mapping)) {
            $err = 'No WOFF2 files found or downloaded';
            $this->logger->error($err, ['url' => $googleCssUrl]);
            $entry = ['status' => 'error', 'last_error' => $err];
            $this->persistStatus($key, $entry);
            return $entry;
        }

        $rewritten = $this->rewriter->applyMapping($css, $mapping);

        $target = $this->storage->cssTargetFor($key);
        $ok = $this->storage->saveText($target['css_path'], $rewritten);
        if (!$ok) {
            $err = 'Failed to write local CSS';
            $entry = ['status' => 'error', 'last_error' => $err];
            $this->persistStatus($key, $entry);
            return $entry;
        }

        $entry = [
            'status' => 'local',
            'css_url' => $target['css_url'],
            'css_path' => $target['css_path'],
            'last_error' => '',
        ];
        /**
         * Allow Pro add-on to enrich/alter the entry (e.g., multisite paths, CDN).
         *
         * @param array  $entry
         * @param string $googleCssUrl
         */
        $entry = apply_filters('fl/lite/localize_entry', $entry, $googleCssUrl);
        $this->persistStatus($key, $entry);
        return $entry;
    }

    private function downloadWoff2(string $url): ?string
    {
        $resp = wp_remote_get($url, [
            'timeout' => 30,
            'user-agent' => 'FontsLocalizerLite/' . (defined('FLLITE_VERSION') ? FLLITE_VERSION : '0') . '; ' . home_url('/'),
        ]);
        if (is_wp_error($resp)) {
            $this->logger->error('Failed WOFF2 download', ['url' => $url, 'error' => $resp->get_error_message()]);
            return null;
        }
        $code = (int) wp_remote_retrieve_response_code($resp);
        if ($code !== 200) {
            $this->logger->error('WOFF2 unexpected response', ['url' => $url, 'code' => $code]);
            return null;
        }
        $bytes = (string) wp_remote_retrieve_body($resp);
        $target = $this->storage->woff2TargetFor($url);
        $ok = $this->storage->saveBinary($target['path'], $bytes);
        return $ok ? $target['url'] : null;
    }

    /**
     * Persist the status map entry
     * @param array<string,mixed> $entry
     */
    private function persistStatus(string $key, array $entry): void
    {
        $map = get_option('fl_status_map', []);
        if (!is_array($map)) {
            $map = [];
        }
        $map[$key] = $entry;
        update_option('fl_status_map', $map, false);
    }

    private function normalizeKey(string $url): string
    {
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
}
