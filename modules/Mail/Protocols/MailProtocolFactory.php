<?php

declare(strict_types=1);

namespace Modules\Mail\Protocols;

/**
 * Fabrique les clients IMAP/SMTP connectés d'une boîte donnée. Passer par
 * une fabrique concrete (autocâblée par le conteneur) évite de lier
 * l'interface MailTransport dans le noyau : les tests remplacent la
 * création du transport par une socket scriptée.
 */
class MailProtocolFactory
{
    /**
     * @param array{host:string,port:int,encryption:string,timeout?:int} $server
     */
    public function connectImap(array $server, string $username, string $secret): ImapClient
    {
        $client = new ImapClient($this->createTransport());
        $client->connect($server, $username, $secret);
        return $client;
    }

    /**
     * @param array{host:string,port:int,encryption:string,timeout?:int} $server
     */
    public function connectSmtp(array $server, string $username, string $secret): SmtpClient
    {
        $client = new SmtpClient($this->createTransport());
        $client->connect($server, $username, $secret);
        return $client;
    }

    protected function createTransport(): MailTransport
    {
        return new StreamTransport();
    }
}
