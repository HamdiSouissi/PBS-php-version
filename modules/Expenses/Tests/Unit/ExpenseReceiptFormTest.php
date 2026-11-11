<?php

declare(strict_types=1);

namespace Modules\Expenses\Tests\Unit;

use App\Core\Application;
use App\Core\Exception\HttpException;
use App\Core\Http\Request;
use App\Core\Http\Response;
use Modules\Expenses\Controllers\ExpenseReportController;
use Tests\Core\DatabaseTestCase;

final class ExpenseReceiptFormTest extends DatabaseTestCase
{
    private int $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db->execute("INSERT INTO companies (legal_name, siren, siret, legal_form, city, postal_code) VALUES ('Test SAS', '123456789', '12345678901234', 'SAS', 'Paris', '75001')");
        $company = (int) $this->db->lastInsertId();
        $this->db->execute("INSERT INTO employees (company_id, employee_number, first_name, last_name, status) VALUES (:company, 'EMP-TEST', 'Anne', 'Test', 'active')", ['company' => $company]);
        $this->employee = (int) $this->db->lastInsertId();
        $container = Application::container();
        $admin = $container->get(\Modules\Authentication\Repositories\UserRepository::class)->findByEmail('admin@prime-vision-solutions.fr');
        $container->get(\App\Core\Auth\AuthManager::class)->setUser($admin);
        unset($_FILES['receipt'], $_SESSION['expense_create_input'], $_SESSION['_flash']);
    }

    protected function tearDown(): void
    {
        unset($_FILES['receipt'], $_SESSION['expense_create_input'], $_SESSION['_flash']);
        parent::tearDown();
    }

    private function createRequest(): Request
    {
        return new Request(['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/expenses'], [], [
            'employee_id' => $this->employee, 'title' => 'Déplacement', 'notes' => 'Notes',
            'lines' => ['label' => ['Restaurant'], 'spent_on' => ['2026-10-26'], 'category' => ['meal'], 'amount' => ['25'], 'vat_amount' => ['2']],
        ], []);
    }

    public function testFormSupportsPdfAndMultipart(): void
    {
        $view = Application::container()->get(\App\Core\View\ViewRenderer::class);
        $html = $view->renderView('Expenses::form', ['previous' => [], 'employees' => [], 'categories' => ['meal']]);
        self::assertTrue(str_contains($html, 'enctype="multipart/form-data"'));
        self::assertTrue(str_contains($html, 'name="receipt"'));
        self::assertTrue(str_contains($html, 'accept=".pdf,application/pdf"'));
    }

    public function testNoAttachmentStillCreatesDraft(): void
    {
        $response = Application::container()->get(ExpenseReportController::class)->store($this->createRequest());
        self::assertSame(302, $response->getStatus());
        self::assertSame(1, (int) $this->db->fetchScalar('SELECT COUNT(*) FROM expense_reports'));
        self::assertSame(0, (int) $this->db->fetchScalar('SELECT COUNT(*) FROM expense_receipts'));
    }

    public function testUploadErrorDoesNotCreateReportAndKeepsInput(): void
    {
        $_FILES['receipt'] = ['error' => UPLOAD_ERR_INI_SIZE];
        $response = Application::container()->get(ExpenseReportController::class)->store($this->createRequest());
        self::assertSame(302, $response->getStatus());
        self::assertSame(0, (int) $this->db->fetchScalar('SELECT COUNT(*) FROM expense_reports'));
        self::assertSame('Déplacement', $_SESSION['expense_create_input']['title']);
        self::assertSame('Restaurant', $_SESSION['expense_create_input']['lines']['label'][0]);
        self::assertTrue(str_contains($_SESSION['_flash']['danger'][0], 'volumineux'));
    }

    public function testExistingUploadErrorShowsUsefulMessage(): void
    {
        $controller = Application::container()->get(ExpenseReportController::class);
        $controller->store($this->createRequest());
        $id = (int) $this->db->fetchScalar('SELECT id FROM expense_reports');
        $_FILES['receipt'] = ['error' => UPLOAD_ERR_INI_SIZE];
        $response = $controller->uploadReceipt($this->createRequest(), $id);
        self::assertSame(302, $response->getStatus());
        self::assertTrue(str_contains($_SESSION['_flash']['danger'][0], 'volumineux'));
    }

    public function testOversizedRequestIsRejectedBeforeCsrf(): void
    {
        $limit = ini_parse_quantity((string) ini_get('post_max_size'));
        if ($limit <= 0) {
            return;
        }
        $request = new Request(['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/expenses', 'CONTENT_LENGTH' => (string) ($limit + 1)], [], [], []);
        $csrf = Application::container()->get(\App\Core\Http\Middleware\CsrfMiddleware::class);
        try {
            $csrf->handle($request, static fn () => new Response('unexpected'));
            throw new \RuntimeException('Requête trop grande acceptée');
        } catch (HttpException $exception) {
            self::assertSame(413, $exception->getStatusCode());
        }
    }
}
