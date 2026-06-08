<?php

/**
 * Plugin Name: SymPress Mailer
 * Description: Symfony Mailer powered SMTP and provider delivery for WordPress.
 * Version: 1.0.0
 * Requires at least: 6.9
 * Requires PHP: 8.5
 * Author: Brian Schaeffner
 * License: GPL-2.0-or-later
 */

declare(strict_types=1);

namespace SymPress\Mailer;

if (!defined('ABSPATH')) {
    return;
}

if (!class_exists(MailerBundle::class)) {
    require_once __DIR__ . '/vendor/autoload.php';
}

if (function_exists('register_uninstall_hook')) {
    register_uninstall_hook(__FILE__, [Hook\Uninstaller::class, 'uninstall']);
}
