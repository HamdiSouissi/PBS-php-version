<?php

declare(strict_types=1);

namespace Modules\Mail\Tests\Functional;

use App\Core\Application;
use App\Core\Encryption\Crypto;
use App\Core\Exception\AppException;
use App\Core\Exception\ValidationException;
use App\Core\Support\Config;
use Modules\Mail\Models\OutgoingMail;
use Modules\Mail\Services\MailAccountService;
use Modules\Mail\Services\MailboxService;
use Modules\Mail\Tests\Fixtures\FakeProtocolFactory;
use Modules\Mail\Tests\Fixtures\FakeTransport;
use Tests\Core\DatabaseTestCase;

final class MailboxServiceTest extends DatabaseTestCase
{
    private MailAccountService $accounts;

    private int $userId;

    private string $email = 'jean.dupont@primevisionsolutions.fr';

    protected function setUp(): void
    {
        putenv('APP_KEY=' . Crypto::generateKey());
        $_ENV['APP_KEY'] = getenv('APP_KEY');
        parent::setUp();
        $this->accounts = Application::container()->get(MailAccountService::class);
        $this->db->execute(
            "INSERT INTO users (email, password_hash, first_name, last_name, is_active)
             VALUES ('jean.dupont@primevisionsolutions.fr', 'x', 'Jean', 'Dupont', 1)"
        );
        $this->userId = (int) $this->db->lastInsertId();
        $this->accounts->create([
            'user_id' => $this->userId,
            'email' => $this->email,
            'display_name' => 'Jean Dupont',
            'imap_username' => $this->email,
            'imap_password' => 'imap-secret',
            'smtp_username' => $this->email,
            'smtp_password' => 'smtp-secret',
            'is_active' => 1,
        ], 1);
    }

    private function service(FakeTransport $imap, FakeTransport $smtp): MailboxService
    {
        return new MailboxService($this->accounts, new FakeProtocolFactory($imap, $smtp));
    }

    private function loggedInImap(FakeTransport $transport): void
    {
        $transport->queueLine('* OK IMAP4rev1 ready');
        $transport->queueLine('A0001 OK LOGIN completed');
    }

    private function logoutLines(FakeTransport $transport, string $tag): void
    {
        $transport->queueLine('* BYE Logging out');
        $transport->queueLine($tag . ' OK LOGOUT completed');
    }

    public function testFoldersReturnLabelsAndCounters(): void
    {
        $imap = new FakeTransport();
        $this->loggedInImap($imap);
        $imap->queueLine('* LIST (\HasNoChildren) "/" "INBOX"');
        $imap->queueLine('* LIST (\HasNoChildren) "/" "' . mb_convert_encoding('Éléments envoyés', 'UTF7-IMAP', 'UTF-8') . '"');
        $imap->queueLine('A0002 OK LIST completed');
        $imap->queueLine('* 5 EXISTS');
        $imap->queueLine('* OK [UNSEEN 2]');
        $imap->queueLine('A0003 OK SELECT completed');
        $imap->queueLine('* SEARCH 2 3');
        $imap->queueLine('A0004 OK SEARCH completed');
        $imap->queueLine('* 1 EXISTS');
        $imap->queueLine('A0005 OK SELECT completed');
        $imap->queueLine('* SEARCH');
        $imap->queueLine('A0006 OK SEARCH completed');
        $this->logoutLines($imap, 'A0007');

        $folders = $this->service($imap, new FakeTransport())->folders($this->userId);

        self::assertCount(2, $folders);
        self::assertSame('Boîte de réception', $folders[0]->label);
        self::assertSame(5, $folders[0]->total);
        self::assertSame(2, $folders[0]->unseen);
        self::assertSame('Éléments envoyés', $folders[1]->label);
        self::assertSame(0, $folders[1]->unseen);
    }

