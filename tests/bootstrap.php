<?php

declare(strict_types=1);

$autoloaders = [
    __DIR__ . '/../../../vendor/autoload.php',
    __DIR__ . '/../vendor/autoload.php',
];

foreach ($autoloaders as $autoloader) {
    if (!is_readable($autoloader)) {
        continue;
    }

    require_once $autoloader;
    break;
}

if (!class_exists('wpdb')) {
    class wpdb
    {
        public string $prefix = 'wp_';

        public function get_charset_collate(): string
        {
            return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
        }
    }
}

$GLOBALS['sympress_mailer_test_options'] ??= [];

if (!function_exists('get_option')) {
    function get_option(string $option, mixed $default = false): mixed
    {
        return $GLOBALS['sympress_mailer_test_options'][$option] ?? $default;
    }
}

if (!function_exists('update_option')) {
    function update_option(string $option, mixed $value, mixed $autoload = null): bool
    {
        $GLOBALS['sympress_mailer_test_options'][$option] = $value;

        return true;
    }
}

$GLOBALS['sympress_mailer_test_actions'] ??= [];
$GLOBALS['sympress_mailer_test_hooks'] ??= [];

if (!function_exists('do_action')) {
    function do_action(string $hook, mixed ...$args): void
    {
        $GLOBALS['sympress_mailer_test_actions'][] = [
            'hook' => $hook,
            'args' => $args,
        ];

        foreach ($GLOBALS['sympress_mailer_test_hooks'][$hook] ?? [] as $callback) {
            $callback(...$args);
        }
    }
}

if (!function_exists('add_action')) {
    function add_action(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): bool
    {
        $GLOBALS['sympress_mailer_test_hooks'][$hook][] = $callback;

        return true;
    }
}

if (!function_exists('remove_action')) {
    function remove_action(string $hook, callable $callback, int $priority = 10): bool
    {
        $callbacks = $GLOBALS['sympress_mailer_test_hooks'][$hook] ?? [];
        $GLOBALS['sympress_mailer_test_hooks'][$hook] = array_values(array_filter($callbacks, static fn (callable $registered): bool => $registered !== $callback));

        return true;
    }
}

if (!function_exists('__')) {
    function __(string $text, string $domain = 'default'): string
    {
        return $text;
    }
}

if (!function_exists('esc_html')) {
    function esc_html(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_url')) {
    function esc_url(string $url): string
    {
        return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('admin_url')) {
    function admin_url(string $path = ''): string
    {
        return 'https://example.test/wp-admin/' . ltrim($path, '/');
    }
}

if (!function_exists('wp_get_environment_type')) {
    function wp_get_environment_type(): string
    {
        return $GLOBALS['sympress_mailer_test_environment'] ?? 'production';
    }
}
