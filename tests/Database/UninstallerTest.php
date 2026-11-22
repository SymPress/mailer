<?php

declare(strict_types=1);

namespace SymPress\Mailer\Tests\Database;

use PHPUnit\Framework\TestCase;
use SymPress\Mailer\Hook\Uninstaller;

final class UninstallerTest extends TestCase
{
    private const string OPTION = 'sympress_mailer_settings';

    public function testEverySiteAndNetworkOptionIsDeletedOnlyByItsOwnFlag(): void
    {
        global $wpdb;
        // WordPress core has no API to create a network; multi-network plugins insert the row the same way.
        $wpdb->insert($wpdb->site, ['domain' => 'second-network.test', 'path' => '/']);
        $secondNetwork = (int) $wpdb->insert_id;
        clean_network_cache($secondNetwork);
        $optedInSite = wp_insert_site(['domain' => DOMAIN_CURRENT_SITE, 'path' => '/uninstall-opted-in/']);
        $silentSite = wp_insert_site(['domain' => DOMAIN_CURRENT_SITE, 'path' => '/uninstall-silent/']);
        $secondNetworkSite = wp_insert_site(['domain' => 'second-network.test', 'path' => '/', 'network_id' => $secondNetwork]);
        self::assertIsInt($optedInSite);
        self::assertIsInt($silentSite);
        self::assertIsInt($secondNetworkSite);

        try {
            update_option(self::OPTION, ['uninstall_data' => false]);
            update_blog_option($optedInSite, self::OPTION, ['uninstall_data' => true]);
            update_blog_option($silentSite, self::OPTION, ['enabled' => true]);
            update_blog_option($secondNetworkSite, self::OPTION, ['uninstall_data' => true]);
            update_network_option(1, self::OPTION, ['uninstall_data' => true]);
            update_network_option($secondNetwork, self::OPTION, ['uninstall_data' => false]);

            Uninstaller::uninstall();

            self::assertSame(['uninstall_data' => false], get_option(self::OPTION));
            self::assertFalse(get_blog_option($optedInSite, self::OPTION));
            self::assertSame(['enabled' => true], get_blog_option($silentSite, self::OPTION));
            self::assertFalse(get_blog_option($secondNetworkSite, self::OPTION));
            self::assertFalse(get_network_option(1, self::OPTION));
            self::assertSame(['uninstall_data' => false], get_network_option($secondNetwork, self::OPTION));
        } finally {
            foreach ([$optedInSite, $silentSite, $secondNetworkSite] as $site) {
                wp_delete_site($site);
            }
            delete_option(self::OPTION);
            delete_network_option(1, self::OPTION);
            delete_network_option($secondNetwork, self::OPTION);
            $wpdb->delete($wpdb->site, ['id' => $secondNetwork]);
            clean_network_cache($secondNetwork);
        }
    }

    public function testMainSiteOptionIsDeletedWhenItOptedIn(): void
    {
        update_option(self::OPTION, ['uninstall_data' => true]);

        Uninstaller::uninstall();

        self::assertFalse(get_option(self::OPTION));
    }
}
