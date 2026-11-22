<?php

declare(strict_types=1);

namespace SymPress\Mailer\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SymPress\Mailer\Hook\Uninstaller;

final class UninstallerTest extends TestCase
{
    public function testSingleSiteOptionIsDeletedOnlyWhenItOptedIn(): void
    {
        update_option('sympress_mailer_settings', ['uninstall_data' => false]);
        Uninstaller::uninstall();
        self::assertSame(['uninstall_data' => false], get_option('sympress_mailer_settings'));

        update_option('sympress_mailer_settings', ['uninstall_data' => true]);
        Uninstaller::uninstall();
        self::assertFalse(get_option('sympress_mailer_settings'));
    }
}
