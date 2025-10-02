<?php
if (!defined('ABSPATH')) { exit; }

function dual_chatbot_render_status_section(): void {
    if (!current_user_can('manage_options')) { return; }

    $smalot_available = class_exists('Smalot\PdfParser\Parser');

    $pdftotext_available = false;
    $pdftotext_unknown = false;

    if (function_exists('exec')) {
        $out = [];
        $code = 1;
        @exec('command -v pdftotext 2>/dev/null', $out, $code);
        if ($code === 0 && !empty($out) && !empty($out[0])) {
            $pdftotext_available = true;
        }
        if (!$pdftotext_available) {
            $o2 = [];
            $c2 = 1;
            @exec('pdftotext -v 2>&1', $o2, $c2);
            if ($c2 === 0 || stripos(implode("\n", (array) $o2), 'pdftotext') !== false) {
                $pdftotext_available = true;
            }
        }
    } elseif (function_exists('shell_exec')) {
        $which = @shell_exec('command -v pdftotext 2>/dev/null');
        if (is_string($which) && trim((string) $which) !== '') {
            $pdftotext_available = true;
        }
    } else {
        $pdftotext_unknown = true;
    }

    $warn_active = (bool) get_transient('dcb_pdf_extract_missing');
    $warn_since  = (int) get_option('dcb_pdf_extract_missing_time', time());
    $since_text  = human_time_diff($warn_since, time());

    $dismiss_url = wp_nonce_url(
        add_query_arg([
            'page'            => 'dual-chatbot-settings',
            'tab'             => 'status',
            'dcb_pdf_notice'  => 'dismiss',
        ], admin_url('options-general.php')),
        'dcb_pdf_notice'
    );

    echo '<h2>' . esc_html__('Status', 'dual-chatbot') . '</h2>';
    echo '<h3>' . esc_html__('PDF-Extraktion', 'dual-chatbot') . '</h3>';

    echo '<table class="widefat striped" style="max-width:700px">';
    echo '<tbody>';

    echo '<tr><th style="width:280px">' . esc_html__('Smalot\PdfParser', 'dual-chatbot') . '</th><td>'
        . ($smalot_available ? esc_html__('Ja', 'dual-chatbot') : esc_html__('Nein', 'dual-chatbot'))
        . '</td></tr>';

    echo '<tr><th>' . esc_html__('pdftotext', 'dual-chatbot') . '</th><td>';
    if ($pdftotext_unknown) {
        echo esc_html__('Unbekannt (exec/shell_exec deaktiviert)', 'dual-chatbot');
    } else {
        echo $pdftotext_available ? esc_html__('Ja', 'dual-chatbot') : esc_html__('Nein', 'dual-chatbot');
    }
    echo '</td></tr>';

    echo '<tr><th>' . esc_html__('Letzte Warnung', 'dual-chatbot') . '</th><td>';
    if ($warn_active) {
        echo esc_html(sprintf(__('Ja (vor %s)', 'dual-chatbot'), $since_text));
        echo ' &nbsp; <a class="button button-secondary" href="' . esc_url($dismiss_url) . '">' . esc_html__('Verstanden', 'dual-chatbot') . '</a>';
    } else {
        echo esc_html__('Nein', 'dual-chatbot');
    }
    echo '</td></tr>';

    echo '</tbody>';
    echo '</table>';
}

