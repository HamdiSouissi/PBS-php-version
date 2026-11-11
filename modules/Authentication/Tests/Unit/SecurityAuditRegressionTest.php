<?php

declare(strict_types=1);

namespace Modules\Authentication\Tests\Unit;

use App\Core\Application;
use App\Core\Auth\AuthManager;
use App\Core\Exception\AuthorizationException;
use App\Core\Exception\NotFoundException;
use App\Core\Http\Request;
use App\Core\Session\SessionManager;
use Modules\Authentication\Controllers\RoleController;
use Modules\Authentication\Controllers\UserController;
use Modules\Authentication\Repositories\UserRepository;
use Modules\Authentication\Services\UserService;
use Modules\Documents\Services\DocumentAccessService;
use Modules\Expenses\Controllers\ExpenseReportController;
use Tests\Core\DatabaseTestCase;

final class SecurityAuditRegressionTest extends DatabaseTestCase
{
    private function user(string $role): int
    {
        $this->db->execute("INSERT INTO users (email, password_hash, first_name, last_name, is_active) VALUES (:email, 'hash', 'Test', 'User', 1)", ['email' => $role . '@test.example']);
        $id = (int) $this->db->lastInsertId();
        $this->db->execute('INSERT INTO role_user (role_id, user_id) VALUES ((SELECT id FROM roles WHERE code = :role), :id)', ['role' => $role, 'id' => $id]);
        return $id;
    }

    private function actAs(int $id): void
    {
        $c = Application::container();
        $c->get(AuthManager::class)->setUser($c->get(UserRepository::class)->findWithRights($id));
    }

    private function request(array $data = []): Request
    {
        return new Request(['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/'], [], $data, []);
    }

    public function testHrCannotChangeRoles(): void
    {
        $this->actAs($this->user('rh'));
        $this->expectException(AuthorizationException::class);
        Application::container()->get(RoleController::class)->store($this->request(['code' => 'evil', 'label' => 'Evil', 'permissions' => ['*']]));
    }

    public function testHrCannotDisableAdminTotp(): void
    {
        $this->actAs($this->user('rh'));
        $admin = (int) $this->db->fetchScalar("SELECT id FROM users WHERE email = 'admin@prime-vision-solutions.fr'");
        $this->expectException(AuthorizationException::class);
        Application::container()->get(UserController::class)->disableTotp($this->request(), $admin);
    }

    public function testHrCannotAssignMorePrivilegedRole(): void
    {
        $id = $this->user('rh');
        $this->actAs($id);
        $role = (int) $this->db->fetchScalar("SELECT id FROM roles WHERE code = 'comptable'");
        $this->expectException(AuthorizationException::class);
        Application::container()->get(UserController::class)->update($this->request(['first_name' => 'Test', 'last_name' => 'User', 'is_active' => '1', 'roles' => [$role]]), $id);
    }

    public function testPasswordChangeInvalidatesOldSessionAndRememberTokens(): void
    {
        $c = Application::container();
        $id = (int) $this->db->fetchScalar("SELECT id FROM users WHERE email = 'admin@prime-vision-solutions.fr'");
        $repo = $c->get(UserRepository::class);
        $old = $repo->findWithRights($id);
        $_SESSION['user_id'] = $id;
        $_SESSION['password_fingerprint'] = hash('sha256', $old->passwordHash);
        $this->db->execute("INSERT INTO remember_tokens (user_id, token_hash, expires_at) VALUES (:id, 'oldtoken', '2099-01-01 00:00:00')", ['id' => $id]);
        $c->get(UserService::class)->changePassword($id, 'PBS@2026!admin', 'NouveauMotDePasse2026!', 'NouveauMotDePasse2026!');
        self::assertSame(0, (int) $this->db->fetchScalar('SELECT COUNT(*) FROM remember_tokens WHERE user_id = :id', ['id' => $id]));
        $freshAuth = new AuthManager($c->get(SessionManager::class), $this->db);
        self::assertNull($freshAuth->user());
    }