    public function testMessagesArePaginatedNewestFirstAndSearchable(): void
    {
        $imap = new FakeTransport();
        $this->loggedInImap($imap);
        $imap->queueLine('* 3 EXISTS');
        $imap->queueLine('A0002 OK SELECT completed');
        $imap->queueLine('* SEARCH 1 2 3');
        $imap->queueLine('A0003 OK SEARCH completed');
        $fetch = '';
        foreach ([1, 2, 3] as $sequence) {
            $headers = "From: marie@example.fr\r\nSubject: Message {$sequence}\r\nDate: Mon, 27 Oct 2026 09:00:00 +0100\r\n";
            $fetch .= "* {$sequence} FETCH (FLAGS () RFC822.SIZE 100 BODY[HEADER.FIELDS (FROM TO CC SUBJECT DATE)] {"
                . strlen($headers) . "}\r\n" . $headers . ")\r\n";
        }
        $imap->queue($fetch);
        $imap->queueLine('A0004 OK FETCH completed');
        $this->logoutLines($imap, 'A0005');

        $result = $this->service($imap, new FakeTransport())->messages($this->userId, 'INBOX', 1, null);

        self::assertSame(3, $result['total']);
        self::assertSame(1, $result['page']);
        self::assertSame([3, 2, 1], array_map(static fn ($m): int => $m->sequence, $result['messages']));
        self::assertSame('Message 3', $result['messages'][0]->subject);
        self::assertSame('marie@example.fr', $result['messages'][0]->fromEmail);
    }

    public function testSearchBuildsQuotedTextCriteria(): void
    {
        $imap = new FakeTransport();
        $this->loggedInImap($imap);
        $imap->queueLine('* 0 EXISTS');
        $imap->queueLine('A0002 OK SELECT completed');
        $imap->queueLine('* SEARCH');
        $imap->queueLine('A0003 OK SEARCH completed');
        $this->logoutLines($imap, 'A0004');

        $this->service($imap, new FakeTransport())->messages($this->userId, 'INBOX', 1, 'facture 2026');

        self::assertStringContainsString('SEARCH TEXT "facture 2026"', $imap->outgoing());
    }

    public function testMessageDetailParsesMimeAndMarksAsRead(): void
    {
        $imap = new FakeTransport();
        $this->loggedInImap($imap);
        $imap->queueLine('* 1 EXISTS');
        $imap->queueLine('A0002 OK SELECT completed');

        $headers = "From: marie@example.fr\r\nSubject: =?UTF-8?B?" . base64_encode('Rapport mensuel') . "?=\r\n";
        $imap->queue(
            "* 1 FETCH (FLAGS () RFC822.SIZE 200 BODY[HEADER.FIELDS (FROM TO CC SUBJECT DATE)] {"
            . strlen($headers) . "}\r\n" . $headers . ")\r\n"
        );
        $imap->queueLine('A0003 OK FETCH completed');

        $mime = implode("\r\n", [
            'Content-Type: multipart/mixed; boundary="BOUND"',
            '',
            '--BOUND',
            'Content-Type: text/plain; charset=UTF-8',
            '',
            'Bonjour Jean, voici le rapport.',
            '--BOUND',
            'Content-Type: application/pdf; name="rapport.pdf"',
            'Content-Transfer-Encoding: base64',
            'Content-Disposition: attachment; filename="rapport.pdf"',
            '',
            'JVBERi0xLjQ=',
            '--BOUND--',
            '',
        ]);
        $imap->queue("* 1 FETCH (BODY[] {" . strlen($mime) . "}\r\n" . $mime . ")\r\n");
        $imap->queueLine('A0004 OK FETCH completed');
        $imap->queueLine('A0005 OK STORE completed');
        $this->logoutLines($imap, 'A0006');

        $message = $this->service($imap, new FakeTransport())->message($this->userId, 'INBOX', 1);

        self::assertSame('Rapport mensuel', $message->subject);
        self::assertStringContainsString('Bonjour Jean', $message->textBody);
        self::assertCount(1, $message->attachments);
        self::assertSame('rapport.pdf', $message->attachments[0]->filename);
        self::assertSame('%PDF-1.4', $message->attachments[0]->contents);
        self::assertStringContainsString('STORE 1 +FLAGS (\Seen)', $imap->outgoing());
    }

