<?php
declare(strict_types=1);

namespace FL\Lite\Admin;

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

final class ListTable extends \WP_List_Table
{
    /** @var array<int,array<string,string>> */
    private array $itemsData = [];

    /**
     * @param array<int,array<string,string>> $items
     */
    public function set_items(array $items): void
    {
        $this->itemsData = $items;
    }

    public function get_columns(): array
    {
        return [
            'url' => __('Quelle', 'fonts-localizer-lite'),
            'status' => __('Status', 'fonts-localizer-lite'),
            'css_url' => __('Lokale CSS', 'fonts-localizer-lite'),
        ];
    }

    protected function column_default($item, $column_name)
    {
        switch ($column_name) {
            case 'url':
                return '<code>' . esc_html((string) $item['url']) . '</code>';
            case 'status':
                $s = (string) ($item['status'] ?? 'external');
                $emoji = $s === 'local' ? '🟢 lokal' : ($s === 'error' ? '🔴 Fehler' : '🔴 extern');
                return esc_html($emoji);
            case 'css_url':
                $u = (string) ($item['css_url'] ?? '');
                return $u !== '' ? '<a href="' . esc_url($u) . '" target="_blank">' . esc_html__('Ansehen', 'fonts-localizer-lite') . '</a>' : '—';
        }
        return '';
    }

    public function prepare_items(): void
    {
        $columns = $this->get_columns();
        $hidden = [];
        $sortable = [];
        $this->_column_headers = [$columns, $hidden, $sortable, 'url'];
        $this->items = $this->itemsData;
    }
}

