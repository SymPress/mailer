<?php

declare(strict_types=1);

namespace SymPress\Mailer\Console;

use SymPress\Mailer\Application\TestEmailSender;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'mailer:test',
    description: 'Send the SymPress Mailer test email.',
)]
final class MailerTestCommand extends Command
{
    public function __construct(
        private readonly TestEmailSender $sender,
    ) {

        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('to', InputArgument::REQUIRED, 'Recipient email address.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $to = $input->getArgument('to');

        if (!is_string($to) || filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            $output->writeln(sprintf('<error>"%s" is not a valid email address. Nothing was sent.</error>', OutputFormatter::escape(is_string($to) ? $to : '')));

            return Command::INVALID;
        }

        [$result, $reason] = $this->sender->sendWithFailureReason($to);

        if (!$result->accepted) {
            $output->writeln($reason !== ''
                ? sprintf('<error>Test email failed: %s</error>', OutputFormatter::escape($reason))
                : '<error>Test email failed. No reason is available.</error>');

            return Command::FAILURE;
        }

        $output->writeln(match ($result->status) {
            'suppressed' => '<comment>Status: suppressed. "Do not send" is on, so the test email was accepted but NOT delivered.</comment>',
            'queued' => sprintf(
                '<comment>Status: queued. The test email was accepted for later delivery and has NOT been sent yet.%s</comment>',
                $reason !== '' ? ' The last attempt failed: ' . OutputFormatter::escape($reason) : '',
            ),
            default => sprintf('<info>Status: %s. Test email sent to %s.</info>', OutputFormatter::escape($result->status), OutputFormatter::escape($to)),
        });

        return Command::SUCCESS;
    }
}
