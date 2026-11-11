<?php

declare(strict_types=1);

namespace Modules\Employees\Tests\Functional;

use App\Core\Application;
use App\Core\Exception\AppException;
use App\Core\Exception\AuthorizationException;
use Modules\Employees\Services\EmployeeAccountAdministrationService;
use Tests\Core\DatabaseTestCase;

final class EmployeeAccountAdministrationTest extends DatabaseTestCase
{
    private function employee(string $email = 'employee@example.test'): int
    {
        $this->db->execute("INSERT INTO companies (legal_name, siren, siret, legal_form, city, postal_code) VALUES ('Test SAS', '123456789', '12345678901234', 'SAS', 'Paris', '75001')");
        $company = (int) $this->db->lastInsertId();
        $this->db->execute("INSERT INTO employees (company_id, employee_number, first_name, last_name, professional_email) VALUES (:company, 'EMP-TEST', 'Anne', 'Test', :email)", ['company' => $company, 'email' => $email]);
        return (int) $this->db->lastInsertId();
    }

    public function testCreatesLinkedAccountWithSelectedRoleAndRandomPassword(): void
    {
        $id = $this->employee('Employee@example.test');
        $role = (int) $this->db->fetchScalar("SELECT id FROM roles WHERE code = 'rh'");
        $service = Application::container()->get(EmployeeAccountAdministrationService::class);
        $result = $service->create($id, $role);
        self::assertSame('created', $result['status']);
        $userId = (int) $result['user_id'];
        self::assertSame($userId, (int) $this->db->fetchScalar('SELECT user_id FROM employees WHERE id = :id', ['id' => $id]));
        $user = $this->db->fetchOne('SELECT * FROM users WHERE id = :id', ['id' => $userId]);
        self::assertSame('employee@example.test', $user['email']);
        self::assertSame(1, (int) $user['must_change_password']);
        self::assertFalse(password_verify('change_me@', $user['password_hash']));
        self::assertSame(1, (int) $this->db->fetchScalar('SELECT COUNT(*) FROM role_user WHERE role_id = :role AND user_id = :user', ['role' => $role, 'user' => $userId]));
        $this->expectException(AppException::class);
        $service->create($id, $role);
    }

    public function testExistingAccountIsNotModifiedOrLinkedWithoutConfirmation(): void
    {
        $id = $this->employee();
        $this->db->execute("INSERT INTO users (email, password_hash, is_active) VALUES ('employee@example.test', 'untouched', 1)");
        $userId = (int) $this->db->lastInsertId();
        $service = Application::container()->get(EmployeeAccountAdministrationService::class);
        self::assertSame('exists', $service->create($id, 9999)['status']);
        self::assertNull($this->db->fetchScalar('SELECT user_id FROM employees WHERE id = :id', ['id' => $id]));
        self::assertSame('untouched', $this->db->fetchScalar('SELECT password_hash FROM users WHERE id = :id', ['id' => $userId]));
        $service->linkExisting($id);
        self::assertSame($userId, (int) $this->db->fetchScalar('SELECT user_id FROM employees WHERE id = :id', ['id' => $id]));
        self::assertSame(0, (int) $this->db->fetchScalar('SELECT COUNT(*) FROM role_user WHERE user_id = :id', ['id' => $userId]));
    }

    public function testCannotLinkAccountAlreadyAssignedToAnotherEmployee(): void
    {
        $id = $this->employee();
        $this->db->execute("INSERT INTO users (email, password_hash) VALUES ('employee@example.test', 'hash')");
        $user = (int) $this->db->lastInsertId();
        $company = (int) $this->db->fetchScalar('SELECT company_id FROM employees WHERE id = :id', ['id' => $id]);
        $this->db->execute("INSERT INTO employees (company_id, user_id, employee_number, first_name, last_name) VALUES (:company, :user, 'EMP-OTHER', 'Other', 'Test')", ['company' => $company, 'user' => $user]);
        $service = Application::container()->get(EmployeeAccountAdministrationService::class);
        self::assertTrue($service->existing($id)['linked_elsewhere']);
        $this->expectException(AppException::class);
        $service->linkExisting($id);
    }

    public function testNonAdminCannotCreateAccount(): void
    {
        $id = $this->employee();
        $this->db->execute("INSERT INTO users (email, password_hash) VALUES ('rh@example.test', 'hash')");
        $user = (int) $this->db->lastInsertId();
        $role = (int) $this->db->fetchScalar("SELECT id FROM roles WHERE code = 'rh'");
        $this->db->execute('INSERT INTO role_user (role_id, user_id) VALUES (:role, :user)', ['role' => $role, 'user' => $user]);
        $container = Application::container();
        $container->get(\App\Core\Auth\AuthManager::class)->setUser($container->get(\Modules\Authentication\Repositories\UserRepository::class)->findWithRights($user));
        $request = new \App\Core\Http\Request(['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/employees/1/account'], [], ['role_id' => $role], []);
        $this->expectException(AuthorizationException::class);
        $container->get(\Modules\Employees\Controllers\EmployeeController::class)->createAccount($request, $id);
    }
    public function testMissingProfessionalAddressIsRejected(): void
    {
        $id = $this->employee('');
        $this->expectException(AppException::class);
        Application::container()->get(EmployeeAccountAdministrationService::class)->create($id, 1);
    }

    public function testInvalidRoleDoesNotCreateAccount(): void
    {
        $id = $this->employee();
        try {
            Application::container()->get(EmployeeAccountAdministrationService::class)->create($id, 99999);
            throw new \RuntimeException('Le rôle invalide doit être refusé');
        } catch (AppException $exception) {
            self::assertNull($this->db->fetchScalar('SELECT user_id FROM employees WHERE id = :id', ['id' => $id]));
            self::assertSame(0, (int) $this->db->fetchScalar("SELECT COUNT(*) FROM users WHERE email = 'employee@example.test'"));
        }
    }

    public function testInactiveAccountCannotBeLinked(): void
    {
        $id = $this->employee();
        $this->db->execute("INSERT INTO users (email, password_hash, is_active) VALUES ('employee@example.test', 'hash', 0)");
        $service = Application::container()->get(EmployeeAccountAdministrationService::class);
        self::assertFalse($service->existing($id)['user']->isActive);
        $this->expectException(AppException::class);
        $service->linkExisting($id);
    }

}
