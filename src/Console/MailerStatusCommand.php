<?php

declare(strict_types=1);

namespace SymPress\Mailer\Console;

use SymPress\Mailer\Config\DeliverySettings;
use SymPress\Mailer\Config\SettingsRepositoryInterface;
use SymPress\Mailer\Validation\ConnectionValidatorInterface;
use SymPress\Mailer\Validation\SmtpConnectionProbe;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'mailer:status',
    description: 'Show the SymPress Mailer delivery configuration without secrets.',
)]
final class MailerStatusCommand extends Command
{
    public function __construct(
        private readonly SettingsRepositoryInterface $settingsRepository,
        private readonly ConnectionValidatorInterface $validator,
        private readonly SmtpConnectionProbe $probe,
    ) {

        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('probe', null, InputOption::VALUE_NONE, 'Also connect to the SMTP server and log in, without sending mail.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $settings = DeliverySettings::read($this->settingsRepository);
        } catch (\Throwable $error) {
            $output->writeln(sprintf('<error>Mailer settings cannot be read: %s</error>', OutputFormatter::escape($error->getMessage())));

            return Command::FAILURE;
        }

        $connection = $settings->defaultConnection();
        $yesNo = static fn (bool $value): string => $value ? 'yes' : 'no';

        // Only non-secret fields: DSN, username, password and API credentials are never printed.
        $rows = [
            'Enabled'          => $yesNo($settings->enabled),
            'Do not send'      => $yesNo($settings->doNotSend),
            'Connection'       => $connection->id,
            'Provider'         => $connection->provider,
            'Secret source'    => $connection->keyStore,
            'From email'       => $connection->fromEmail,
            'From name'        => $connection->fromName,
            'Force From email' => $yesNo($connection->forceFrom),
            'Force From name'  => $yesNo($connection->forceFromName),
        ];

        foreach ($rows as $label => $value) {
            $output->writeln(sprintf('%-17s %s', $label . ':', OutputFormatter::escape($value !== '' ? $value : '(not set)')));
        }

        $validation = $this->validator->validate($connection);
        $healthy = $validation->valid();
        $output->writeln($healthy ? '<info>Configuration: valid</info>' : '<error>Configuration: invalid</error>');

        if (!$healthy) {
            foreach (explode("\n", $validation->message()) as $line) {
                $output->writeln('  - ' . OutputFormatter::escape($line));
            }
        }

        if ($input->getOption('probe') === true) {
            $health = $this->probe->probe($connection);
            $healthy = $healthy && $health->healthy;
            $output->writeln(sprintf($health->healthy ? '<info>Connection check: %s</info>' : '<error>Connection check: %s</error>', OutputFormatter::escape($health->message)));
        }

        return $healthy ? Command::SUCCESS : Command::FAILURE;
    }
}
