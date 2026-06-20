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

if (!function_exists('do_action')) {
    function do_action(string $hook, mixed ...$args): void
    {
        $GLOBALS['sympress_mailer_test_actions'][] = [
            'hook' => $hook,
            'args' => $args,
        ];
    }
}
