<?php

declare(strict_types=1);

namespace Modules\Authentication\Services;

use App\Core\Database\Connection;
use App\Core\Exception\AppException;
use App\Core\Exception\ValidationException;
use App\Core\Security\Password;
use App\Core\Security\Totp;
use Modules\Authentication\Models\User;
use Modules\Authentication\Repositories\UserRepository;

/**
 * Gestion administrative des utilisateurs et du double facteur TOTP.
 */
final class UserService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly Connection $db,
        private readonly Password $password,
        private readonly Totp $totp
    ) {
    }

    /**
     * @param array<string,mixed> $data
     * @param list<int> $roleIds
     */
    public function createUser(array $data, array $roleIds): User
    {
        $email = mb_strtolower(trim((string) $data['email']));
        if ($this->users->findByEmail($email) !== null) {
            throw new ValidationException(['email' => 'Un utilisateur existe déjà avec cet e-mail.']);
        }
        $errors = $this->password->validateStrength((string) $data['password']);
        if ($errors !== []) {
            throw new ValidationException(['password' => $errors[0]]);
        }
        $id = $this->users->create([
            'email' => $email,
            'password_hash' => $this->password->hash((string) $data['password']),
            'first_name' => $data['first_name'] ?? '',
            'last_name' => $data['last_name'] ?? '',
            'is_active' => ($data['is_active'] ?? true),
            'must_change_password' => ($data['must_change_password'] ?? false),
        ]);
        $this->users->syncRoles($id, $roleIds);
        audit('user.create', 'User', $id, ['email' => $email]);
        return $this->users->findWithRights($id) ?? throw new AppException('Utilisateur illisible après création.');
    }

    /**
     * @param array<string,mixed> $data
     * @param list<int> $roleIds
     */
    public function updateUser(int $id, array $data, array $roleIds): User
    {
        $this->users->findOrFail($id);
        $update = array_filter([
            'first_name' => $data['first_name'] ?? null,
            'last_name' => $data['last_name'] ?? null,
            'is_active' => $data['is_active'] ?? null,
            'updated_at' => date('Y-m-d H:i:s'),
        ], static fn ($value) => $value !== null);
        if ($update !== []) {
            $this->users->updateProfile($id, $update);
        }
        if ($roleIds !== []) {
            $this->users->syncRoles($id, $roleIds);
        }
        audit('user.update', 'User', $id);
        return $this->users->findWithRights($id) ?? throw new AppException('Utilisateur illisible.');
    }

    public function changePassword(int $userId, string $current, string $new, string $confirmation): void
    {
        if ($new !== $confirmation) {
            throw new ValidationException(['password' => 'La confirmation ne correspond pas.']);
        }
        $user = $this->users->findWithRights($userId) ?? throw new AppException('Utilisateur introuvable.');
        if (!$this->password->verify($current, $user->passwordHash)) {
            throw new ValidationException(['current_password' => 'Mot de passe actuel invalide.']);
        }
        $errors = $this->password->validateStrength($new);
        if ($errors !== []) {
            throw new ValidationException(['password' => $errors[0]]);
        }
        $this->db->execute(
            'UPDATE users SET password_hash = :h, must_change_password = FALSE, updated_at = ' . $this->db->nowExpression() . ' WHERE id = :id',
            ['h' => $this->password->hash($new), 'id' => $userId]
        );
        audit('user.password_change', 'User', $userId);
    }

    /**
     * Démarre l'enrôlement TOTP : génère un secret et une URI otpauth.
     *
     * @return array{secret:string,uri:string}
     */
    public function beginTotpEnrollment(User $user): array
    {
        $secret = $this->totp->generateSecret();
        $this->db->execute(
            'UPDATE users SET totp_secret = :s, totp_enrolled = FALSE WHERE id = :id',
            ['s' => $secret, 'id' => $user->id]
        );
        return [
            'secret' => $secret,
            'uri' => $this->totp->provisioningUri($secret, $user->email),
        ];
    }

    public function confirmTotpEnrollment(User $user, string $code): bool
    {
        $fresh = $this->users->findWithRights($user->id) ?? throw new AppException('Utilisateur introuvable.');
        if ($fresh->totpSecret === null || !$this->totp->verify($fresh->totpSecret, $code)) {
            return false;
        }
        $this->db->execute(
            'UPDATE users SET totp_enrolled = TRUE, totp_confirmed_at = ' . $this->db->nowExpression() . ' WHERE id = :id',
            ['id' => $user->id]
        );
        audit('user.totp_enabled', 'User', $user->id);
        return true;
    }

    public function disableTotp(User $user): void
    {
        $this->db->execute(
            'UPDATE users SET totp_secret = NULL, totp_enrolled = FALSE, totp_confirmed_at = NULL WHERE id = :id',
            ['id' => $user->id]
        );
        audit('user.totp_disabled', 'User', $user->id);
    }

    public function createFromConsole(string $email, string $password, string $roleCode): User
    {
        $roleId = $this->db->fetchScalar('SELECT id FROM roles WHERE code = :c', ['c' => $roleCode]);
        if ($roleId === false || $roleId === null) {
            throw new AppException("Rôle introuvable : {$roleCode}");
        }
        return $this->createUser(
            ['email' => $email, 'password' => $password, 'first_name' => 'Compte', 'last_name' => $roleCode],
            [(int) $roleId]
        );
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function allRoles(): array
    {
        return $this->db->fetchAll('SELECT * FROM roles ORDER BY label');
    }
}
