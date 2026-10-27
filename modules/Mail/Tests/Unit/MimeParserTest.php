<?php

declare(strict_types=1);

namespace Modules\Mail\Tests\Unit;

use Modules\Mail\Mime\MimeParser;
use Tests\Core\TestCase;

final class MimeParserTest extends TestCase
{
    public function testParsesMultipartAlternativeWithAttachment(): void
    {
        $raw = implode("\r\n", [
            'Subject: =?UTF-8?B?' . base64_encode('Rapport mensuel') . '?=',
            'From: =?UTF-8?B?' . base64_encode('Jean Dupont') . '?= <jean@pbs.fr>',
            'To: marie@pbs.fr',
            'Content-Type: multipart/mixed; boundary="BOUND"',
            '',
            '--BOUND',
            'Content-Type: multipart/alternative; boundary="INNER"',
            '',
            '--INNER',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: quoted-printable',
            '',
            'Bonjour Marie,=20voici le rapport.',
            '--INNER',
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            '',
            '<p>Bonjour Marie</p>',
            '--INNER--',
            '--BOUND',
            'Content-Type: application/pdf; name="rapport.pdf"',
            'Content-Transfer-Encoding: base64',
            'Content-Disposition: attachment; filename="rapport.pdf"',
            '',
            'JVBERi0xLjQ=',
            '--BOUND--',
            '',
        ]);

        $message = (new MimeParser())->parse($raw);

        self::assertSame('Rapport mensuel', $message->header('subject'));
        self::assertStringContainsString('Bonjour Marie, voici le rapport.', $message->textBody());
        self::assertStringContainsString('<p>Bonjour Marie</p>', $message->htmlBody());

        $attachments = $message->attachments();
        self::assertCount(1, $attachments);
        self::assertSame('rapport.pdf', $attachments[0]->filename());
        self::assertSame('%PDF-1.4', $attachments[0]->decodedBody());
    }

    public function testParsesBase64BodyAndCharsetConversion(): void
    {
        $raw = implode("\r\n", [
            'Content-Type: text/plain; charset=ISO-8859-1',
            'Content-Transfer-Encoding: base64',
            '',
            base64_encode(iconv('UTF-8', 'ISO-8859-1', 'Réunion à 10h')),
            '',
        ]);

        $part = (new MimeParser())->parse($raw);
        self::assertSame('Réunion à 10h', $part->decodedBody());
    }
}
