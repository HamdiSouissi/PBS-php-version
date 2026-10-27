<?php

declare(strict_types=1);

namespace Modules\Mail\Tests\Functional;

use Modules\Mail\Protocols\ImapClient;
use Modules\Mail\Protocols\ProtocolException;
use Modules\Mail\Tests\Fixtures\FakeTransport;
use Tests\Core\TestCase;

final class ImapClientTest extends TestCase
{
    private function client(FakeTransport $transport, string $encryption = 'ssl'): ImapClient
    {
        $client = new ImapClient($transport);
        $client->connect(
            ['host' => 'imap.ionos.fr', 'port' => 993, 'encryption' => $encryption, 'timeout' => 5],
            'jean@primevisionsolutions.fr',
            'mot-de-passe'
        );
        return $client;
    }

    public function testLoginAndListFolders(): void
    {
        $transport = new FakeTransport();
        $transport->queueLine('* OK IMAP4rev1 ready');
        $transport->queueLine('A0001 OK LOGIN completed');
        $transport->queueLine('* LIST (\HasNoChildren) "/" "INBOX"');
        $sentUtf7 = mb_convert_encoding('Éléments envoyés', 'UTF7-IMAP', 'UTF-8');
        $transport->queueLine('* LIST (\HasNoChildren) "/" "' . $sentUtf7 . '"');
        $transport->queueLine('A0002 OK LIST completed');

        $folders = $this->client($transport)->listFolders();

        self::assertCount(2, $folders);
        self::assertSame('INBOX', $folders[0]['name']);
        self::assertSame('Éléments envoyés', $folders[1]['name']);
        self::assertStringContainsString('A0001 LOGIN "jean@primevisionsolutions.fr" "mot-de-passe"', $transport->outgoing());
        self::assertStringContainsString('LIST "" "*"', $transport->outgoing());
    }

    public function testSelectSearchAndFetchEnvelopes(): void
    {
        $transport = new FakeTransport();
        $transport->queueLine('* OK IMAP4rev1 ready');
        $transport->queueLine('A0001 OK LOGIN completed');
        $transport->queueLine('* 3 EXISTS');
        $transport->queueLine('* OK [UNSEEN 2] Message 2 first unseen');
        $transport->queueLine('A0002 OK [READ-WRITE] SELECT completed');
        $transport->queueLine('* SEARCH 1 2 3');
        $transport->queueLine('A0003 OK SEARCH completed');

        $subjectEncoded = '=?UTF-8?B?' . base64_encode('Bâtiment juillet') . '?=';
        $headerBlock = "From: Marie Curie <marie@example.fr>\r\n"
            . 'To: jean@primevisionsolutions.fr' . "\r\n"
            . 'Subject: ' . $subjectEncoded . "\r\n"
            . "Date: Mon, 27 Oct 2026 09:30:00 +0100\r\n";
        $transport->queue(
            "* 1 FETCH (FLAGS (\Seen) INTERNALDATE \"27-Oct-2026 09:30:00 +0100\" RFC822.SIZE 1234 "
            . "BODY[HEADER.FIELDS (FROM TO CC SUBJECT DATE)] {" . strlen($headerBlock) . "}\r\n"
            . $headerBlock
            . ")\r\n"
        );
        $transport->queueLine('A0004 OK FETCH completed');

        $client = $this->client($transport);
        $select = $client->select('INBOX');
        self::assertSame(3, $select['exists']);
        self::assertSame(2, $select['unseen_id']);

        $ids = $client->search('ALL');
        self::assertSame([1, 2, 3], $ids);

        $envelopes = $client->fetchEnvelopes([1]);
        self::assertCount(1, $envelopes);
        self::assertSame(1, $envelopes[0]['sequence']);
        self::assertTrue($envelopes[0]['seen']);
        self::assertSame(1234, $envelopes[0]['size']);
        self::assertSame('Bâtiment juillet', $envelopes[0]['headers']['subject']);
        self::assertStringContainsString('marie@example.fr', $envelopes[0]['headers']['from']);
    }

    public function testMarkReadDeleteAndMoveWithFallback(): void
    {
        $transport = new FakeTransport();
        $transport->queueLine('* OK IMAP4rev1 ready');
        $transport->queueLine('A0001 OK LOGIN completed');
        $transport->queueLine('A0002 OK STORE completed');
        $transport->queueLine('A0003 OK STORE completed');
        $transport->queueLine('A0004 OK EXPUNGE completed');
        // MOVE inconnu du serveur : repli COPY + \Deleted + EXPUNGE.
        $transport->queueLine('A0005 BAD Unknown command MOVE');
        $transport->queueLine('A0006 OK COPY completed');
        $transport->queueLine('A0007 OK STORE completed');
        $transport->queueLine('A0008 OK EXPUNGE completed');

        $client = $this->client($transport);
        $client->markRead(7);
        $client->delete(7);
        $client->move(7, 'Corbeille');

        $out = $transport->outgoing();
        self::assertStringContainsString('STORE 7 +FLAGS (\Seen)', $out);
        self::assertStringContainsString('STORE 7 +FLAGS (\Deleted)', $out);
        self::assertStringContainsString('EXPUNGE', $out);
        self::assertStringContainsString('MOVE 7 "Corbeille"', $out);
        self::assertStringContainsString('COPY 7 "Corbeille"', $out);
    }

    public function testAppendAndLogout(): void
    {
        $transport = new FakeTransport();
        $transport->queueLine('* OK IMAP4rev1 ready');
        $transport->queueLine('A0001 OK LOGIN completed');
        $transport->queueLine('+ Ready for literal data');
        $transport->queueLine('A0002 OK APPEND completed');
        $transport->queueLine('* BYE Logging out');
        $transport->queueLine('A0003 OK LOGOUT completed');

        $client = $this->client($transport);
        $message = "From: jean@primevisionsolutions.fr\r\nSubject: test\r\n\r\nBonjour";
        $client->append('Éléments envoyés', $message);
        $client->logout();

        $out = $transport->outgoing();
        $expectedUtf7 = mb_convert_encoding('Éléments envoyés', 'UTF7-IMAP', 'UTF-8');
        self::assertStringContainsString('APPEND "' . $expectedUtf7 . '" (\Seen) {' . strlen($message) . '}', $out);
        self::assertStringContainsString($message, $out);
        self::assertStringContainsString('LOGOUT', $out);
        self::assertFalse($transport->isConnected());
    }

    public function testStartTlsUpgradesTransportBeforeLogin(): void
    {
        $transport = new FakeTransport();
        $transport->queueLine('* OK IMAP4rev1 ready');
        $transport->queueLine('* CAPABILITY IMAP4rev1 STARTTLS LOGINDISABLED');
        $transport->queueLine('A0001 OK CAPABILITY completed');
        $transport->queueLine('A0002 OK STARTTLS ready');
        $transport->queueLine('A0003 OK LOGIN completed');

        $this->client($transport, 'tls');

        self::assertTrue($transport->wasUpgraded());
        $out = $transport->outgoing();
        self::assertTrue(strpos($out, 'STARTTLS') < strpos($out, 'LOGIN'));
    }

    public function testServerRejectionRaisesProtocolException(): void
    {
        $transport = new FakeTransport();
        $transport->queueLine('* OK IMAP4rev1 ready');
        $transport->queueLine('A0001 NO LOGIN failed');

        $this->expectException(ProtocolException::class);
        new ImapClient($transport)->connect(
            ['host' => 'imap.ionos.fr', 'port' => 993, 'encryption' => 'ssl', 'timeout' => 5],
            'jean@primevisionsolutions.fr',
            'mauvais'
        );
    }
}
