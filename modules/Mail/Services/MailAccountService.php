<?php

declare(strict_types=1);

namespace Modules\Mail\Services;

use App\Core\Database\Connection;
use App\Core\Encryption\Crypto;
use App\Core\Exception\AppException;
use App\Core\Exception\NotFoundException;
use Modules\Mail\Models\MailAccount;
use Modules\Mail\Repositories\MailAccountRepository;

/**
 * Administration des boîtes mail : création, affectation à un utilisateur,
 * mise à jour et révocation. Les mots de passe IMAP/SMTP sont chiffrés au
 * repos via libsodium ; ils ne sont jamais renvoyés aux vues.
 */
final class MailAccountService
{
    public const ENCRYPTIONS = ['ssl', 'tls', 'none'];

    public function __construct(
        private readonly Connection $db,
        private readonly MailAccountRepository $repository,
        private readonly Crypto $crypto
    ) {
    }

    /**
     * Vue admin : toutes les boîtes, sans aucun secret.
     *
     * @return list<array<string,mixed>>
     */
    public function adminList(): array
    {
        return array_map(static function (array $row): array {
            unset($row['imap_secret'], $row['smtp_secret']);
            return $row;
        }, $this->repository->allWithUsers());
    }

    /**
     * Boîte active affectée à l'utilisateur, sous forme de DTO sans secret.
     */
    public function activeModelForUser(int $userId): ?MailAccount
    {
        $row = $this->repository->activeForUser($userId);
        return $row === null ? null : MailAccount::fromRow($row);
    }

    /**
     * Ligne complète (secrets chiffrés inclus) pour l'ouverture d'une
     * connexion par le MailboxService. Réservée au contexte utilisateur propriétaire.
     *
     * @return array<string,mixed>
     */
    public function requireActiveRowForUser(int $userId): array
    {
        $row = $this->repository->activeForUser($userId);
        if ($row === null) {
            throw new AppException("Aucune boîte mail n'est affectée à votre compte. Contactez un administrateur.");
        }
        return $row;
    }

    public function imapPassword(array $row): string
    {
        return $this->crypto->decrypt((string) $row['imap_secret']);
    }

    public function smtpPassword(array $row): string
    {
        return $this->crypto->decrypt((string) $row['smtp_secret']);
    }

    /**
     * @param array<string,mixed> $data
     */
    public function create(array $data, int $adminId): int
    {
        $data = $this->normalized($data);
        if ($data['imap_password'] === '' || $data['smtp_password'] === '') {
            throw new AppException('Les mots de passe IMAP et SMTP sont obligatoires à la création.');
        }
        $this->assertUserFree((int) $data['user_id']);
        $this->assertEmailNotShared((string) $data['email'], 0);

        $this->db->execute(
            'INSERT INTO mail_accounts
                (user_id, email, display_name, imap_host, imap_port, imap_encryption, imap_username, imap_secret,
                 smtp_host, smtp_port, smtp_encryption, smtp_username, smtp_secret, is_active)
             VALUES
                (:user_id, :email, :display_name, :imap_host, :imap_port, :imap_encryption, :imap_username, :imap_secret,
                 :smtp_host, :smtp_port, :smtp_encryption, :smtp_username, :smtp_secret, :is_active)',
            [
                'user_id' => $data['user_id'],
                'email' => $data['email'],
                'display_name' => $data['display_name'],
                'imap_host' => $data['imap_host'],
                'imap_port' => $data['imap_port'],
                'imap_encryption' => $data['imap_encryption'],
                'imap_username' => $data['imap_username'],
                'imap_secret' => $this->crypto->encrypt($data['imap_password']),
                'smtp_host' => $data['smtp_host'],
                'smtp_port' => $data['smtp_port'],
                'smtp_encryption' => $data['smtp_encryption'],
                'smtp_username' => $data['smtp_username'],
                'smtp_secret' => $this->crypto->encrypt($data['smtp_password']),
                'is_active' => $data['is_active'],
            ]
        );
        $id = (int) $this->db->lastInsertId();
        audit('mail.account.create', 'MailAccount', $id, [
            'user_id' => $data['user_id'], 'email' => $data['email'], 'by' => $adminId,
        ]);
        return $id;
    }