    public function testAccountantCannotReadPayslipThroughDocumentManager(): void
    {
        $this->actAs($this->user('comptable'));
        $this->expectException(NotFoundException::class);
        Application::container()->get(DocumentAccessService::class)->assertCanAccess(['entity_type' => 'payslip', 'employee_id' => 123]);
    }

    public function testExpenseCreateForAnotherEmployeeWithoutAttachmentDenied(): void
    {
        $id = $this->user('employe');
        $this->db->execute("INSERT INTO companies (legal_name, siren, siret, legal_form, city, postal_code) VALUES ('Test', '123456789', '12345678901234', 'SAS', 'Paris', '75001')");
        $company = (int) $this->db->lastInsertId();
        $this->db->execute("INSERT INTO employees (company_id, employee_number, first_name, last_name, status) VALUES (:c, 'OTHER', 'Autre', 'Test', 'active')", ['c' => $company]);
        $employee = (int) $this->db->lastInsertId();
        $this->db->execute("INSERT INTO role_permission (role_id, permission_id) SELECT r.id, p.id FROM roles r, permissions p WHERE r.code = 'employe' AND p.code = 'expenses.request'");
        $this->actAs($id);
        unset($_FILES['receipt']);
        $this->expectException(AuthorizationException::class);
        Application::container()->get(ExpenseReportController::class)->store($this->request(['employee_id' => $employee, 'title' => 'Test', 'lines' => ['label' => ['Repas'], 'amount' => ['10']]]));
    }
    public function testExpenseReadAndSubmitAreScopedToOwner(): void
    {
        $user = $this->user('employe');
        $this->db->execute("INSERT INTO companies (legal_name, siren, siret, legal_form, city, postal_code) VALUES ('Test', '123456789', '12345678901234', 'SAS', 'Paris', '75001')");
        $company = (int) $this->db->lastInsertId();
        $this->db->execute("INSERT INTO employees (company_id, employee_number, first_name, last_name, status, user_id) VALUES (:c, 'OWN', 'Own', 'Test', 'active', :u)", ['c' => $company, 'u' => $user]);
        $this->db->execute("INSERT INTO employees (company_id, employee_number, first_name, last_name, status) VALUES (:c, 'OTHER', 'Other', 'Test', 'active')", ['c' => $company]);
        $other = (int) $this->db->lastInsertId();
        $this->db->execute("INSERT INTO expense_reports (employee_id, title, status, total_amount) VALUES (:id, 'Autre', 'draft', 10)", ['id' => $other]);
        $report = (int) $this->db->lastInsertId();
        $this->db->execute("INSERT INTO role_permission (role_id, permission_id) SELECT r.id, p.id FROM roles r, permissions p WHERE r.code = 'employe' AND p.code IN ('expenses.request', 'expenses.read')");
        $this->actAs($user);
        $controller = Application::container()->get(ExpenseReportController::class);
        try {
            $controller->show($this->request(), $report);
            throw new \RuntimeException('Lecture non autorisée');
        } catch (NotFoundException) {
        }
        try {
            $controller->submit($this->request(), $report);
            throw new \RuntimeException('Soumission non autorisée');
        } catch (NotFoundException) {
        }
        self::assertSame('draft', $this->db->fetchScalar('SELECT status FROM expense_reports WHERE id = :id', ['id' => $report]));
        self::assertSame([], Application::container()->get(\Modules\Expenses\Services\ExpenseReportService::class)->all((int) $this->db->fetchScalar('SELECT id FROM employees WHERE user_id = :id', ['id' => $user])));
    }

    public function testResetRevokesExistingSession(): void
    {
        $c = Application::container();
        $id = (int) $this->db->fetchScalar("SELECT id FROM users WHERE email = 'admin@prime-vision-solutions.fr'");
        $old = $c->get(UserRepository::class)->findWithRights($id);
        $_SESSION['user_id'] = $id;
        $_SESSION['password_fingerprint'] = hash('sha256', $old->passwordHash);
        $reset = $c->get(\Modules\Authentication\Services\PasswordResetService::class);
        $reset->resetWithToken($reset->createForUser($id), 'NouveauMotDePasse2026!');
        self::assertNull((new AuthManager($c->get(SessionManager::class), $this->db))->user());
    }

}
