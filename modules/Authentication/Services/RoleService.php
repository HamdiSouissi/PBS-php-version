<?php

declare(strict_types=1);

namespace Modules\Authentication\Services;

use App\Core\Database\Connection;
use App\Core\Exception\AppException;
use Database\Seeds\RbacSeeder;

/**
 * Gestion administrative des rôles et de leur matrice de permissions.
 * Les rôles système seedés restent modifiables (libellé, description,
 * permissions) mais aucun rôle rattaché à un utilisateur ne peut être supprimé.
 */
final class RoleService
{
    public function __construct(private readonly Connection $db)
    {
    }

    /**
     * Rôles avec le nombre d'utilisateurs et de permissions associés.
     *
     * @return list<array<string,mixed>>
     */
    public function allRoles(): array
    {
        return $this->db->fetchAll(
            'SELECT r.*,
                    (SELECT COUNT(*) FROM role_user ru WHERE ru.role_id = r.id) AS user_count,
                    (SELECT COUNT(*) FROM role_permission rp WHERE rp.role_id = r.id) AS permission_count
             FROM roles r ORDER BY r.label'
        );
    }

    /**
     * @return array<string,mixed>
     */
    public function getOrFail(int $id): array
    {
        $role = $this->db->fetchOne('SELECT * FROM roles WHERE id = :id', ['id' => $id]);
        if ($role === null) {
            throw new AppException('Rôle introuvable.');
        }
        return $role;
    }

    /**
     * Codes de permissions actuellement accordés au rôle.
     *
     * @return list<string>
     */
    public function permissionCodes(int $roleId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT p.code FROM role_permission rp
             JOIN permissions p ON p.id = rp.permission_id
             WHERE rp.role_id = :id',
            ['id' => $roleId]
        );
        return array_map(static fn (array $row): string => (string) $row['code'], $rows);
    }

    /**
     * Matrice des permissions groupées par module (ordre du RbacSeeder).
     *
     * @return array<string,list<array<string,string>>>
     */
    public function permissionMatrix(): array
    {
        $rows = $this->db->fetchAll('SELECT code, label, module FROM permissions ORDER BY module, id');
        $byCode = [];
        foreach ($rows as $row) {
            $byCode[(string) $row['code']] = $row;
        }

        $matrix = [];
        foreach (RbacSeeder::PERMISSIONS as $module => $codes) {
            foreach ($codes as $code) {
                if (isset($byCode[$code])) {
                    $matrix[$module][] = $byCode[$code];
                }
            }
        }
        if (isset($byCode['*'])) {
            $matrix['system'][] = $byCode['*'];
        }
        return $matrix;
    }

    /**
     * @param list<string> $permissionCodes
     */
    public function create(string $code, string $label, string $description, array $permissionCodes): int
    {
        $code = $this->normalizeCode($code);
        if ($code === '') {
            throw new AppException('Le code du rôle est obligatoire.');
        }
        $existing = $this->db->fetchScalar('SELECT id FROM roles WHERE code = :code', ['code' => $code]);
        if ($existing !== null && $existing !== false) {
            throw new AppException("Un rôle avec le code « {$code} » existe déjà.");
        }

        return (int) $this->db->transaction(function (Connection $db) use ($code, $label, $description, $permissionCodes): int {
            $db->execute(
                'INSERT INTO roles (code, label, description) VALUES (:code, :label, :description)',
                ['code' => $code, 'label' => $label, 'description' => $description]
            );
            $roleId = (int) $db->lastInsertId();
            $this->syncPermissions($db, $roleId, $permissionCodes);
            audit('role.create', 'Role', $roleId);
            return $roleId;
        });
    }

    /**
     * @param list<string> $permissionCodes
     */
    public function update(int $roleId, string $label, string $description, array $permissionCodes): void
    {
        $this->getOrFail($roleId);
        if (trim($label) === '') {
            throw new AppException('Le libellé du rôle est obligatoire.');
        }
        $this->db->transaction(function (Connection $db) use ($roleId, $label, $description, $permissionCodes): void {
            $db->execute(
                'UPDATE roles SET label = :label, description = :description WHERE id = :id',
                ['label' => $label, 'description' => $description, 'id' => $roleId]
            );
            $this->syncPermissions($db, $roleId, $permissionCodes);
            audit('role.update', 'Role', $roleId);
        });
    }

    public function delete(int $roleId): void
    {
        $this->getOrFail($roleId);
        $userCount = (int) $this->db->fetchScalar(
            'SELECT COUNT(*) FROM role_user WHERE role_id = :id',
            ['id' => $roleId]
        );
        if ($userCount > 0) {
            throw new AppException("Impossible de supprimer un rôle attribué à {$userCount} utilisateur(s). Retirez d'abord les attributions.");
        }
        $this->db->transaction(function (Connection $db) use ($roleId): void {
            $db->execute('DELETE FROM role_permission WHERE role_id = :id', ['id' => $roleId]);
            $db->execute('DELETE FROM roles WHERE id = :id', ['id' => $roleId]);
            audit('role.delete', 'Role', $roleId);
        });
    }

    /**
     * Remplace l'ensemble des permissions d'un rôle après vérification
     * de l'existence de chaque code en base.
     *
     * @param list<string> $permissionCodes
     */
    private function syncPermissions(Connection $db, int $roleId, array $permissionCodes): void
    {
        $db->execute('DELETE FROM role_permission WHERE role_id = :id', ['id' => $roleId]);
        $codes = array_values(array_unique(array_filter(array_map('strval', $permissionCodes))));
        if ($codes === []) {
            return;
        }
        foreach ($codes as $code) {
            $permissionId = $db->fetchScalar('SELECT id FROM permissions WHERE code = :code', ['code' => $code]);
            if ($permissionId === null || $permissionId === false) {
                throw new AppException("Permission inconnue : {$code}.");
            }
            $db->execute(
                'INSERT INTO role_permission (role_id, permission_id) VALUES (:role, :permission)',
                ['role' => $roleId, 'permission' => (int) $permissionId]
            );
        }
    }

    private function normalizeCode(string $code): string
    {
        $code = strtolower(trim($code));
        // Cohérence avec les codes seedés : lettres, chiffres, underscore.
        return preg_replace('/[^a-z0-9_]/', '_', $code) ?? '';
    }
}