    /**
     * Met à jour une boîte. Un mot de passe laissé vide conserve le secret
     * existant ; sinon le nouveau secret remplace l'ancien.
     *
     * @param array<string,mixed> $data
     */
    public function update(int $id, array $data, int $adminId): void
    {
        $existing = $this->requireRow($id);
        $data = $this->normalized($data, $existing);
        $this->assertUserFree((int) $data['user_id'], $id);
        $this->assertEmailNotShared((string) $data['email'], $id);

        $params = [
            'id' => $id,
            'user_id' => $data['user_id'],
            'email' => $data['email'],
            'display_name' => $data['display_name'],
            'imap_host' => $data['imap_host'],
            'imap_port' => $data['imap_port'],
            'imap_encryption' => $data['imap_encryption'],
            'imap_username' => $data['imap_username'],
            'smtp_host' => $data['smtp_host'],
            'smtp_port' => $data['smtp_port'],
            'smtp_encryption' => $data['smtp_encryption'],
            'smtp_username' => $data['smtp_username'],
            'is_active' => $data['is_active'],
        ];
        $secretAssignments = '';
        if ($data['imap_password'] !== '') {
            $params['imap_secret'] = $this->crypto->encrypt($data['imap_password']);
            $secretAssignments .= ', imap_secret = :imap_secret';
        }
        if ($data['smtp_password'] !== '') {
            $params['smtp_secret'] = $this->crypto->encrypt($data['smtp_password']);
            $secretAssignments .= ', smtp_secret = :smtp_secret';
        }

        $this->db->execute(
            "UPDATE mail_accounts SET
                user_id = :user_id, email = :email, display_name = :display_name,
                imap_host = :imap_host, imap_port = :imap_port, imap_encryption = :imap_encryption,
                imap_username = :imap_username,
                smtp_host = :smtp_host, smtp_port = :smtp_port, smtp_encryption = :smtp_encryption,
                smtp_username = :smtp_username, is_active = :is_active,
                updated_at = :now
                {$secretAssignments}
             WHERE id = :id",
            $params + ['now' => date('Y-m-d H:i:s')]
        );
        audit('mail.account.update', 'MailAccount', $id, [
            'user_id' => $data['user_id'], 'email' => $data['email'],
            'imap_secret_rotated' => $data['imap_password'] !== '',
            'smtp_secret_rotated' => $data['smtp_password'] !== '',
            'by' => $adminId,
        ]);
    }

    public function delete(int $id, int $adminId): void
    {
        $existing = $this->requireRow($id);
        $this->db->execute('DELETE FROM mail_accounts WHERE id = :id', ['id' => $id]);
        audit('mail.account.delete', 'MailAccount', $id, [
            'user_id' => $existing['user_id'], 'email' => $existing['email'], 'by' => $adminId,
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    public function requireRow(int $id): array
    {
        $row = $this->repository->find($id);
        if ($row === null) {
            throw new NotFoundException('Boîte mail introuvable.');
        }
        return $row;
    }

    private function assertUserFree(int $userId, int $exceptId = 0): void
    {
        $row = $this->db->fetchOne(
            'SELECT id FROM mail_accounts WHERE user_id = :user AND id <> :id LIMIT 1',
            ['user' => $userId, 'id' => $exceptId]
        );
        if ($row !== null) {
            throw new AppException('Cet utilisateur possède déjà une boîte mail. Réaffectez ou modifiez la boîte existante.');
        }
    }

    private function assertEmailNotShared(string $email, int $exceptId): void
    {
        $row = $this->db->fetchOne(
            'SELECT id FROM mail_accounts WHERE email = :email AND id <> :id LIMIT 1',
            ['email' => $email, 'id' => $exceptId]
        );
        if ($row !== null) {
            throw new AppException('Cette adresse mail est déjà configurée sur une autre boîte.');
        }
    }

    /**
     * Normalise et borne les valeurs du formulaire, avec valeurs IONOS par
     * défaut issues de la configuration centrale.
     *
     * @param array<string,mixed> $data
     * @param array<string,mixed>|null $existing
     * @return array<string,mixed>
     */
    private function normalized(array $data, ?array $existing = null): array
    {
        $defaults = config('mail.mailbox');
        $get = static fn (string $key, mixed $fallback) => isset($data[$key]) && $data[$key] !== '' ? $data[$key] : $fallback;

        $imapEncryption = (string) $get('imap_encryption', $defaults['imap']['encryption']);
        $smtpEncryption = (string) $get('smtp_encryption', $defaults['smtp']['encryption']);
        if (!in_array($imapEncryption, self::ENCRYPTIONS, true)
            || !in_array($smtpEncryption, self::ENCRYPTIONS, true)) {
            throw new AppException("Mode de chiffrement inconnu (ssl, tls ou none attendu).");
        }

        $email = trim((string) ($data['email'] ?? ''));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new AppException('Adresse e-mail invalide.');
        }
        $userId = (int) ($data['user_id'] ?? 0);
        if ($userId <= 0) {
            throw new AppException('Sélectionnez l\'utilisateur affecté à cette boîte.');
        }

        return [
            'user_id' => $userId,
            'email' => $email,
            'display_name' => trim((string) ($data['display_name'] ?? '')),
            'imap_host' => trim((string) $get('imap_host', $defaults['imap']['host'])),
            'imap_port' => (int) $get('imap_port', $defaults['imap']['port']),
            'imap_encryption' => $imapEncryption,
            'imap_username' => trim((string) $get('imap_username', $email)),
            'imap_password' => (string) ($data['imap_password'] ?? ''),
            'smtp_host' => trim((string) $get('smtp_host', $defaults['smtp']['host'])),
            'smtp_port' => (int) $get('smtp_port', $defaults['smtp']['port']),
            'smtp_encryption' => $smtpEncryption,
            'smtp_username' => trim((string) $get('smtp_username', $email)),
            'smtp_password' => (string) ($data['smtp_password'] ?? ''),
            'is_active' => !empty($data['is_active']) ? 1 : 0,
        ];
    }
}
