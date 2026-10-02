<?php

declare(strict_types=1);

namespace SymPress\Mailer\Hook;

final class Translations
{
    public function load(): void
    {
        // phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- Register bundled translations at this Composer plugin path for WordPress just-in-time loading.
        load_plugin_textdomain('sympress-mailer', false, 'mailer/languages');
    }
}
