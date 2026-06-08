<?php

declare(strict_types=1);

namespace SymPress\Mailer\Asset;

use SymPress\Assets\Asset;
use SymPress\Assets\AssetProviderInterface;
use SymPress\Assets\Loader\EncoreEntrypointsLoader;
use SymPress\Assets\Loader\WebpackManifestLoader;
use SymPress\Assets\Script;
use SymPress\Assets\Style;
use SymPress\Kernel\SiteConfig;
use SymPress\Mailer\Support\WordPressArray;

final readonly class MailerAdminAssets implements AssetProviderInterface
{
    private const string PRO_ENTRY = 'mailer-pro/mailer-pro.php';

    public function __construct(
        private SiteConfig $config,
    ) {
    }

    /**
     * @return iterable<Asset>
     */
    public function assets(): iterable
    {
        $locations = $this->config->locations();
        $assetUrl = $locations->pluginsUrl('mailer/assets');
        $assetDir = $locations->pluginsDir('mailer/assets');

        if ($assetUrl === null || $assetDir === null) {
            return;
        }

        $assetUrl = rtrim($assetUrl, '/') . '/';
        $assetDir = rtrim($assetDir, DIRECTORY_SEPARATOR);
        $loader = $this->loader($assetDir);
        $canEnqueue = $this->canEnqueue(...);

        if ($loader !== null) {
            $file = $loader instanceof EncoreEntrypointsLoader
                ? $assetDir . DIRECTORY_SEPARATOR . 'entrypoints.json'
                : $assetDir . DIRECTORY_SEPARATOR . 'manifest.json';

            foreach ($loader->withDirectoryUrl($assetUrl)->load($file) as $asset) {
                yield $this->prepare($asset, $canEnqueue);
            }

            return;
        }

        yield (new Style('sympress-mailer-admin', $assetUrl . 'admin.css', Asset::BACKEND))
            ->withFilePath($assetDir . DIRECTORY_SEPARATOR . 'admin.css')
            ->canEnqueue($canEnqueue);

        yield (new Script('sympress-mailer-admin', $assetUrl . 'admin.js', Asset::BACKEND))
            ->withFilePath($assetDir . DIRECTORY_SEPARATOR . 'admin.js')
            ->canEnqueue($canEnqueue)
            ->defer();
    }

    /**
     * @param callable(): bool $canEnqueue
     */
    private function prepare(Asset $asset, callable $canEnqueue): Asset
    {
        $asset
            ->forLocation(Asset::BACKEND)
            ->canEnqueue($canEnqueue);

        if ($asset instanceof Script) {
            $asset->defer();
        }

        return $asset;
    }

    private function loader(string $assetDir): EncoreEntrypointsLoader|WebpackManifestLoader|null
    {
        if (is_readable($assetDir . DIRECTORY_SEPARATOR . 'entrypoints.json')) {
            return new EncoreEntrypointsLoader();
        }

        if (is_readable($assetDir . DIRECTORY_SEPARATOR . 'manifest.json')) {
            return new WebpackManifestLoader();
        }

        return null;
    }

    private function canEnqueue(): bool
    {
        if ($this->proPluginActive()) {
            return false;
        }

        $page = WordPressArray::string(WordPressArray::get()['page'] ?? '');

        return $page === 'sympress-mailer'
            || str_starts_with($page, 'sympress-mailer-');
    }

    private function proPluginActive(): bool
    {
        if (!function_exists('get_option')) {
            return false;
        }

        $active = get_option('active_plugins', []);

        if (is_array($active) && in_array(self::PRO_ENTRY, $active, true)) {
            return true;
        }

        if (!function_exists('is_multisite') || !is_multisite() || !function_exists('get_site_option')) {
            return false;
        }

        $network = get_site_option('active_sitewide_plugins', []);

        return is_array($network) && array_key_exists(self::PRO_ENTRY, $network);
    }
}
