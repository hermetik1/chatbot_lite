<?php
declare(strict_types=1);

namespace FL\Lite\Options;

final class Settings
{
    public function __construct()
    {
        add_action('admin_init', [$this, 'register']);
    }

    public function register(): void
    {
        register_setting('fl_settings_group', 'fl_auto_localize', [
            'type' => 'boolean',
            'description' => 'Automatically localize Google Fonts when detected',
            'sanitize_callback' => static function ($value): bool {
                return (bool) $value;
            },
            'default' => false,
            'show_in_rest' => false,
        ]);

        register_setting('fl_settings_group', 'fl_fallback_font', [
            'type' => 'string',
            'description' => 'Fallback font if localization fails',
            'sanitize_callback' => static function ($value): string {
                $value = is_string($value) ? trim($value) : '';
                if ($value === '') {
                    $value = 'system-ui';
                }
                return sanitize_text_field($value);
            },
            'default' => 'system-ui',
            'show_in_rest' => false,
        ]);

        register_setting('fl_settings_group', 'fl_status_map', [
            'type' => 'array',
            'description' => 'Map of Google Fonts CSS url to local status',
            'sanitize_callback' => function ($value): array {
                return is_array($value) ? $value : [];
            },
            'default' => [],
            'show_in_rest' => false,
        ]);
    }

    public function autoLocalize(): bool
    {
        return (bool) get_option('fl_auto_localize', false);
    }

    public function fallbackFont(): string
    {
        $val = (string) get_option('fl_fallback_font', 'system-ui');
        return $val !== '' ? $val : 'system-ui';
    }
}

