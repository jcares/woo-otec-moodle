<?php

if (!defined('ABSPATH')) {
    exit;
}

final class PCC_WooOTEC_Pro_Logger {
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
        $directory = PCC_WOOOTEC_PRO_PATH . 'logs/';
        self::ensure_directory($directory);
        return $directory;
    }

    private static function ensure_directory(string $path): void {
        if (!file_exists($path)) {
            mkdir($path, 0755, true);
            // Add index.php for security
            file_put_contents($path . 'index.php', '<?php // Silence is golden');
            // Add .htaccess to prevent direct access
            file_put_contents($path . '.htaccess', 'deny from all');
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
        $filesystem = self::get_filesystem();
        if (!$filesystem) {
            return array();
        }

        if (!$filesystem->exists($file)) {
            return array();
        }

        $contents = $filesystem->get_contents($file);
        if ($contents === false) {
            return array();
        }

        $lines = preg_split('/\r\n|\r|\n/', (string) $contents);
        if (!is_array($lines)) {
            return array();
        }

        $lines = array_values(array_filter($lines, static fn($line) => $line !== ''));
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
            $line .= ' | ' . wp_json_encode(self::sanitize_context($context));
        }

        $line .= PHP_EOL;

        $filesystem = self::get_filesystem();
        if (!$filesystem) {
            return;
        }

        $file = self::get_file_path($filename);
        $existing = $filesystem->exists($file) ? $filesystem->get_contents($file) : '';
        $payload = ($existing !== false ? (string) $existing : '') . $line;

        $filesystem->put_contents($file, $payload, FS_CHMOD_FILE);
    }

    private static function get_filesystem(): mixed {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        if (!WP_Filesystem()) {
            return false;
        }

        global $wp_filesystem;
        return $wp_filesystem ?: false;
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
