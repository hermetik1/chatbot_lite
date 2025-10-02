<?php
if (!defined('ABSPATH')) { exit; }

/**
 * SQL Policy:
 * - Jeglicher User-Input -> IMMER via $wpdb->prepare().
 * - Ausnahmen nur für DDL/SHOW/EXPLAIN ohne User-Input (kommentieren).
 * - LIKE: Prozentzeichen in den vorbereiteten WERT, nicht in die Query.
 * - ORDER BY / LIMIT / Identifier: ausschließlich via Whitelist.
 * - IN-Klauseln: Platzhalterliste dynamisch erzeugen.
 * - Falls technisch nötig: Variablen whitelisten/sanitize_* und sicherstellen,
 *   dass sie nicht direkt aus User-Input stammen (z. B. Tabellennamen via Prefix).
 * - Bei unumgänglichen False-Positives: begründetes // phpcs:ignore mit Verweis auf Issue/PR.
 */

if (!function_exists('dcb_table_history')) {
    function dcb_table_history(): string {
        global $wpdb;
        // Align with plugin table naming used elsewhere (chatbot_history)
        return $wpdb->prefix . 'chatbot_history';
    }
}

if (!function_exists('dcb_table_analytics')) {
    function dcb_table_analytics(): string {
        global $wpdb;
        return $wpdb->prefix . 'dual_chatbot_analytics';
    }
}

// Helpers for safe SQL patterns
if (!function_exists('dcb_db_like')) {
    function dcb_db_like(string $s): string {
        global $wpdb;
        return '%' . $wpdb->esc_like($s) . '%';
    }
}

if (!function_exists('dcb_db_order_by')) {
    function dcb_db_order_by(string $col, array $allow, string $fallback): string {
        return in_array($col, $allow, true) ? $col : $fallback;
    }
}

if (!function_exists('dcb_db_dir')) {
    function dcb_db_dir(string $dir): string {
        $dir = strtoupper($dir);
        return $dir === 'ASC' ? 'ASC' : 'DESC';
    }
}

if (!function_exists('dcb_db_placeholders')) {
    function dcb_db_placeholders(int $count, string $type = '%d'): string {
        return implode(',', array_fill(0, max(0, $count), $type));
    }
}
