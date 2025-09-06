<?php
declare(strict_types=1);

namespace FL\Lite\Services;

/**
 * Storage service writing under uploads/fonts_localizer with safe path joining.
 */
final class Storage
{
    private Logger $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Ensure base upload dir exists.
     *
     * @return array{path:string,url:string}
     */
    public function ensure_upload_dir(): array
    {
        $up = wp_upload_dir();
        $basePath = rtrim($up['basedir'], '/\\') . '/fonts_localizer';
        $baseUrl = rtrim($up['baseurl'], '/\\') . '/fonts_localizer';
        $this->ensureDir($basePath);
        return ['path' => $basePath, 'url' => $baseUrl];
    }

    /**
     * Save a file under uploads/fonts_localizer via WP_Filesystem.
     * Accepts only .woff2 extension to enforce security.
     *
     * @return string Absolute path of saved file on success, empty string on failure
     */
    public function save_file(string $destRelPath, string $bytes): string
    {
        $base = $this->ensure_upload_dir();
        $safeRel = $this->sanitize_relpath($destRelPath);
        if (!str_ends_with(strtolower($safeRel), '.woff2')) {
            $this->logger->warning('Blocked non-woff2 file', ['rel' => $safeRel]);
            return '';
        }

        $abs = $this->join($base['path'], $safeRel);
        $dir = dirname($abs);
        if (!$this->ensureDir($dir)) {
            $this->logger->error('Failed to create directory', ['dir' => $dir]);
            return '';
        }
        if (!$this->ensureFilesystem()) {
            return '';
        }
        global $wp_filesystem;
        $ok = (bool) $wp_filesystem->put_contents($abs, $bytes, FS_CHMOD_FILE);
        return $ok ? $abs : '';
    }

    /**
     * Write CSS file for a given bundle/family slug.
     *
     * @return array{path:string,url:string,version:string}
     */
    public function write_css(string $family, string $css): array
    {
        $base = $this->ensure_upload_dir();
        $slug = $this->slugify($family);
        $cssDir = $this->join($base['path'], 'css');
        $this->ensureDir($cssDir);
        $abs = $this->join($cssDir, $slug . '.css');
        if (!$this->ensureFilesystem()) {
            return ['path' => '', 'url' => '', 'version' => ''];
        }
        global $wp_filesystem;
        $ok = (bool) $wp_filesystem->put_contents($abs, $css, FS_CHMOD_FILE);
        if (!$ok) {
            $this->logger->error('Failed to write CSS', ['file' => $abs]);
            return ['path' => '', 'url' => '', 'version' => ''];
        }
        $url = rtrim($base['url'], '/').'/css/'.$slug.'.css';
        $ver = (string) @filemtime($abs);
        return ['path' => $abs, 'url' => $url, 'version' => $ver];
    }

    /** @internal */
    public function ensureDir(string $dir): bool
    {
        if (is_dir($dir)) {
            return true;
        }
        return wp_mkdir_p($dir);
    }

    /** @internal */
    public function ensureFilesystem(): bool
    {
        if (!function_exists('request_filesystem_credentials')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        $creds = request_filesystem_credentials('', '', false, false, null);
        if (!WP_Filesystem($creds)) {
            $this->logger->error('WP_Filesystem init failed');
            return false;
        }
        return true;
    }

    /** @internal */
    private function join(string $base, string $rel): string
    {
        $base = rtrim(str_replace(['\\', '\\'], '/', $base), '/');
        $rel = ltrim(str_replace(['\\', '\\'], '/', $rel), '/');
        $path = $base . '/' . $rel;
        // Prevent traversal; normalize and assert starts with base
        $normBase = realpath($base) ?: $base;
        $normPath = $this->normalizePath($path);
        if (strpos($normPath, rtrim($normBase, '/\\')) !== 0) {
            // fallback to base
            return $base;
        }
        return $normPath;
    }

    /** @internal */
    private function normalizePath(string $path): string
    {
        $parts = [];
        $segments = explode('/', str_replace(['\\', '\\'], '/', $path));
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.') continue;
            if ($segment === '..') { array_pop($parts); continue; }
            $parts[] = $segment;
        }
        return implode('/', $parts);
    }

    /** @internal */
    public function slugify(string $text): string
    {
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9\-_.]+/i', '-', $text) ?? '';
        $text = trim($text, '-');
        return $text !== '' ? $text : 'fonts';
    }

    /** @internal */
    private function sanitize_relpath(string $rel): string
    {
        $rel = str_replace(['\\', '\\'], '/', $rel);
        $parts = array_filter(explode('/', $rel), static function ($p) {
            return $p !== '' && $p !== '.' && $p !== '..';
        });
        $san = [];
        foreach ($parts as $p) {
            $san[] = $this->slugify($p);
        }
        return implode('/', $san);
    }
}
