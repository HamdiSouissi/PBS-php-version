<?php

declare(strict_types=1);

namespace Modules\Employees\Tests\Functional;

use App\Core\Pdf\PdfDocument;
use App\Services\Pdf\EmployeeContractPdf;
use Tests\Core\DatabaseTestCase;

final class ContractPdfTest extends DatabaseTestCase
{
    public function testAllEmploymentContractTypesOmitLegalWarning(): void
    {
        $this->db->execute("INSERT INTO companies (legal_name, siren, siret, legal_form, city, postal_code) VALUES ('Test SAS', '123456789', '12345678901234', 'SAS', 'Paris', '75001')");
        $company = (int) $this->db->lastInsertId();
        $this->db->execute("INSERT INTO employees (company_id, employee_number, last_name, first_name, status, hire_date) VALUES (:company, 'EMP-001', 'Dupont', 'Jean', 'active', '2026-01-01')", ['company' => $company]);
        $employee = (int) $this->db->lastInsertId();
        foreach (['CDI', 'CDD', 'apprentissage', 'professionnalisation', 'stage', 'alternance', 'interim'] as $type) {
            $this->db->execute("INSERT INTO employment_contracts (employee_id, type, start_date, monthly_gross, weekly_hours, status) VALUES (:employee, :type, '2026-01-01', 3000, 35, 'active')", ['employee' => $employee, 'type' => $type]);
            $pdf = new PdfDocument();
            (new EmployeeContractPdf($this->db))->render($pdf, (int) $this->db->lastInsertId());
            $binary = $pdf->output();
            self::assertTrue(str_starts_with($binary, '%PDF-'));
            preg_match_all('/stream\r?\n(.*?)endstream/s', $binary, $matches);
            $text = '';
            foreach ($matches[1] as $raw) {
                $decoded = @gzuncompress($raw);
                $text .= $decoded === false ? $raw : $decoded;
            }
            self::assertFalse(str_contains($text, 'DOCUMENT TYPE'), $type);
            self::assertFalse(str_contains($text, 'RELECTURE JURIDIQUE OBLIGATOIRE'), $type);
            self::assertTrue(str_contains($text, 'CONTRAT DE TRAVAIL'), $type);
        }
    }
}
