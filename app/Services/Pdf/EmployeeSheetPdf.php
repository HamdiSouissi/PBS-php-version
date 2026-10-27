<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use App\Core\Database\Connection;
use App\Core\Exception\NotFoundException;
use App\Core\Pdf\PdfDocument;

/**
 * Fiche synthétique salarié : identité, contrat actif, congés et bulletins.
 */
final class EmployeeSheetPdf implements PdfTemplate
{
    public function __construct(private readonly Connection $db)
    {
    }

    public function render(PdfDocument $pdf, int $entityId): string
    {
        $employee = $this->db->fetchOne(
            'SELECT e.*, c.legal_name FROM employees e
             JOIN companies c ON c.id = e.company_id WHERE e.id = :id',
            ['id' => $entityId]
        );
        if ($employee === null) {
            throw new NotFoundException('Salarié introuvable.');
        }
        $contract = $this->db->fetchOne(
            'SELECT * FROM employment_contracts WHERE employee_id = :id ORDER BY status = \'active\' DESC, id DESC LIMIT 1',
            ['id' => $entityId]
        );
        $payslips = $this->db->fetchAll(
            'SELECT p.*, per.year, per.month FROM payslips p
             JOIN payroll_periods per ON per.id = p.period_id
             WHERE p.employee_id = :id ORDER BY per.year DESC, per.month DESC LIMIT 12',
            ['id' => $entityId]
        );
        $leaveBalance = $this->db->fetchScalar(
            'SELECT COALESCE(SUM(remaining_days), 0) FROM leave_balances WHERE employee_id = :id',
            ['id' => $entityId]
        );

        $title = 'Fiche salarié — ' . $employee['last_name'] . ' ' . $employee['first_name'];
        $pdf->setDocumentTitle($title)->addPage();
        $pdf->h1($title);

        $pdf->h2('Identité');
        $pdf->keyValueTable([
            ['Matricule', (string) $employee['employee_number']],
            ['Nom', (string) $employee['last_name']],
            ['Prénom', (string) $employee['first_name']],
            ['Date de naissance', $employee['birth_date'] ? date('d/m/Y', strtotime((string) $employee['birth_date'])) : '—'],
            ['NIR', (string) ($employee['social_security_number'] ?: '—')],
            ['Adresse', implode(', ', array_filter([
                $employee['address'],
                trim($employee['postal_code'] . ' ' . $employee['city']),
            ])) ?: '—'],
            ['E-mail professionnel', (string) ($employee['professional_email'] ?: '—')],
            ['Téléphone', (string) ($employee['phone'] ?: '—')],
        ]);

        $pdf->h2('Situation professionnelle');
        $pdf->keyValueTable([
            ['Poste', (string) ($employee['job_title'] ?: '—')],
            ['Statut', (string) $employee['status']],
            ['Date d\'embauche', $employee['hire_date'] ? date('d/m/Y', strtotime((string) $employee['hire_date'])) : '—'],
            ['Solde de congés restant', number_format((float) $leaveBalance, 1, ',', ' ') . ' jours'],
        ]);

        if ($contract !== null) {
            $pdf->h2('Contrat actif');
            $pdf->keyValueTable([
                ['Type', (string) $contract['type']],
                ['Depuis le', date('d/m/Y', strtotime((string) $contract['start_date']))],
                ['Durée hebdomadaire', number_format((float) $contract['weekly_hours'], 2, ',', ' ') . ' heures'],
                ['Salaire brut mensuel', money_format((float) $contract['monthly_gross'])],
            ]);
        }

        if ($payslips !== []) {
            $pdf->h2('Derniers bulletins');
            $rows = [];
            foreach ($payslips as $payslip) {
                $rows[] = [
                    sprintf('%02d/%d', (int) $payslip['month'], (int) $payslip['year']),
                    (string) $payslip['status'],
                    money_format((float) $payslip['gross_salary']),
                    money_format((float) $payslip['net_payable']),
                    (int) $payslip['is_paid'] === 1 ? 'Payé' : 'Non payé',
                ];
            }
            $pdf->dataTable(['Période', 'Statut', 'Brut', 'Net à payer', 'Règlement'], $rows, [30, 35, 40, 40, 0], ['L', 'L', 'R', 'R', 'L']);
        }

        return $title;
    }
}
