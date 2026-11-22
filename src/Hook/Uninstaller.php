<?php

declare(strict_types=1);

namespace SymPress\Mailer\Hook;

final class Uninstaller
{
    private const string OPTION_NAME = 'sympress_mailer_settings';
    private const int PAGE_SIZE = 100;

    /** Every stored option is deleted exactly when its own uninstall_data flag is true. */
    public static function uninstall(): void
    {
        if (!is_multisite()) {
            if (self::optedIn(get_option(self::OPTION_NAME))) {
                delete_option(self::OPTION_NAME);
            }

            return;
        }

        foreach (self::ids(get_networks(...)) as $networkId) {
            if (!self::optedIn(get_network_option($networkId, self::OPTION_NAME))) {
                continue;
            }

            delete_network_option($networkId, self::OPTION_NAME);
        }

        foreach (self::ids(get_sites(...)) as $siteId) {
            if (!self::optedIn(get_blog_option($siteId, self::OPTION_NAME))) {
                continue;
            }

            delete_blog_option($siteId, self::OPTION_NAME);
        }
    }

    private static function optedIn(mixed $option): bool
    {
        return is_array($option) && ($option['uninstall_data'] ?? false) === true;
    }

    /** @return \Generator<int> */
    private static function ids(callable $query): \Generator
    {
        // Deleting an option never removes a site or network, so offset paging cannot skip entries.
        for ($offset = 0;; $offset += self::PAGE_SIZE) {
            $ids = $query(['fields' => 'ids', 'number' => self::PAGE_SIZE, 'offset' => $offset]);

            if (!is_array($ids) || $ids === []) {
                return;
            }

            foreach ($ids as $id) {
                yield (int) $id;
            }
        }
    }
}
