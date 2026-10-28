<?php

declare(strict_types=1);

namespace Modules\Employees\Tests\Functional;

use App\Core\Application;
use App\Core\Security\Password;
use Modules\Authentication\Repositories\UserRepository;
use Modules\Authentication\Services\UserService;
use Modules\Employees\Repositories\EmployeeRepository;
use Modules\Employees\Services\EmployeeAccountService;
use Modules\Employees\Services\EmployeeService;
use Tests\Core\DatabaseTestCase;

final class EmployeeAccountProvisionTest extends DatabaseTestCase
{
    private function companyId(): int
    {
        $this->db->execute(
            "INSERT INTO companies (legal_name, siren, siret, legal_form, city, postal_code)
             VALUES ('Test SAS', '123456789', '12345678901234', 'SAS', 'Paris', '75001')"
        );
        return (int) $this->db->lastInsertId();
    }

    public function testEmployeeCreationProvisionsAccountWithTemporaryPassword(): void
    {
        $service = Application::container()->get(EmployeeService::class);
        $employeeId = $service->create($this->companyId(), [
            'last_name' => 'Nouveau',
            'first_name' => 'Léa',
            'professional_email' => 'lea.nouveau@entreprise.fr',
        ]);

        $employee = $this->db->fetchOne('SELECT * FROM employees WHERE id = :id', ['id' => $employeeId]);
        self::assertNotNull($employee['user_id']);

        $user = Application::container()->get(UserRepository::class)->findByEmail('lea.nouveau@entreprise.fr');
        self::assertNotNull($user);
        self::assertTrue($user->mustChangePassword);
        self::assertTrue(in_array('employe', $user->roles, true));

        $hasher = Application::container()->get(Password::class);
        self::assertTrue($hasher->verify(EmployeeAccountService::TEMPORARY_PASSWORD, $user->passwordHash));

        // Le changement de mot de passe lève le drapeau de forçage.
        Application::container()->get(UserService::class)->changePassword(
            $user->id,
            EmployeeAccountService::TEMPORARY_PASSWORD,
            'UnMotDePasseFort2026!',
            'UnMotDePasseFort2026!'
        );
        $refreshed = Application::container()->get(UserRepository::class)->findWithRights($user->id);
        self::assertFalse($refreshed->mustChangePassword);
    }

    public function testProvisionLinksExistingUserWithoutTouchingCredentials(): void
    {
        $hasher = Application::container()->get(Password::class);
        $this->db->execute(
            'INSERT INTO users (email, password_hash, first_name, last_name, is_active, must_change_password)
             VALUES (:e, :p, :f, :l, 1, 0)',
            [
                'e' => 'deja.present@entreprise.fr',
                'p' => $hasher->hash('MotDePasseExistant789!'),
                'f' => 'Déjà',
                'l' => 'Présent',
            ]
        );
        $existingUserId = (int) $this->db->lastInsertId();

        $this->db->execute(
            "INSERT INTO companies (legal_name, siren, siret, legal_form, city, postal_code)
             VALUES ('Test SAS', '123456789', '12345678901234', 'SAS', 'Paris', '75001')"
        );
        $companyId = (int) $this->db->lastInsertId();
        $this->db->execute(
            "INSERT INTO employees (company_id, employee_number, last_name, first_name, professional_email, status)
             VALUES ({$companyId}, 'EMP-T-0009', 'Présent', 'Déjà', 'deja.present@entreprise.fr', 'active')"
        );
        $employeeId = (int) $this->db->lastInsertId();

        $userId = Application::container()->get(EmployeeAccountService::class)->provision($employeeId);

        self::assertSame($existingUserId, $userId);
        $employee = $this->db->fetchOne('SELECT user_id FROM employees WHERE id = :id', ['id' => $employeeId]);
        self::assertSame($existingUserId, (int) $employee['user_id']);

        $user = Application::container()->get(UserRepository::class)->findWithRights($existingUserId);
        self::assertTrue(in_array('employe', $user->roles, true));
        self::assertFalse($user->mustChangePassword);
        self::assertTrue($hasher->verify('MotDePasseExistant789!', $user->passwordHash));
    }

    public function testEmployeeWithoutEmailIsCreatedWithoutAccount(): void
    {
        $service = Application::container()->get(EmployeeService::class);
        $employeeId = $service->create($this->companyId(), [
            'last_name' => 'Sansmail',
            'first_name' => 'Hugo',
        ]);

        $employee = $this->db->fetchOne('SELECT * FROM employees WHERE id = :id', ['id' => $employeeId]);
        self::assertNull($employee['user_id']);
        // Aucun compte n'est créé pour une fiche sans adresse e-mail.
        self::assertSame(
            0,
            (int) $this->db->fetchScalar("SELECT COUNT(*) FROM users WHERE email LIKE '%sansmail%' OR email LIKE '%hugo%'")
        );
    }

    public function testProvisionAllBackfillsUnlinkedEmployees(): void
    {
        $service = Application::container()->get(EmployeeService::class);
        $companyId = $this->companyId();
        $first = $service->create($companyId, [
            'last_name' => 'Alpha', 'first_name' => 'Ana', 'professional_email' => 'ana.alpha@entreprise.fr',
        ]);
        $second = $service->create($companyId, [
            'last_name' => 'Beta', 'first_name' => 'Bob', 'personal_email' => 'bob.beta@messagerie.fr',
        ]);

        // Cas de reprise : on détache les fiches comme avant la migration 016.
        $this->db->execute('UPDATE employees SET user_id = NULL');
        $stats = Application::container()->get(EmployeeAccountService::class)->provisionAll();

        // Les comptes existent déjà : la reprise les rattache, sans les recréer.
        self::assertSame(0, $stats['created']);
        self::assertSame(2, $stats['linked']);
        foreach ([$first, $second] as $employeeId) {
            $employee = $this->db->fetchOne('SELECT user_id FROM employees WHERE id = :id', ['id' => $employeeId]);
            self::assertNotNull($employee['user_id']);
        }
    }

    public function testFindForUserPrefersDirectLinkThenEmailFallback(): void
    {
        $repository = Application::container()->get(EmployeeRepository::class);
        $service = Application::container()->get(EmployeeService::class);
        $employeeId = $service->create($this->companyId(), [
            'last_name' => 'Lien', 'first_name' => 'Inès', 'professional_email' => 'ines.lien@entreprise.fr',
        ]);

        $user = Application::container()->get(UserRepository::class)->findByEmail('ines.lien@entreprise.fr');
        $linked = $repository->findForUser($user->id, $user->email);
        self::assertSame($employeeId, (int) $linked['id']);

        // Repli historique par e-mail si le lien direct est absent.
        $this->db->execute('UPDATE employees SET user_id = NULL WHERE id = :id', ['id' => $employeeId]);
        $fallback = $repository->findForUser($user->id, $user->email);
        self::assertSame($employeeId, (int) $fallback['id']);
    }
}
