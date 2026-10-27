<?php

declare(strict_types=1);

namespace Modules\Mail\Repositories;

use App\Core\Database\BaseRepository;
use App\Core\Database\Connection;
use Modules\Mail\Models\MailAccount;

/**
 * Accès aux boîtes mail configurées. Les mots de passe chiffrés ne sont
 * exposés que par les méthodes explicites de récupération de secrets.
 */
final class MailAccountRepository extends BaseRepository
{
    public function __construct(Connection $db)
    {
        parent::__construct($db);
    }

    protected function table(): string
    {
        return 'mail_accounts';
    }

    /**
     * Toutes les boîtes, avec le nom de l'utilisateur affecté (vue admin).
     *
     * @return list<array<string,mixed>>
     */
    public function allWithUsers(): array
    {
        return $this->db->fetchAll(
            'SELECT m.*, u.email AS user_email, u.first_name, u.last_name
             FROM mail_accounts m
             JOIN users u ON u.id = m.user_id
             ORDER BY m.id DESC'
        );
    }

    public function findByUser(int $userId): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM mail_accounts WHERE user_id = :user LIMIT 1',
            ['user' => $userId]
        );
    }

    public function activeForUser(int $userId): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM mail_accounts WHERE user_id = :user AND is_active = 1 LIMIT 1',
            ['user' => $userId]
        );
    }

    public function isAssignedToOtherUser(string $email, int $userId): bool
    {
        return (int) $this->db->fetchScalar(
            'SELECT COUNT(*) FROM mail_accounts WHERE email = :email AND user_id <> :user',
            ['email' => $email, 'user' => $userId]
        ) > 0;
    }

    public function touchSync(int $id): void
    {
        $this->db->execute(
            'UPDATE mail_accounts SET last_sync_at = :now, updated_at = :now WHERE id = :id',
            ['now' => date('Y-m-d H:i:s'), 'id' => $id]
        );
    }

    public function toModel(array $row): MailAccount
    {
        return MailAccount::fromRow($row);
    }
}
