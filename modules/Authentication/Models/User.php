<?php

declare(strict_types=1);

namespace Modules\Authentication\Models;

use App\Core\Database\BaseModel;

/**
 * Utilisateur applicatif avec ses rôles et permissions résolues.
 */
final class User extends BaseModel
{
    /**
     * @param list<string> $roles
     * @param list<string> $permissions
     */
    public function __construct(
        public readonly int $id,
        public readonly string $email,
        public readonly string $passwordHash,
        public readonly string $firstName,
        public readonly string $lastName,
        public readonly bool $isActive,
        public readonly ?string $totpSecret,
        public readonly bool $totpEnrolled,
        public readonly int $failedAttempts,
        public readonly ?\DateTimeImmutable $lockedUntil,
        public readonly ?\DateTimeImmutable $lastLoginAt,
        public readonly bool $mustChangePassword,
        public readonly array $roles = [],
        public readonly array $permissions = []
    ) {
    }

    public static function fromRow(array $row): static
    {
        return new self(
            (int) $row['id'],
            (string) $row['email'],
            (string) $row['password_hash'],
            (string) ($row['first_name'] ?? ''),
            (string) ($row['last_name'] ?? ''),
            self::bool($row['is_active'] ?? true),
            isset($row['totp_secret']) && $row['totp_secret'] !== '' ? (string) $row['totp_secret'] : null,
            self::bool($row['totp_enrolled'] ?? false),
            (int) ($row['failed_attempts'] ?? 0),
            self::dateOrNull($row['locked_until'] ?? null),
            self::dateOrNull($row['last_login_at'] ?? null),
            self::bool($row['must_change_password'] ?? false),
            isset($row['roles']) ? explode(',', (string) $row['roles']) : [],
            isset($row['permissions']) ? array_filter(explode(',', (string) $row['permissions'])) : []
        );
    }

    public function fullName(): string
    {
        return trim($this->firstName . ' ' . $this->lastName) ?: $this->email;
    }

    public function initials(): string
    {
        $parts = array_filter([mb_substr($this->firstName, 0, 1), mb_substr($this->lastName, 0, 1)]);
        return mb_strtoupper(implode('', $parts) ?: mb_substr($this->email, 0, 2));
    }

    public function isLocked(): bool
    {
        return $this->lockedUntil !== null && $this->lockedUntil > new \DateTimeImmutable();
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'full_name' => $this->fullName(),
            'is_active' => $this->isActive,
            'totp_enrolled' => $this->totpEnrolled,
            'roles' => $this->roles,
            'permissions' => $this->permissions,
        ];
    }
}
