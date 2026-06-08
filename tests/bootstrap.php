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
