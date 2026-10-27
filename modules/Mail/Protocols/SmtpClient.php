<?php

declare(strict_types=1);

namespace Modules\Mail\Protocols;

/**
 * Client SMTP minimal en PHP natif (RFC 5321) : EHLO, STARTTLS, AUTH LOGIN,
 * transmission d'un message avec protection des points (dot-stuffing).
 * Instance mono-usage, fermée par quit().
 */
final class SmtpClient
{
    public function __construct(private readonly MailTransport $transport)
    {
    }

    /**
     * @param array{host:string,port:int,encryption:string,timeout?:int} $server
     */
    public function connect(array $server, string $username, string $password): void
    {
        $this->transport->connect(
            $server['host'],
            (int) $server['port'],
            $server['encryption'],
            (int) ($server['timeout'] ?? 15)
        );
        $this->readReply([220]);
        $extensions = $this->ehlo();
        if (($server['encryption'] ?? 'ssl') === 'tls') {
            $this->command('STARTTLS', [220]);
            $this->transport->upgradeToTls();
            $extensions = $this->ehlo();
        }
        if ($username !== '') {
            $this->authenticate($username, $password, $extensions);
        }
    }

    /**
     * Transmet un message prêt (en-têtes + corps en CRLF).
     *
     * @param list<string> $recipients adresses de destination (To/Cc/Bcc)
     */
    public function send(string $from, array $recipients, string $rfc822Message): void
    {
        $this->command('MAIL FROM:<' . $from . '>', [250]);
        foreach ($recipients as $recipient) {
            $this->command('RCPT TO:<' . $recipient . '>', [250, 251]);
        }
        $this->command('DATA', [354]);
        $this->transport->write($this->dotStuff($rfc822Message) . "\r\n.\r\n");
        $this->readReply([250, 251]);
    }

    public function quit(): void
    {
        if (!$this->transport->isConnected()) {
            return;
        }
        try {
            $this->transport->write("QUIT\r\n");
            $this->readReply([221]);
        } catch (ProtocolException) {
            // La fermeture socket reste garantie quoi qu'il arrive.
        } finally {
            $this->transport->close();
        }
    }

    /**
     * @return list<string> lignes d'extensions annoncées par EHLO
     */
    private function ehlo(): array
    {
        [, $raw] = $this->command('EHLO ' . $this->localHost(), [250]);
        return preg_split('/\r\n/', $raw) ?: [];
    }

    /**
     * @param list<string> $extensions
     */
    private function authenticate(string $username, string $password, array $extensions): void
    {
        $supportsAuthLogin = false;
        foreach ($extensions as $line) {
            if (preg_match('/^250[- ]AUTH (.*)$/', $line, $m) && str_contains(strtoupper($m[1]), 'LOGIN')) {
                $supportsAuthLogin = true;
            }
        }
        if (!$supportsAuthLogin) {
            throw new ProtocolException('Le serveur SMTP n\'accepte pas l\'authentification LOGIN.');
        }
        $this->command('AUTH LOGIN', [334]);
        $this->command(base64_encode($username), [334]);
        $this->command(base64_encode($password), [235]);
    }

    /**
     * @param list<int> $expected
     * @return array{0:int,1:string} code, réponse brute
     */
    private function command(string $command, array $expected): array
    {
        $this->transport->write($command . "\r\n");
        return $this->readReply($expected);
    }

    /**
     * Lit une réponse SMTP multiligne (codes suivis d'un tiret) puis
     * vérifie le code final.
     *
     * @param list<int> $expected
     * @return array{0:int,1:string}
     */
    private function readReply(array $expected): array
    {
        $raw = '';
        do {
            $line = $this->transport->readLine();
            $raw .= $line;
        } while (isset($line[3]) && $line[3] === '-');

        $code = (int) substr($raw, 0, 3);
        if (!in_array($code, $expected, true)) {
            throw new ProtocolException('Réponse SMTP inattendue (' . $code . ').');
        }
        return [$code, $raw];
    }

    /**
     * Protège les lignes commençant par un point (RFC 5321 §4.5.2) et
     * normalise les fins de ligne en CRLF.
     */
    private function dotStuff(string $message): string
    {
        $normalized = str_replace(["\r\n", "\r", "\n"], ["\n", "\n", "\r\n"], $message);
        $lines = explode("\r\n", $normalized);
        foreach ($lines as $index => $line) {
            if (str_starts_with($line, '.')) {
                $lines[$index] = '.' . $line;
            }
        }
        return implode("\r\n", $lines);
    }

    private function localHost(): string
    {
        $host = (string) (gethostname() ?: 'localhost');
        return preg_replace('/[^a-z0-9.\-]/i', '', $host) ?: 'localhost';
    }
}
