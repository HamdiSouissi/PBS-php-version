<?php

declare(strict_types=1);

namespace Modules\Mail\Tests\Fixtures;

use Modules\Mail\Protocols\ImapClient;
use Modules\Mail\Protocols\MailProtocolFactory;
use Modules\Mail\Protocols\SmtpClient;

/**
 * Fabrique de clients branchée sur des transports factices : chaque appel
 * relit le scénario enregistré dans le FakeTransport correspondant.
 */
final class FakeProtocolFactory extends MailProtocolFactory
{
    public function __construct(
        private readonly FakeTransport $imapTransport,
        private readonly FakeTransport $smtpTransport
    ) {
    }

    public function connectImap(array $server, string $username, string $secret): ImapClient
    {
        $client = new ImapClient($this->imapTransport);
        $client->connect($server, $username, $secret);
        return $client;
    }

    public function connectSmtp(array $server, string $username, string $secret): SmtpClient
    {
        $client = new SmtpClient($this->smtpTransport);
        $client->connect($server, $username, $secret);
        return $client;
    }

    public function imapTransport(): FakeTransport
    {
        return $this->imapTransport;
    }

    public function smtpTransport(): FakeTransport
    {
        return $this->smtpTransport;
    }
}
