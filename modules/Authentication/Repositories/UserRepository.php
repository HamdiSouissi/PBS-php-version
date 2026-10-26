<?php

declare(strict_types=1);

namespace Modules\Authentication\Repositories;

use App\Core\Database\BaseRepository;
use App\Core\Database\Connection;
use Modules\Authentication\Models\User;

/**
 * Accès aux utilisateurs, rôles et permissions (requêtes PDO explicites).
 */
final class UserRepository extends BaseRepository
{
    private const SELECT_WITH_RIGHTS = "
        SELECT u.*,
               STRING_AGG(DISTINCT r.code, ',') AS roles,
               STRING_AGG(DISTINCT p.code, ',') AS permissions
        FROM users u
        LEFT JOIN role_user ru ON ru.user_id = u.id
        LEFT JOIN roles r ON r.id = ru.role_id
        LEFT JOIN role_permission rp ON rp.role_id = r.id
        LEFT JOIN permissions p ON p.id = rp.permission_id";

    public function __construct(Connection $db)
    {
        parent::__construct($db);
    }

    protected function table(): string
    {
        return 'users';
    }

    public function findByEmail(string $email): ?User
    {
        $row = $this->db->fetchOne(
            self::SELECT_WITH_RIGHTS . ' WHERE u.email = :email GROUP BY u.id',
            ['email' => mb_strtolower(trim($email))]
        );
        return $row === null ? null : User::fromRow($row);
    }

    public function findWithRights(int $id): ?User
    {
        $row = $this->db->fetchOne(
            self::SELECT_WITH_RIGHTS . ' WHERE u.id = :id GROUP BY u.id',
            ['id' => $id]
        );
        return $row === null ? null : User::fromRow($row);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function paginateUsers(int $page, int $perPage): array
    {
        $offset = ($page - 1) * $perPage;
        return $this->db->fetchAll(
            "SELECT u.*, STRING_AGG(r.code, ',') AS role_codes
             FROM users u
             LEFT JOIN role_user ru ON ru.user_id = u.id
             LEFT JOIN roles r ON r.id = ru.role_id
             GROUP BY u.id
             ORDER BY u.created_at DESC
             LIMIT {$perPage} OFFSET {$offset}"
        );
    }

    /**
     * @param array<string,mixed> $data
     */
    public function create(array $data): int
    {
        return $this->insert('users', [
            'email' => mb_strtolower(trim((string) $data['email'])),
            'password_hash' => $data['password_hash'],
            'first_name' => $data['first_name'] ?? '',
            'last_name' => $data['last_name'] ?? '',
            'is_active' => $data['is_active'] ?? true,
            'must_change_password' => $data['must_change_password'] ?? false,
        ]);
    }

    /**
     * @param array<string,mixed> $data
     */
    public function updateProfile(int $id, array $data): void
    {
        $this->update('users', $data, ['id' => $id]);
    }

    public function syncRoles(int $userId, array $roleIds): void
    {
        $this->db->execute('DELETE FROM role_user WHERE user_id = :u', ['u' => $userId]);
        foreach (array_unique(array_map('intval', $roleIds)) as $roleId) {
            $this->db->execute(
                'INSERT INTO role_user (role_id, user_id) VALUES (:r, :u) ON CONFLICT DO NOTHING',
                ['r' => $roleId, 'u' => $userId]
            );
        }
    }

    public function recordFailedAttempt(int $userId, int $maxAttempts = 5): void
    {
        $this->db->execute(
            'UPDATE users SET failed_attempts = failed_attempts + 1,
                locked_until = CASE WHEN failed_attempts + 1 >= :max THEN NOW() + INTERVAL \'15 minutes\' ELSE locked_until END
             WHERE id = :id',
            ['max' => $maxAttempts, 'id' => $userId]
        );
    }

    public function resetFailedAttempts(int $userId): void
    {
        $this->db->execute(
            'UPDATE users SET failed_attempts = 0, locked_until = NULL, last_login_at = NOW() WHERE id = :id',
            ['id' => $userId]
        );
    }

    public function storeRememberToken(int $userId, string $hash, \DateTimeImmutable $expires): void
    {
        $this->db->execute(
            'INSERT INTO remember_tokens (user_id, token_hash, expires_at) VALUES (:u, :h, :e)',
            ['u' => $userId, 'h' => $hash, 'e' => $exp->format('Y-m-d H:i:s')]
        );
    }

    public function findByRememberToken(string $token): ?User
    {
        $row = $this->db->fetchOne(
            "SELECT u.* FROM users u
             JOIN remember_tokens rt ON rt.user_id = u.id
             WHERE rt.token_hash = :h AND rt.expires_at > NOW() AND u.is_active = TRUE",
            ['h' => hash('sha256', $token)]
        );
        return $row === null ? null : $this->findWithRights((int) $row['id']);
    }

    public function purgeExpiredTokens(): void
    {
        $this->db->exec('DELETE FROM remember_tokens WHERE expires_at < NOW()');
        $this->db->exec('DELETE FROM password_resets WHERE expires_at < NOW()');
    }
}