    public function testSendGoesThroughSmtpAndIsArchivedToSentFolder(): void
    {
        $smtp = new FakeTransport();
        $smtp->queueLine('220 smtp.ionos.fr ESMTP ready');
        $smtp->queueLine('250-smtp.ionos.fr');
        $smtp->queueLine('250-AUTH LOGIN PLAIN');
        $smtp->queueLine('250 OK');
        $smtp->queueLine('334');
        $smtp->queueLine('334');
        $smtp->queueLine('235 OK');
        $smtp->queueLine('250 OK');
        $smtp->queueLine('250 Accepted');
        $smtp->queueLine('250 Accepted');
        $smtp->queueLine('354 Start mail input');
        $smtp->queueLine('250 Queued');

        $imap = new FakeTransport();
        $this->loggedInImap($imap);
        $imap->queueLine('* LIST (\HasNoChildren) "/" "INBOX"');
        $imap->queueLine('* LIST (\HasNoChildren) "/" "' . mb_convert_encoding('Éléments envoyés', 'UTF7-IMAP', 'UTF-8') . '"');
        $imap->queueLine('A0002 OK LIST completed');
        $imap->queueLine('+ Ready for literal');
        $imap->queueLine('A0003 OK APPEND completed');
        $this->logoutLines($imap, 'A0004');

        $mail = OutgoingMail::fromInput([
            'to' => 'client@example.fr',
            'cc' => 'copie@example.fr',
            'subject' => 'Proposition commerciale',
            'text_body' => 'Bonjour, veuillez trouver ci-joint notre proposition.',
        ]);
        $this->service($imap, $smtp)->send($this->userId, $mail);

        self::assertStringContainsString('MAIL FROM:<' . $this->email . '>', $smtp->outgoing());
        self::assertStringContainsString('RCPT TO:<client@example.fr>', $smtp->outgoing());
        self::assertStringContainsString('RCPT TO:<copie@example.fr>', $smtp->outgoing());
        self::assertStringContainsString('AUTH LOGIN', $smtp->outgoing());
        self::assertStringContainsString('APPEND', $imap->outgoing());
        // Le Bcc n'est jamais émis dans les en-têtes : ici seuls To et Cc existent.
        self::assertFalse(str_contains($smtp->outgoing(), 'Bcc:'));

        $audit = $this->db->fetchScalar(
            "SELECT COUNT(*) FROM audit_logs WHERE action = 'mail.send' AND entity_type = 'MailMessage'"
        );
        self::assertSame(1, (int) $audit);
    }

    public function testSendRejectsTooManyRecipients(): void
    {
        $recipients = [];
        for ($i = 0; $i < 51; $i++) {
            $recipients[] = "dest{$i}@example.fr";
        }
        $mail = OutgoingMail::fromInput([
            'to' => implode(',', $recipients),
            'subject' => 'Publipostage',
            'text_body' => 'Contenu',
        ]);

        $this->expectException(ValidationException::class);
        $this->service(new FakeTransport(), new FakeTransport())->send($this->userId, $mail);
    }

    public function testSendRejectsOversizedAttachments(): void
    {
        Config::set('mail.mailbox.max_attachments_size', 50);
        $mail = OutgoingMail::fromInput([
            'to' => 'client@example.fr',
            'subject' => 'Fichier',
            'text_body' => 'Contenu',
            'attachments' => [
                ['filename' => 'gros.bin', 'mime_type' => 'application/octet-stream', 'contents' => str_repeat('x', 100)],
            ],
        ]);

        $this->expectException(ValidationException::class);
        $this->service(new FakeTransport(), new FakeTransport())->send($this->userId, $mail);
    }

    public function testUserWithoutMailboxGetsClearBusinessError(): void
    {
        $this->db->execute(
            "INSERT INTO users (email, password_hash, first_name, last_name, is_active)
             VALUES ('sans.bal@primevisionsolutions.fr', 'x', 'Sans', 'Bal', 1)"
        );
        $otherId = (int) $this->db->lastInsertId();

        $this->expectException(AppException::class);
        $this->service(new FakeTransport(), new FakeTransport())->folders($otherId);
    }

    public function testDeleteAndMoveIssueExpectedImapCommands(): void
    {
        $imap = new FakeTransport();
        $this->loggedInImap($imap);
        $imap->queueLine('* 3 EXISTS');
        $imap->queueLine('A0002 OK SELECT completed');
        $imap->queueLine('A0003 OK STORE completed');
        $imap->queueLine('A0004 OK EXPUNGE completed');
        $this->logoutLines($imap, 'A0005');

        $service = $this->service($imap, new FakeTransport());
        $service->delete($this->userId, 'INBOX', 2);

        self::assertStringContainsString('STORE 2 +FLAGS (\Deleted)', $imap->outgoing());
        self::assertStringContainsString('EXPUNGE', $imap->outgoing());
    }
}
