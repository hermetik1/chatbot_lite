<?php
declare(strict_types=1);

namespace FL\Lite;

final class Autoloader
{
    /** @var array<string,string> */
    private static array $prefixes = [];

    /**
     * @param array<string,string> $map
     */
    public static function register(array $map): void
    {
        self::$prefixes = $map + self::$prefixes;
        spl_autoload_register([self::class, 'autoload']);
    }

    private static function autoload(string $class): void
    {
        foreach (self::$prefixes as $prefix => $baseDir) {
            $len = strlen($prefix);
            if (strncmp($prefix, $class, $len) !== 0) {
                continue;
            }
            $relative = substr($class, $len);
            $relativePath = str_replace(['\\', '\\'], DIRECTORY_SEPARATOR, $relative) . '.php';
            $file = rtrim($baseDir, '/\\') . DIRECTORY_SEPARATOR . $relativePath;
            if (is_file($file)) {
                /** @noinspection PhpIncludeInspection */
                require $file;
            }
        }
    }
}

