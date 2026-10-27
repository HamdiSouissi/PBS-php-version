<?php

declare(strict_types=1);

namespace Modules\Mail\Tests\Unit;

use Modules\Mail\Mime\MimeBuilder;
use Modules\Mail\Mime\MimeParser;
use Tests\Core\TestCase;

final class MimeBuilderTest extends TestCase
{
    public function testBuildsMixedMessageWithHtmlAndAttachment(): void
    {
        $message = (new MimeBuilder())->build([
            'from' => ['name' => 'Jean Dupont', 'email' => 'jean@pbs.fr'],
            'to' => [['name' => 'Marie Curie', 'email' => 'marie@pbs.fr']],
            'cc' => [['email' => 'copie@pbs.fr']],
            'subject' => 'Rapport d\'activité',
            'text_body' => 'Version texte',
            'html_body' => '<p>Version HTML</p>',
            'attachments' => [
                ['filename' => 'stats.csv', 'mime_type' => 'text/csv', 'contents' => "a;b\r\n1;2"],
            ],
        ]);

        // Toutes les fins de ligne sont normalisées en CRLF.
        self::assertFalse(str_contains($message, "\n") && !str_contains($message, "\r\n"));
        self::assertStringContainsString('multipart/mixed', $message);
        self::assertStringContainsString('multipart/alternative', $message);
        self::assertStringContainsString('Content-Disposition: attachment; filename="stats.csv"', $message);
        self::assertStringContainsString(chunk_split(base64_encode("a;b\r\n1;2"), 76, "\r\n"), $message);
        self::assertStringContainsString('Cc: copie@pbs.fr', $message);
        self::assertStringContainsString('Jean Dupont <jean@pbs.fr>', $message);
        self::assertStringContainsString('Message-ID:', $message);

        // Le message construit reste cohérent et re-parseable.
        $parsed = (new MimeParser())->parse($message);
        self::assertStringContainsString('Version texte', $parsed->textBody());
        self::assertStringContainsString('<p>Version HTML</p>', $parsed->htmlBody());
        self::assertCount(1, $parsed->attachments());
        self::assertSame("a;b\r\n1;2", $parsed->attachments()[0]->decodedBody());
    }

    public function testTextOnlyMessageUsesSinglePlainPart(): void
    {
        $message = (new MimeBuilder())->build([
            'from' => ['email' => 'jean@pbs.fr'],
            'to' => [['email' => 'marie@pbs.fr']],
            'subject' => 'Simple',
            'text_body' => 'Bonjour',
        ]);

        self::assertStringContainsString('Content-Type: text/plain; charset=UTF-8', $message);
        self::assertFalse(str_contains($message, 'multipart/'));
        self::assertStringContainsString("\r\n\r\nBonjour\r\n", $message);
    }
}
