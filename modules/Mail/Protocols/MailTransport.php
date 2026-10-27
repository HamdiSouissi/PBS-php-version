<?php

declare(strict_types=1);

namespace Modules\Mail\Protocols;

/**
 * Abstraction du flux réseau IMAP/SMTP. L'implémentation de production
 * utilise les sockets PHP natifs (StreamTransport) ; les tests injectent
 * un transport factice scripté, sans aucun accès réseau.
 */
interface MailTransport
{
    /**
     * Ouvre la connexion vers le serveur :
     *  - ssl  : TLS implicite (IMAP 993, SMTP 465) via ssl://
     *  - tls  : connexion TCP claire puis STARTTLS négocié par le client
     *  - none : TCP clair (réservé aux environnements de développement)
     */
    public function connect(string $host, int $port, string $encryption, int $timeout): void;

    /** Bascule le flux existant en TLS (réponse à STARTTLS). */
    public function upgradeToTls(): void;

    /** Écrit des octets bruts sur le flux. */
    public function write(string $data): void;

    /** Lit une ligne jusqu'au CRLF inclus (false est transformé en exception). */
    public function readLine(): string;

    /** Lit exactement $length octets (gestion des littéraux IMAP). */
    public function readBytes(int $length): string;

    public function isConnected(): bool;

    public function close(): void;
}
