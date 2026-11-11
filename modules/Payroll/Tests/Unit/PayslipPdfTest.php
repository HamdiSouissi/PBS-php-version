<?php

declare(strict_types=1);

namespace Modules\Payroll\Tests\Unit;

use App\Core\Application;
use App\Core\Pdf\PdfDocument;
use App\Services\Pdf\PayslipPdf;
use Modules\Organization\Services\OrganizationService;
use Modules\Payroll\Services\PayrollService;
use Tests\Core\DatabaseTestCase;

final class PayslipPdfTest extends DatabaseTestCase
{
    public function testPayslipDisplaysStoredIdentityAndContributionRates(): void
    {
        $this->db->execute("INSERT INTO companies (legal_name, siret, city, postal_code) VALUES ('Entreprise test', '12345678901234', 'Paris', '75001')");
        $company = (int) $this->db->lastInsertId();
        Application::container()->get(OrganizationService::class)->updateCompany($company, [
            'naf_code' => '6202A', 'urssaf_number' => '117 1234567890', 'collective_agreement' => 'Bureaux études',
        ]);
        $this->db->execute("INSERT INTO employees (company_id, employee_number, last_name, first_name, status, hire_date, address, city, postal_code, coefficient) VALUES (:company, 'E-42', 'Martin', 'Alex', 'active', '2024-01-01', '1 rue Test', 'Paris', '75001', '150')", ['company' => $company]);
        $employee = (int) $this->db->lastInsertId();
        $this->db->execute("INSERT INTO employment_contracts (employee_id, type, start_date, monthly_gross, status, classification) VALUES (:employee, 'CDI', '2024-01-01', 3000, 'active', 'Cadre')", ['employee' => $employee]);
        $this->db->execute("INSERT INTO payroll_rule_versions (code, label, category, base_type, rate_employee, rate_employer, is_deductible, effective_from, source, notes) VALUES ('TEST-PDF', 'Maladie', 'sante', 'brut', 2, 13, 1, '2026-01-01', 'TEST', '')");
        foreach (['Maladie complémentaire', 'Vieillesse plafonnée', 'Retraite complémentaire', 'Allocations familiales', 'Accidents du travail', 'Assurance chômage', 'AGS', 'CSG déductible', 'CSG non déductible', 'Autre contribution', 'Retraite supplémentaire'] as $i => $label) {
            $this->db->execute("INSERT INTO payroll_rule_versions (code, label, category, base_type, rate_employee, rate_employer, is_deductible, effective_from, source, notes) VALUES (:code, :label, 'autres', 'brut', 2, 13, 1, '2026-01-01', 'TEST', '')", ['code' => 'T' . $i, 'label' => $label]);
        }
        $service = Application::container()->get(PayrollService::class);
        $period = $service->ensurePeriod($company, 2026, 9);
        $id = $service->generatePayslip((int) $period['id'], $employee, 0, null);
        $document = new PayslipRecordingPdf();
        $document->setTightSpacing(true);
        (new PayslipPdf($this->db))->render($document, $id);
        $text = json_encode($document->recorded, JSON_UNESCAPED_UNICODE);
        self::assertStringContainsString('6202A', $text);
        self::assertStringContainsString('117 1234567890', $text);
        self::assertStringContainsString('Bureaux études', $text);
        self::assertStringContainsString('Cadre', $text);
        self::assertStringContainsString('Taux pat.', $text);
        self::assertStringContainsString('Net à payer avant impôt', $text);
        self::assertFalse(str_contains($text, 'Statut du bulletin'));
        self::assertFalse(str_contains($text, 'Règlement :'));
        self::assertFalse(str_contains($text, 'Document généré par Prime Business Suite'));
        self::assertStringContainsString('13 %', $text);
        self::assertStringContainsString('Retraite supplémentaire', $text);
        self::assertSame(1, $document->pageCount());
        self::assertStringContainsString('%PDF-', substr($document->output(), 0, 5));
    }
}

final class PayslipRecordingPdf extends PdfDocument
{
    public array $recorded = [];

    public function keyValueColumns(string $leftTitle, array $leftRows, string $rightTitle, array $rightRows, float $labelWidth = 34): void
    {
        $this->recorded[] = [$leftRows, $rightRows];
        parent::keyValueColumns($leftTitle, $leftRows, $rightTitle, $rightRows, $labelWidth);
    }

    public function keyValueTable(array $rows, float $labelWidth = 62): void
    {
        $this->recorded[] = $rows;
        parent::keyValueTable($rows, $labelWidth);
    }

    public function dataTable(array $headers, array $rows, array $widths, array $aligns = [], bool $flush = true): void
    {
        $this->recorded[] = [$headers, $rows];
        parent::dataTable($headers, $rows, $widths, $aligns, $flush);
    }

    public function totalsTable(array $rows): void
    {
        $this->recorded[] = $rows;
        parent::totalsTable($rows);
    }

    public function smallMuted(string $text): void
    {
        $this->recorded[] = $text;
        parent::smallMuted($text);
    }
}
