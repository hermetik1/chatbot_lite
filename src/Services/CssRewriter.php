<?php
declare(strict_types=1);

namespace FL\Lite\Services;

final class CssRewriter
{
    private Logger $logger;
    private Storage $storage;
    private \FL\Lite\Options\Settings $settings;

    public function __construct(Logger $logger, Storage $storage, \FL\Lite\Options\Settings $settings)
    {
        $this->logger = $logger;
        $this->storage = $storage;
        $this->settings = $settings;
    }

    /**
     * @return string[]
     */
    public function extractGstaticUrls(string $css): array
    {
        $matches = [];
        preg_match_all('#url\(("|\')?(https://fonts\\.gstatic\\.com/[^\)\"\']+\.woff2)(\1)?\)#i', $css, $matches);
        $urls = array_values(array_unique($matches[2] ?? []));
        return $urls;
    }

    /**
     * @param array<string,string> $mapping remote gstatic url => local url
     */
    public function applyMapping(string $css, array $mapping): string
    {
        foreach ($mapping as $remote => $local) {
            $css = str_replace($remote, $local, $css);
        }
        // Ensure font-display: swap in each @font-face block
        $css = preg_replace_callback('#@font-face\s*\{[^\}]*\}#i', function ($m) {
            $block = $m[0];
            if (!preg_match('#font-display\s*:#i', $block)) {
                $block = rtrim(substr($block, 0, -1)) . ';font-display: swap;}';
            }
            return $block;
        }, $css) ?? $css;

        /**
         * Filter final CSS after mapping and font-display injection.
         *
         * @param string $css
         * @param array  $mapping
         */
        return apply_filters('fl/lite/rewritten_css', $css, $mapping);
    }
}
