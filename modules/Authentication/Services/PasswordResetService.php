<?php

declare(strict_types=1);

namespace Modules\Authentication\Services;

use App\Core\Database\Connection;
use App\Core\Exception\AppException;
use App\Core\Security\Password;
use Modules\Authentication\Repositories\UserRepository;

/**
 * Réinitialisation des mots de passe par jeton à usage unique.
 */
final class PasswordResetService
{
    public function __construct(
        private readonly Connection $db,
        private readonly UserRepository $users,
        private readonly Password $password
    ) {
    }

    public function createResetToken(string $email): string
    {
        $user = $this->users->findByEmail($email);
        // Pas d'énumération des comptes : message identique si l'email existe ou non.
        if ($user === null) {
            return '';
        }
        $token = bin2hex(random_bytes(32));
        $this->db->execute(
            'INSERT INTO password_resets (user_id, token_hash, expires_at)
             VALUES (:u, :h, ' . $this->db->nowPlus(1, 'hour') . ')',
            ['u' => $user->id, 'h' => hash('sha256', $token)]
        );
        return $token;
    }

    public function resetWithToken(string $token, string $newPassword): void
    {
        $row = $this->db->fetchOne(
            'SELECT * FROM password_resets
             WHERE token_hash = :h AND used_at IS NULL AND expires_at > ' . $this->db->nowExpression(),
            ['h' => hash('sha256', $token)]
        );
        if ($row === null) {
            throw new AppException('Jeton de réinitialisation invalide ou expiré.');
        }
        $errors = $this->password->validateStrength($newPassword);
        if ($errors !== []) {
            throw new AppException($errors[0]);
        }
        $this->db->transaction(function (Connection $db) use ($row, $newPassword): void {
            $db->execute(
                'UPDATE users SET password_hash = :h, failed_attempts = 0, locked_until = NULL,
                    must_change_password = FALSE, updated_at = ' . $db->nowExpression() . ' WHERE id = :id',
                ['h' => $this->password->hash($newPassword), 'id' => $row['user_id']]
            );
            $db->execute('UPDATE password_resets SET used_at = ' . $db->nowExpression() . ' WHERE id = :id', ['id' => $row['id']]);
            $db->execute('DELETE FROM remember_tokens WHERE user_id = :u', ['u' => $row['user_id']]);
        });
    }
}
