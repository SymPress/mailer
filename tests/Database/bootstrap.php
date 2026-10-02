<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';
$databaseName = getenv('SYMPRESS_MAILER_TEST_DB');
if (!is_string($databaseName) || preg_match('/^sympress_review_mailer[a-z0-9_]+$/D', $databaseName) !== 1) {
    throw new RuntimeException('Set a unique disposable SYMPRESS_MAILER_TEST_DB schema.');
}
$databaseServer = getenv('SYMPRESS_MAILER_TEST_DB_HOST') ?: '127.0.0.1:33079';
$databaseUser = getenv('SYMPRESS_MAILER_TEST_DB_USER') ?: 'root';
$databasePassword = getenv('SYMPRESS_MAILER_TEST_DB_PASSWORD') ?: '';
[$databaseHost, $databasePort] = array_pad(explode(':', $databaseServer, 2), 2, '3306');
$connection = new mysqli($databaseHost, $databaseUser, $databasePassword, '', (int) $databasePort);
$connection->query("SET SESSION sql_mode = ''"); // Disposable bootstrap seed, before WordPress applies its database modes.
$connection->query('CREATE DATABASE `' . $databaseName . '`');
$ownerPid = getmypid();
register_shutdown_function(static function () use ($databaseName, $databaseHost, $databasePort, $databaseUser, $databasePassword, $ownerPid): void {
    if (getmypid() !== $ownerPid) {
        return;
    }
    (new mysqli($databaseHost, $databaseUser, $databasePassword, '', (int) $databasePort))->query('DROP DATABASE IF EXISTS `' . $databaseName . '`');
});
// Seed only the network bootstrap identity before WordPress installs its canonical schema.
$connection->select_db($databaseName);
$connection->query("CREATE TABLE wp_options (option_id bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY, option_name varchar(191) NOT NULL DEFAULT '', option_value longtext NOT NULL, autoload varchar(20) NOT NULL DEFAULT 'yes', UNIQUE KEY option_name (option_name))");
$connection->query("INSERT INTO wp_options (option_name,option_value) VALUES ('siteurl','https://mailer-review.test'),('home','https://mailer-review.test'),('blog_charset','UTF-8')");
$connection->query("CREATE TABLE wp_site (id bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY, domain varchar(200) NOT NULL, path varchar(100) NOT NULL)");
$connection->query("INSERT INTO wp_site (id,domain,path) VALUES (1,'mailer-review.test','/')");
$connection->query("CREATE TABLE wp_sitemeta (meta_id bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY, site_id bigint unsigned NOT NULL DEFAULT 0, meta_key varchar(255), meta_value longtext)");
$connection->query("INSERT INTO wp_sitemeta (site_id,meta_key,meta_value) VALUES (1,'site_name','Mailer review'),(1,'siteurl','https://mailer-review.test'),(1,'admin_email','admin@example.test')");
$connection->query("CREATE TABLE wp_blogs (blog_id bigint unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY, site_id bigint unsigned NOT NULL DEFAULT 0, domain varchar(200) NOT NULL, path varchar(100) NOT NULL, registered datetime NOT NULL DEFAULT '0000-00-00 00:00:00', last_updated datetime NOT NULL DEFAULT '0000-00-00 00:00:00', public tinyint NOT NULL DEFAULT 1, archived tinyint NOT NULL DEFAULT 0, mature tinyint NOT NULL DEFAULT 0, spam tinyint NOT NULL DEFAULT 0, deleted tinyint NOT NULL DEFAULT 0, lang_id int NOT NULL DEFAULT 0)");
$connection->query("INSERT INTO wp_blogs (blog_id,site_id,domain,path) VALUES (1,1,'mailer-review.test','/')");

$_SERVER['HTTP_HOST'] = 'mailer-review.test';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
define('ABSPATH', dirname(__DIR__, 2) . '/vendor/wordpress/wordpress/');
define('DB_NAME', $databaseName);
define('DB_USER', $databaseUser);
define('DB_PASSWORD', $databasePassword);
define('DB_HOST', $databaseServer);
const DB_CHARSET = 'utf8mb4';
const DB_COLLATE = '';
const WP_INSTALLING = true;
const WP_DEBUG = false;
const WP_HOME = 'https://mailer-review.test';
const WP_SITEURL = 'https://mailer-review.test';
const SYMPRESS_MAILER_ENCRYPTION_KEY = 'disposable-mailer-review-fixture-key-2026';
const MULTISITE = true;
const SUBDOMAIN_INSTALL = false;
const DOMAIN_CURRENT_SITE = 'mailer-review.test';
const PATH_CURRENT_SITE = '/';
const SITE_ID_CURRENT_SITE = 1;
const BLOG_ID_CURRENT_SITE = 1;
$table_prefix = 'wp_';
require ABSPATH . 'wp-settings.php';
require ABSPATH . 'wp-admin/includes/upgrade.php';
require ABSPATH . 'wp-admin/includes/admin.php';
require ABSPATH . 'wp-admin/includes/network.php';
add_filter('pre_http_request', static fn () => new WP_Error('review_no_network', 'Network requests are disabled in this fixture.'));
add_filter('pre_wp_mail', '__return_true'); // No real delivery during fixture installation.
wp_install('Mailer review', 'review-admin', 'admin@example.test', false, '', bin2hex(random_bytes(20)));
install_network();
update_site_option('site_admins', ['review-admin']);
remove_all_filters('pre_wp_mail');
wp_set_current_user(1);
grant_super_admin(1);
$GLOBALS['wpdb']->suppress_errors(false);
