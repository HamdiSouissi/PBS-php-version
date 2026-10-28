<?php

declare(strict_types=1);

namespace Modules\HR\Tests\Functional;

use Tests\Core\DatabaseTestCase;

/**
 * Le réglage d'acquisition des congés n'est modifiable que par le
 * super-administrateur (permission générique '*') et le rôle RH.
 */
final class LeaveSettingsPermissionTest extends DatabaseTestCase
{
    private function roleHasPermission(string $roleCode, string $permissionCode): bool
    {
        $row = $this->db->fetchOne(
            'SELECT 1 AS found
             FROM role_permission rp
             JOIN roles r ON r.id = rp.role_id
             JOIN permissions p ON p.id = rp.permission_id
             WHERE r.code = :role AND p.code = :permission',
            ['role' => $roleCode, 'permission' => $permissionCode]
        );
        return $row !== null;
    }

    public function testPermissionExists(): void
    {
        self::assertNotNull(
            $this->db->fetchOne("SELECT id FROM permissions WHERE code = 'hr.settings.manage'")
        );
    }

    public function testOnlyAdminAndRhCanManageLeaveSettings(): void
    {
        self::assertTrue($this->roleHasPermission('rh', 'hr.settings.manage'));
        self::assertTrue($this->roleHasPermission('admin', '*'));

        self::assertFalse($this->roleHasPermission('paie', 'hr.settings.manage'));
        self::assertFalse($this->roleHasPermission('comptable', 'hr.settings.manage'));
        self::assertFalse($this->roleHasPermission('employe', 'hr.settings.manage'));
    }

    public function testRhKeepsStandardLeavePermissions(): void
    {
        foreach (['hr.leaves.read', 'hr.leaves.request', 'hr.leaves.approve'] as $permission) {
            self::assertTrue($this->roleHasPermission('rh', $permission), "RH doit avoir {$permission}.");
        }
    }
}
