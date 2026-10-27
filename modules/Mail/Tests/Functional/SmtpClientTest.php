<?php

declare(strict_types=1);

namespace Modules\Mail\Tests\Functional;

use Modules\Mail\Protocols\ProtocolException;
use Modules\Mail\Protocols\SmtpClient;
use Modules\Mail\Tests\Fixtures\FakeTransport;
use Tests\Core\TestCase;

final class SmtpClientTest extends TestCase
{
    public function testAuthenticatesAndSendsWithDotStuffing(): void
    {
        $transport = new FakeTransport();
        $transport->queueLine('220 smtp.ionos.fr ESMTP ready');
        $transport->queueLine('250-smtp.ionos.fr');
        $transport->queueLine('250-AUTH LOGIN PLAIN');
        $transport->queueLine('250 OK');
        $transport->queueLine('334 VXNlcm5hbWU6');
        $transport->queueLine('334 UGFzc3dvcmQ6');
        $transport->queueLine('235 Authentication succeeded');
        $transport->queueLine('250 OK');
        $transport->queueLine('250 Accepted');
        $transport->queueLine('250 Accepted');
        $transport->queueLine('354 Start mail input');
        $transport->queueLine('250 Message accepted for delivery');

        $client = new SmtpClient($transport);
        $client->connect(
            ['host' => 'smtp.ionos.fr', 'port' => 465, 'encryption' => 'ssl', 'timeout' => 5],
            'jean@primevisionsolutions.fr',
            'secret-smtp'
        );

        $message = "From: jean@primevisionsolutions.fr\r\nTo: client@example.fr\r\nSubject: test\r\n\r\n"
            . "Ligne normale\r\n. ligne commençant par un point\r\nFin";
        $client->send('jean@primevisionsolutions.fr', ['client@example.fr', 'copie@example.fr'], $message);
        $client->quit();

        $out = $transport->outgoing();
        self::assertStringContainsString('EHLO ', $out);
        self::assertStringContainsString('AUTH LOGIN', $out);
        self::assertStringContainsString(base64_encode('jean@primevisionsolutions.fr'), $out);
        self::assertStringContainsString(base64_encode('secret-smtp'), $out);
        self::assertStringContainsString('MAIL FROM:<jean@primevisionsolutions.fr>', $out);
        self::assertStringContainsString('RCPT TO:<client@example.fr>', $out);
        self::assertStringContainsString('RCPT TO:<copie@example.fr>', $out);
        self::assertStringContainsString('DATA', $out);
        // Le point initial doit être doublé (dot-stuffing), et le message se termine par CRLF.CRLF.
        self::assertStringContainsString('.. ligne commençant par un point', $out);
        self::assertStringContainsString("\r\n.\r\n", $out);
    }

    public function testStartTlsIsNegotiatedBeforeAuthentication(): void
    {
        $transport = new FakeTransport();
        $transport->queueLine('220 smtp.example.fr ESMTP ready');
        $transport->queueLine('250-smtp.example.fr');
        $transport->queueLine('250 STARTTLS');
        $transport->queueLine('220 Ready to start TLS');
        $transport->queueLine('250-smtp.example.fr');
        $transport->queueLine('250-AUTH LOGIN');
        $transport->queueLine('250 OK');
        $transport->queueLine('334');
        $transport->queueLine('334');
        $transport->queueLine('235 OK');

        $client = new SmtpClient($transport);
        $client->connect(
            ['host' => 'smtp.example.fr', 'port' => 587, 'encryption' => 'tls', 'timeout' => 5],
            'user@example.fr',
            'pw'
        );

        self::assertTrue($transport->wasUpgraded());
        $out = $transport->outgoing();
        self::assertTrue(strpos($out, 'STARTTLS') < strpos($out, 'AUTH LOGIN'));
    }

    public function testUnexpectedReplyRaisesProtocolException(): void
    {
        $transport = new FakeTransport();
        $transport->queueLine('554 Service unavailable');

        $this->expectException(ProtocolException::class);
        (new SmtpClient($transport))->connect(
            ['host' => 'smtp.ionos.fr', 'port' => 465, 'encryption' => 'ssl', 'timeout' => 5],
            'u',
            'p'
        );
    }
}
