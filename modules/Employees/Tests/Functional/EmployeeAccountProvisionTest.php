<?php

declare(strict_types=1);

namespace Modules\Employees\Tests\Functional;

use App\Core\Application;
use App\Core\Exception\AppException;
use App\Core\Exception\ValidationException;
use Modules\Employees\Repositories\EmployeeRepository;
use Modules\Employees\Services\EmployeeAccountService;
use Modules\Employees\Services\EmployeeService;
use Tests\Core\DatabaseTestCase;

final class EmployeeAccountProvisionTest extends DatabaseTestCase
{
    private function employee(): int
    {
        $this->db->execute("INSERT INTO companies (legal_name, siren, siret, legal_form, city, postal_code) VALUES ('Test SAS', '123456789', '12345678901234', 'SAS', 'Paris', '75001')");
        return Application::container()->get(EmployeeService::class)->create((int) $this->db->lastInsertId(), [
            'last_name' => 'Test', 'first_name' => 'Léa', 'professional_email' => 'lea@example.test',
        ]);
    }

    private function link(int $employeeId): int
    {
        $this->db->execute("INSERT INTO users (email, password_hash, first_name, last_name, is_active) VALUES ('lea@example.test', 'original', 'Léa', 'Test', 1)");
        $user = (int) $this->db->lastInsertId();
        $this->db->execute('UPDATE employees SET user_id = :user WHERE id = :id', ['user' => $user, 'id' => $employeeId]);
        return $user;
    }

    public function testNoAutomaticAccountOrEmailFallback(): void
    {
        $id = $this->employee();
        self::assertNull($this->db->fetchScalar('SELECT user_id FROM employees WHERE id = :id', ['id' => $id]));
        self::assertSame(0, (int) $this->db->fetchScalar("SELECT COUNT(*) FROM users WHERE email = 'lea@example.test'"));
        $repository = Application::container()->get(EmployeeRepository::class);
        self::assertNull($repository->findForUser(999, 'lea@example.test'));
        $user = $this->link($id);
        self::assertSame($id, (int) $repository->findForUser($user, 'other@example.test')['id']);
        self::assertNull($repository->findForUser(999, 'lea@example.test'));
    }

    public function testLegacyProvisioningIsDisabled(): void
    {
        $id = $this->employee();
        $this->expectException(AppException::class);
        Application::container()->get(EmployeeAccountService::class)->provision($id);
    }

    public function testTerminationRevokesAccountAndInvalidatesOldPassword(): void
    {
        $id = $this->employee();
        $user = $this->link($id);
        Application::container()->get(EmployeeService::class)->terminate($id, '2026-12-31', 'demission');
        $row = $this->db->fetchOne('SELECT is_active, password_hash FROM users WHERE id = :id', ['id' => $user]);
        self::assertSame(0, (int) $row['is_active']);
        self::assertNotSame('original', $row['password_hash']);
        self::assertSame('terminated', $this->db->fetchScalar('SELECT status FROM employees WHERE id = :id', ['id' => $id]));
    }

    public function testCannotBypassTerminationThroughUpdate(): void
    {
        $id = $this->employee();
        $this->expectException(ValidationException::class);
        Application::container()->get(EmployeeService::class)->update($id, ['status' => 'terminated']);
    }
    public function testSharedAccountPreventsTermination(): void
    {
        $id = $this->employee();
        $user = $this->link($id);
        $company = (int) $this->db->fetchScalar('SELECT company_id FROM employees WHERE id = :id', ['id' => $id]);
        $this->db->execute("INSERT INTO employees (company_id, employee_number, last_name, first_name, user_id, status) VALUES (:company, 'OTHER', 'Other', 'Test', :user, 'active')", ['company' => $company, 'user' => $user]);
        try {
            Application::container()->get(EmployeeService::class)->terminate($id, '2026-12-31', 'demission');
            throw new \RuntimeException('La sortie doit être refusée.');
        } catch (ValidationException $exception) {
            self::assertSame(1, (int) $this->db->fetchScalar('SELECT is_active FROM users WHERE id = :id', ['id' => $user]));
        }
    }

    public function testKnownLegacyPasswordCannotAuthenticate(): void
    {
        $id = $this->employee();
        $user = $this->link($id);
        $this->db->execute('UPDATE users SET password_hash = :hash WHERE id = :id', ['hash' => password_hash('change_me@', PASSWORD_DEFAULT), 'id' => $user]);
        $this->expectException(\App\Core\Exception\AuthenticationException::class);
        Application::container()->get(\Modules\Authentication\Services\AuthService::class)->attempt('lea@example.test', 'change_me@', '198.18.77.1');
    }

}
