<?php

declare(strict_types=1);

namespace Modules\Mail\Tests\Fixtures;

use Modules\Mail\Protocols\MailTransport;
use Modules\Mail\Protocols\ProtocolException;

/**
 * Socket scripté pour les tests : les réponses du serveur sont mises en
 * file sous forme d'octets, et tout ce que le client écrit est enregistré
 * pour assertions. Aucun accès réseau.
 */
final class FakeTransport implements MailTransport
{
    private string $incoming = '';

    private string $outgoing = '';

    private bool $upgraded = false;

    /** @var array{host:string,port:int,encryption:string,timeout:int}|null */
    private ?array $connection = null;

    public function queue(string $raw): void
    {
        $this->incoming .= $raw;
    }

    public function queueLine(string $line): void
    {
        $this->incoming .= $line . "\r\n";
    }

    public function connect(string $host, int $port, string $encryption, int $timeout): void
    {
        $this->connection = ['host' => $host, 'port' => $port, 'encryption' => $encryption, 'timeout' => $timeout];
    }

    public function upgradeToTls(): void
    {
        $this->upgraded = true;
    }

    public function write(string $data): void
    {
        $this->outgoing .= $data;
    }

    public function readLine(): string
    {
        $position = strpos($this->incoming, "\r\n");
        if ($position === false) {
            throw new ProtocolException('Aucune réponse scriptée supplémentaire (transport factice).');
        }
        $line = substr($this->incoming, 0, $position + 2);
        $this->incoming = substr($this->incoming, $position + 2);
        return $line;
    }

    public function readBytes(int $length): string
    {
        if ($length <= 0) {
            return '';
        }
        if (strlen($this->incoming) < $length) {
            throw new ProtocolException('Littéral scripté incomplet (transport factice).');
        }
        $chunk = substr($this->incoming, 0, $length);
        $this->incoming = substr($this->incoming, $length);
        return $chunk;
    }

    public function isConnected(): bool
    {
        return $this->connection !== null;
    }

    public function close(): void
    {
        $this->connection = null;
    }

    public function outgoing(): string
    {
        return $this->outgoing;
    }

    public function wasUpgraded(): bool
    {
        return $this->upgraded;
    }

    public function connection(): ?array
    {
        return $this->connection;
    }

    public function remaining(): string
    {
        return $this->incoming;
    }
}
