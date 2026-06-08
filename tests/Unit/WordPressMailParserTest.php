<?php

declare(strict_types=1);

namespace SymPress\Mailer\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SymPress\Mailer\Message\WordPressMailParser;

final class WordPressMailParserTest extends TestCase
{
    public function testParsesWordPressMailAttributes(): void
    {
        $mail = (new WordPressMailParser())->parse(
            [
                'to' => 'Ada <ada@example.test>, Grace <grace@example.test>',
                'subject' => 'Hello',
                'message' => '<p>Body</p>',
                'headers' => [
                    'From: Team <team@example.test>',
                    'Cc: ops@example.test',
                    'Bcc: audit@example.test',
                    'Reply-To: reply@example.test',
                    'Content-Type: text/html; charset=UTF-8',
                    'X-Custom: value',
                ],
                'attachments' => "/tmp/a.txt\n/tmp/b.txt",
            ],
        );

        self::assertSame(['Ada <ada@example.test>', 'Grace <grace@example.test>'], $mail->to);
        self::assertSame('Team <team@example.test>', $mail->from);
        self::assertSame(['ops@example.test'], $mail->cc);
        self::assertSame(['audit@example.test'], $mail->bcc);
        self::assertSame(['reply@example.test'], $mail->replyTo);
        self::assertSame('text/html', $mail->contentType);
        self::assertSame('UTF-8', $mail->charset);
        self::assertSame(['/tmp/a.txt', '/tmp/b.txt'], $mail->attachments);
        self::assertSame(['value'], $mail->headers['X-Custom']);
    }
}
