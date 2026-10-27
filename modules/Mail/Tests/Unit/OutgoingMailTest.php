<?php

declare(strict_types=1);

namespace Modules\Mail\Tests\Unit;

use App\Core\Exception\ValidationException;
use Modules\Mail\Models\OutgoingMail;
use Tests\Core\TestCase;

final class OutgoingMailTest extends TestCase
{
    public function testValidInputIsParsedWithUniqueRecipients(): void
    {
        $mail = OutgoingMail::fromInput([
            'to' => ['client@example.fr', 'doublon@example.fr'],
            'cc' => 'copie@example.fr, doublon@example.fr',
            'bcc' => 'secret@example.fr',
            'subject' => 'Sujet',
            'text_body' => 'Corps',
        ]);

        self::assertCount(2, $mail->to);
        self::assertCount(2, $mail->cc);
        self::assertCount(1, $mail->bcc);
        self::assertSame(
            ['client@example.fr', 'doublon@example.fr', 'copie@example.fr', 'secret@example.fr'],
            $mail->recipientEmails()
        );
        self::assertSame(4, $mail->totalRecipients());
    }

    public function testMissingRecipientSubjectAndBodyAreRejected(): void
    {
        try {
            OutgoingMail::fromInput(['to' => '', 'subject' => '   ', 'text_body' => '']);
            self::fail('Une ValidationException était attendue.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('to', $exception->errors());
            self::assertArrayHasKey('subject', $exception->errors());
            self::assertArrayHasKey('text_body', $exception->errors());
        }
    }

    public function testInvalidEmailIsRejected(): void
    {
        try {
            OutgoingMail::fromInput(['to' => 'pas-un-email', 'subject' => 'Sujet', 'text_body' => 'Corps']);
            self::fail('Une ValidationException était attendue.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('to', $exception->errors());
        }
    }

    public function testAttachmentSizeIsSummed(): void
    {
        $mail = OutgoingMail::fromInput([
            'to' => 'client@example.fr',
            'subject' => 'Pièces',
            'text_body' => 'Ci-jointes',
            'attachments' => [
                ['filename' => 'a.txt', 'mime_type' => 'text/plain', 'contents' => '12345'],
                ['filename' => 'b.txt', 'mime_type' => 'text/plain', 'contents' => '67890'],
            ],
        ]);

        self::assertSame(10, $mail->attachmentsSize());
        self::assertCount(2, $mail->attachments);
    }
}
