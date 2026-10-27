<?php

declare(strict_types=1);

namespace Modules\Authentication\Tests\Unit;

use App\Core\Application;
use App\Core\Exception\AppException;
use Modules\Authentication\Services\RoleService;
use Tests\Core\DatabaseTestCase;

final class RoleServiceTest extends DatabaseTestCase
{
    private RoleService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = Application::container()->get(RoleService::class);
    }

    public function testCreateRoleSyncsPermissions(): void
    {
        $id = $this->service->create('commercial', 'Commercial', 'Équipe vente', [
            'clients.read',
            'invoicing.read',
        ]);

        $role = $this->db->fetchOne('SELECT * FROM roles WHERE id = :id', ['id' => $id]);
        self::assertSame('commercial', $role['code']);
        $codes = $this->service->permissionCodes($id);
        sort($codes);
        self::assertSame(['clients.read', 'invoicing.read'], $codes);
    }

    public function testDuplicateCodeIsRejected(): void
    {
        $this->service->create('audit', 'Audit', '', ['audit.read']);
        $this->expectException(AppException::class);
        $this->service->create('audit', 'Doublon', '', ['audit.read']);
    }

    public function testUnknownPermissionIsRejected(): void
    {
        $this->expectException(AppException::class);
        $this->service->create('fantome', 'Fantôme', '', ['permission.inexistante']);
    }

    public function testUpdateReplacesFullPermissionSet(): void
    {
        $id = $this->service->create('mobile', 'Mobile', '', ['clients.read', 'clients.write']);
        $this->service->update($id, 'Mobile terrain', 'Libellé ajusté', ['clients.read']);

        $role = $this->db->fetchOne('SELECT label, description FROM roles WHERE id = :id', ['id' => $id]);
        self::assertSame('Mobile terrain', $role['label']);
        self::assertSame('Libellé ajusté', $role['description']);
        self::assertSame(['clients.read'], $this->service->permissionCodes($id));
    }

    public function testRoleWithoutUsersCanBeDeleted(): void
    {
        $id = $this->service->create('temporaire', 'Temporaire', '', ['documents.read']);
        $this->service->delete($id);

        self::assertSame(0, (int) $this->db->fetchScalar('SELECT COUNT(*) FROM roles WHERE id = :id', ['id' => $id]));
        self::assertSame(0, (int) $this->db->fetchScalar('SELECT COUNT(*) FROM role_permission WHERE role_id = :id', ['id' => $id]));
    }

    public function testRoleAttachedToUserCannotBeDeleted(): void
    {
        $roleId = (int) $this->db->fetchScalar("SELECT id FROM roles WHERE code = 'admin'");
        $this->db->execute(
            "INSERT INTO users (email, password_hash, first_name, last_name, is_active, must_change_password)
             VALUES ('pj@test.fr', 'x', 'Pj', 'Test', 1, 0)"
        );
        $userId = (int) $this->db->lastInsertId();
        $this->db->execute(
            'INSERT INTO role_user (role_id, user_id) VALUES (:r, :u)',
            ['r' => $roleId, 'u' => $userId]
        );

        $this->expectException(AppException::class);
        $this->service->delete($roleId);
    }

    public function testPermissionMatrixIsGroupedByModule(): void
    {
        $matrix = $this->service->permissionMatrix();
        self::assertArrayHasKey('payroll', $matrix);
        $codes = array_map(static fn (array $p): string => $p['code'], $matrix['payroll']);
        self::assertTrue(in_array('payroll.run', $codes, true));
    }
}
