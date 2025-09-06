<?php
declare(strict_types=1);

namespace FL\Lite\Services;

final class Scanner
{
    private Logger $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @return string[] Google Fonts CSS2 URLs found in enqueued styles
     */
    public function scanEnqueued(): array
    {
        global $wp_styles;
        $found = [];
        if (!isset($wp_styles) || !($wp_styles instanceof \WP_Styles)) {
            return $found;
        }
        foreach ((array) $wp_styles->queue as $handle) {
            if (!isset($wp_styles->registered[$handle])) {
                continue;
            }
            $src = (string) $wp_styles->registered[$handle]->src;
            $abs = $this->absolute($src);
            if (strpos($abs, 'fonts.googleapis.com/css2') !== false) {
                $found[] = $this->normalize($abs);
            }
        }
        return array_values(array_unique($found));
    }

    /**
     * @return string[] Google Fonts CSS2 URLs found in HTML
     */
    public function scanHtml(string $html): array
    {
        $matches = [];
        preg_match_all('#<link[^>]+href=["\']([^"\']+fonts\.googleapis\.com/css2[^"\']+)["\'][^>]*>#i', $html, $matches);
        $list = array_map([$this, 'normalize'], array_map([$this, 'absolute'], $matches[1] ?? []));
        return array_values(array_unique($list));
    }

    /**
     * @return string[]
     */
    public function scanHomePage(): array
    {
        $urls = [];
        $resp = wp_remote_get(home_url('/'), [
            'timeout' => 15,
            'user-agent' => 'FontsLocalizerLiteScanner/' . (defined('FLLITE_VERSION') ? FLLITE_VERSION : '0'),
        ]);
        if (is_wp_error($resp)) {
            $this->logger->error('scanHomePage error', ['error' => $resp->get_error_message()]);
            return $urls;
        }
        $code = (int) wp_remote_retrieve_response_code($resp);
        if ($code !== 200) {
            $this->logger->error('scanHomePage http error', ['code' => $code]);
            return $urls;
        }
        $body = (string) wp_remote_retrieve_body($resp);
        return $this->scanHtml($body);
    }

    private function normalize(string $url): string
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

    private function absolute(string $src): string
    {
        if (str_starts_with($src, '//')) {
            return (is_ssl() ? 'https:' : 'http:') . $src;
        }
        if (preg_match('#^https?://#i', $src)) {
            return $src;
        }
        return home_url($src);
    }
}

