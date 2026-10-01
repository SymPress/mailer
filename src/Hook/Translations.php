<?php

declare(strict_types=1);

namespace SymPress\Mailer\Hook;

final class Translations
{
    public function load(): void
    {
        load_plugin_textdomain('sympress-mailer', false, 'mailer/languages');
    }
}
