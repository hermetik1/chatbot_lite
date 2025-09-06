<?php
declare(strict_types=1);

namespace FL\Lite\Services;

/**
 * Minimal PSR-3 style logger writing to WP_DEBUG/WP_DEBUG_LOG.
 */
final class Logger
{
    private string $prefix;

    public function __construct(string $prefix = 'FLLite')
    {
        $this->prefix = $prefix;
    }

    /** @param array<string,mixed> $context */
    public function emergency(string $message, array $context = []): void { $this->log('emergency', $message, $context); }
    /** @param array<string,mixed> $context */
    public function alert(string $message, array $context = []): void { $this->log('alert', $message, $context); }
    /** @param array<string,mixed> $context */
    public function critical(string $message, array $context = []): void { $this->log('critical', $message, $context); }
    /** @param array<string,mixed> $context */
    public function error(string $message, array $context = []): void { $this->log('error', $message, $context); }
    /** @param array<string,mixed> $context */
    public function warning(string $message, array $context = []): void { $this->log('warning', $message, $context); }
    /** @param array<string,mixed> $context */
    public function notice(string $message, array $context = []): void { $this->log('notice', $message, $context); }
    /** @param array<string,mixed> $context */
    public function info(string $message, array $context = []): void { $this->log('info', $message, $context); }
    /** @param array<string,mixed> $context */
    public function debug(string $message, array $context = []): void { $this->log('debug', $message, $context); }

    /**
     * @param array<string,mixed> $context
     */
    public function log(string $level, string $message, array $context = []): void
    {
        if (!$this->shouldLog()) {
            return;
        }
        $interpolated = $this->interpolate($message, $context);
        $msg = '[' . $this->prefix . ':' . strtoupper($level) . '] ' . $interpolated;
        if (!empty($context)) {
            $msg .= ' ' . wp_json_encode($context);
        }
        error_log($msg);
    }

    private function shouldLog(): bool
    {
        // Log only when debug is on; prefer WP_DEBUG_LOG, then WP_DEBUG
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            return true;
        }
        return defined('WP_DEBUG') && WP_DEBUG;
    }

    /**
     * Replace {placeholders} in message with context values.
     *
     * @param array<string,mixed> $context
     */
    private function interpolate(string $message, array $context): string
    {
        $replace = [];
        foreach ($context as $key => $val) {
            $replace['{' . $key . '}'] = is_scalar($val) ? (string) $val : wp_json_encode($val);
        }
        return strtr($message, $replace);
    }
}
