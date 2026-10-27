<?php

declare(strict_types=1);

namespace Modules\Mail\Models;

/**
 * Boîte mail d'entreprise affectée à un utilisateur. Les mots de passe
 * IMAP/SMTP sont chiffrés au repos et n'apparaissent jamais dans ce DTO :
 * le service les déchiffre uniquement au moment d'ouvrir une connexion.
 */
final class MailAccount
{
    public function __construct(
        public readonly int $id,
        public readonly int $userId,
        public readonly string $email,
        public readonly string $displayName,
        public readonly string $imapHost,
        public readonly int $imapPort,
        public readonly string $imapEncryption,
        public readonly string $imapUsername,
        public readonly string $smtpHost,
        public readonly int $smtpPort,
        public readonly string $smtpEncryption,
        public readonly string $smtpUsername,
        public readonly bool $isActive,
        public readonly ?string $lastSyncAt
    ) {
    }

    /**
     * Construit le DTO depuis une ligne de table (les colonnes secrètes
     * restent volontairement hors du DTO).
     *
     * @param array<string,mixed> $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (int) $row['user_id'],
            (string) $row['email'],
            (string) ($row['display_name'] ?? ''),
            (string) $row['imap_host'],
            (int) $row['imap_port'],
            (string) $row['imap_encryption'],
            (string) $row['imap_username'],
            (string) $row['smtp_host'],
            (int) $row['smtp_port'],
            (string) $row['smtp_encryption'],
            (string) $row['smtp_username'],
            (bool) (int) ($row['is_active'] ?? 1),
            isset($row['last_sync_at']) && $row['last_sync_at'] !== null ? (string) $row['last_sync_at'] : null
        );
    }

    /**
     * Libellé d'expédition : « Nom affiché <adresse> » ou l'adresse seule.
     */
    public function senderLabel(): string
    {
        return $this->displayName !== ''
            ? $this->displayName . ' <' . $this->email . '>'
            : $this->email;
    }
}
