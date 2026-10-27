<?php

declare(strict_types=1);

namespace Modules\Mail\Protocols;

/**
 * Transport de production : socket TCP/TLS native PHP, sans extension imap.
 * La vérification du certificat (autorité + nom d'hôte) reste activée en
 * permanence : les hébergeurs comme IONOS présentent des certificats valides.
 */
final class StreamTransport implements MailTransport
{
    /** @var resource|null */
    private $stream = null;

    private string $host = '';

    private int $timeout = 15;

    public function connect(string $host, int $port, string $encryption, int $timeout): void
    {
        if ($this->stream !== null) {
            $this->close();
        }
        $this->host = $host;
        $this->timeout = $timeout > 0 ? $timeout : 15;

        $remote = ($encryption === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false,
                'disable_compression' => true,
                'SNI_server_name' => $host,
            ],
        ]);

        $errno = 0;
        $errstr = '';
        $stream = @stream_socket_client(
            $remote,
            $errno,
            $errstr,
            (float) $this->timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );
        if ($stream === false) {
            throw new ProtocolException("Connexion au serveur de messagerie impossible ({$host}:{$port}).");
        }
        $this->stream = $stream;
        stream_set_timeout($this->stream, $this->timeout);
    }

    public function upgradeToTls(): void
    {
        if ($this->stream === null) {
            throw new ProtocolException('Aucune connexion ouverte à sécuriser.');
        }
        $result = stream_socket_enable_crypto(
            $this->stream,
            true,
            [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false,
                'SNI_server_name' => $this->host,
            ]
        );
        if ($result !== true) {
            throw new ProtocolException('La négociation STARTTLS a échoué.');
        }
    }

    public function write(string $data): void
    {
        if ($this->stream === null) {
            throw new ProtocolException('Aucune connexion ouverte.');
        }
        $total = strlen($data);
        $written = 0;
        while ($written < $total) {
            $chunk = fwrite($this->stream, substr($data, $written));
            if ($chunk === false || $chunk === 0) {
                throw new ProtocolException('Échec d\'écriture sur la connexion mail.');
            }
            $written += $chunk;
        }
    }

    public function readLine(): string
    {
        if ($this->stream === null) {
            throw new ProtocolException('Aucune connexion ouverte.');
        }
        $line = fgets($this->stream);
        if ($line === false) {
            $meta = stream_get_meta_data($this->stream);
            if (!empty($meta['timed_out'])) {
                throw new ProtocolException('Délai d\'attente dépassé sur la connexion mail.');
            }
            throw new ProtocolException('La connexion mail a été interrompue.');
        }
        return $line;
    }

    public function readBytes(int $length): string
    {
        if ($this->stream === null || $length <= 0) {
            return '';
        }
        $buffer = '';
        while (strlen($buffer) < $length) {
            $chunk = fread($this->stream, $length - strlen($buffer));
            if ($chunk === false || $chunk === '') {
                throw new ProtocolException('Littéral IMAP incomplet ou connexion interrompue.');
            }
            $buffer .= $chunk;
        }
        return $buffer;
    }

    public function isConnected(): bool
    {
        return $this->stream !== null;
    }

    public function close(): void
    {
        if ($this->stream !== null && is_resource($this->stream)) {
            fclose($this->stream);
        }
        $this->stream = null;
    }
}
