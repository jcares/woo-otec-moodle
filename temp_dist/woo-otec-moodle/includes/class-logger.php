<?php

if (!defined('ABSPATH')) {
    exit;
}

final class Woo_OTEC_Moodle_Logger {
    public const SYNC_LOG = 'sync.log';
    public const ERROR_LOG = 'error.log';
    public const MAIN_LOG = 'log.txt';

    public static function log(string $message, string $filename = self::MAIN_LOG): void {
        self::write($filename, 'INFO', $message);
    }

    public static function info(string $message, array $context = array()): void {
        self::write(self::SYNC_LOG, 'INFO', $message, $context);
    }

    public static function error(string $message, array $context = array()): void {
        self::write(self::ERROR_LOG, 'ERROR', $message, $context);
    }

    public static function get_directory(): string {
        $directory = WOO_OTEC_MOODLE_PATH . 'logs/';
        self::ensure_directory($directory);
        return $directory;
    }

    private static function ensure_directory(string $path): void {
        if (!file_exists($path)) {
            wp_mkdir_p($path);
            // Add index.php for security
            file_put_contents($path . 'index.php', '<?php // Silence is golden');
        }
    }

    public static function get_file_path(string $filename): string {
        return self::get_directory() . ltrim($filename, '/');
    }

    public static function read_full(string $filename): string {
        $file = self::get_file_path($filename);
        if (!file_exists($file)) {
            return '';
        }
        return (string) file_get_contents($file);
    }

    public static function read_tail(string $filename, int $max_lines = 200): array {
        $file = self::get_file_path($filename);

        if (!file_exists($file)) {
            return array();
        }

        $contents = file_get_contents($file);
        if ($contents === false || $contents === '') {
            return array();
        }

        $lines = preg_split('/\r\n|\r|\n/', (string) $contents);
        if (!is_array($lines)) {
            return array();
        }

        $lines = array_values(array_filter($lines, static fn($line) => trim($line) !== ''));
        return array_slice($lines, -1 * $max_lines);
    }

    private static function write(string $filename, string $level, string $message, array $context = array()): void {
        $line = sprintf(
            "[%s] [%s] %s",
            gmdate('c'),
            $level,
            $message
        );

        if (!empty($context)) {
            $line .= ' | ' . wp_json_encode(self::sanitize_context($context), JSON_UNESCAPED_UNICODE);
        }

        $line .= PHP_EOL;
        $file = self::get_file_path($filename);

        // Native PHP append is much more reliable across disparate hosting environments
        file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }

    private static function sanitize_context(array $context): array {
        foreach (array('token', 'wstoken', 'moodle_token') as $secret_key) {
            if (isset($context[$secret_key])) {
                $context[$secret_key] = '***';
            }
        }

        return $context;
    }
}
