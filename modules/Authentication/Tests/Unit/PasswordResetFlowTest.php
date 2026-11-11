<?php

declare(strict_types=1);

namespace Modules\Authentication\Tests\Unit;

use App\Core\Application;
use App\Core\Exception\AppException;
use App\Core\Exception\ValidationException;
use Modules\Authentication\Services\PasswordResetService;
use Modules\Employees\Services\EmployeeService;
use Tests\Core\DatabaseTestCase;

final class PasswordResetFlowTest extends DatabaseTestCase
{
    private function employee(): array
    {
        $this->db->execute("INSERT INTO companies (legal_name, siren, siret, legal_form, city, postal_code) VALUES ('Test SAS', '123456789', '12345678901234', 'SAS', 'Paris', '75001')");
        $company = (int) $this->db->lastInsertId();
        $this->db->execute("INSERT INTO users (email, password_hash, first_name, last_name, is_active) VALUES ('old@example.test', 'oldhash', 'Anne', 'Test', 1)");
        $user = (int) $this->db->lastInsertId();
        $this->db->execute("INSERT INTO employees (company_id, user_id, employee_number, first_name, last_name, professional_email, personal_email) VALUES (:company, :user, 'EMP-01', 'Anne', 'Test', 'old@example.test', 'personal@example.test')", ['company' => $company, 'user' => $user]);
        return [(int) $this->db->lastInsertId(), $user];
    }

    public function testProfessionalEmailSyncAndOldResetRevoked(): void
    {
        [$employee, $user] = $this->employee();
        $service = Application::container()->get(PasswordResetService::class);
        $oldToken = $service->createResetToken('old@example.test');
        Application::container()->get(EmployeeService::class)->update($employee, ['professional_email' => 'New@example.test', 'first_name' => 'Alice']);
        self::assertSame('new@example.test', $this->db->fetchScalar('SELECT email FROM users WHERE id = :id', ['id' => $user]));
        self::assertSame('Alice', $this->db->fetchScalar('SELECT first_name FROM employees WHERE id = :id', ['id' => $employee]));
        self::assertSame('', $service->createResetToken('old@example.test'));
        self::assertTrue($service->createResetToken('new@example.test') !== '');
        $this->expectException(AppException::class);
        $service->resetWithToken($oldToken, 'NewStrongPassword123!');
    }

    public function testDuplicateLoginRollsBackAllChanges(): void
    {
        [$employee] = $this->employee();
        $this->db->execute("INSERT INTO users (email, password_hash) VALUES ('taken@example.test', 'hash')");
        try {
            Application::container()->get(EmployeeService::class)->update($employee, ['professional_email' => 'taken@example.test', 'first_name' => 'Changed']);
            throw new \RuntimeException('La collision aurait dû être refusée');
        } catch (ValidationException $exception) {
            self::assertSame('Anne', $this->db->fetchScalar('SELECT first_name FROM employees WHERE id = :id', ['id' => $employee]));
            self::assertSame('old@example.test', $this->db->fetchScalar('SELECT professional_email FROM employees WHERE id = :id', ['id' => $employee]));
        }
    }

    public function testResetIsSingleUseAndInvalidatesOtherTokens(): void
    {
        [, $user] = $this->employee();
        $service = Application::container()->get(PasswordResetService::class);
        $token = $service->createForUser($user);
        $other = $service->createForUser($user);
        $service->resetWithToken($token, 'NewStrongPassword123!');
        self::assertTrue(password_verify('NewStrongPassword123!', (string) $this->db->fetchScalar('SELECT password_hash FROM users WHERE id = :id', ['id' => $user])));
        foreach ([$token, $other] as $invalid) {
            try {
                $service->resetWithToken($invalid, 'AnotherStrong123!');
                throw new \RuntimeException('Un ancien jeton ne doit plus fonctionner');
            } catch (AppException $exception) {
                self::assertTrue(true);
            }
        }
    }

    public function testInactiveAccountCannotReset(): void
    {
        [, $user] = $this->employee();
        $service = Application::container()->get(PasswordResetService::class);
        $token = $service->createForUser($user);
        $this->db->execute('UPDATE users SET is_active = 0 WHERE id = :id', ['id' => $user]);
        self::assertSame('', $service->createResetToken('old@example.test'));
        $this->expectException(AppException::class);
        $service->resetWithToken($token, 'NewStrongPassword123!');
    }
    public function testPayrollCannotEditAndHrCannotEditAdminAccount(): void
    {
        [$employee, $userId] = $this->employee();
        $c = Application::container();
        $request = new \App\Core\Http\Request(['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/employees/1/edit'], [], [], []);
        $auth = $c->get(\App\Core\Auth\AuthManager::class);
        $repo = $c->get(\Modules\Authentication\Repositories\UserRepository::class);
        $controller = $c->get(\Modules\Employees\Controllers\EmployeeController::class);
        $role = (int) $this->db->fetchScalar("SELECT id FROM roles WHERE code = 'paie'");
        $this->db->execute('INSERT INTO role_user (role_id, user_id) VALUES (:role, :user)', ['role' => $role, 'user' => $userId]);
        $auth->setUser($repo->findWithRights($userId));
        try {
            $controller->edit($request, $employee);
            throw new \RuntimeException('La paie ne doit pas modifier la fiche');
        } catch (\App\Core\Exception\AuthorizationException $exception) {
            self::assertTrue(true);
        }
        try {
            $controller->sendPasswordReset($request, $employee);
            throw new \RuntimeException('La paie ne doit pas envoyer de lien');
        } catch (\App\Core\Exception\AuthorizationException $exception) {
            self::assertTrue(true);
        }
        $admin = (int) $this->db->fetchScalar("SELECT id FROM users WHERE email = 'admin@prime-vision-solutions.fr'");
        $this->db->execute('UPDATE employees SET user_id = :admin WHERE id = :id', ['admin' => $admin, 'id' => $employee]);
        $role = (int) $this->db->fetchScalar("SELECT id FROM roles WHERE code = 'rh'");
        $this->db->execute('INSERT INTO role_user (role_id, user_id) VALUES (:role, :user)', ['role' => $role, 'user' => $userId]);
        $auth->setUser($repo->findWithRights($userId));
        $this->expectException(\App\Core\Exception\AuthorizationException::class);
        $controller->edit($request, $employee);
    }

}
