<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use App\Core\Database\Connection;
use App\Core\Exception\NotFoundException;
use App\Core\Pdf\PdfDocument;

/**
 * Bulletin de paie PDF : période, salarié, rémunération brute, cotisations
 * et net à payer, conformément aux lignes réellement calculées en base.
 */
final class PayslipPdf implements PdfTemplate
{
    public function __construct(private readonly Connection $db)
    {
    }

    public function render(PdfDocument $pdf, int $entityId): string
    {
        $payslip = $this->db->fetchOne(
            'SELECT p.*, per.year, per.month, per.starts_on, per.ends_on,
                    e.employee_number, e.first_name, e.last_name, e.job_title,
                    e.social_security_number, co.legal_name, co.address AS company_address,
                    co.postal_code AS company_pc, co.city AS company_city
             FROM payslips p
             JOIN payroll_periods per ON per.id = p.period_id
             JOIN employees e ON e.id = p.employee_id
             JOIN companies co ON co.id = e.company_id
             WHERE p.id = :id',
            ['id' => $entityId]
        );
        if ($payslip === null) {
            throw new NotFoundException('Bulletin de paie introuvable.');
        }
        $lines = $this->db->fetchAll(
            'SELECT * FROM payslip_lines WHERE payslip_id = :id ORDER BY sort_order, id',
            ['id' => $entityId]
        );

        $period = sprintf('%02d/%d', (int) $payslip['month'], (int) $payslip['year']);
        $title = 'Bulletin de paie ' . $period . ' — ' . $payslip['last_name'] . ' ' . $payslip['first_name'];
        $pdf->setDocumentTitle($title)->addPage();
        $pdf->h1('BULLETIN DE PAIE');

        $pdf->keyValueTable([
            ['Période', $period . ' (' . date('d/m/Y', strtotime((string) $payslip['starts_on']))
                . ' au ' . date('d/m/Y', strtotime((string) $payslip['ends_on'])) . ')'],
            ['Salarié', $payslip['first_name'] . ' ' . mb_strtoupper((string) $payslip['last_name'])],
            ['Matricule', (string) $payslip['employee_number']],
            ['Poste', (string) ($payslip['job_title'] ?: '—')],
            ['NIR', (string) ($payslip['social_security_number'] ?: '—')],
            ['Statut du bulletin', (string) $payslip['status']],
            ['Heures travaillées', number_format((float) $payslip['hours_worked'], 2, ',', ' ') . ' h'],
        ]);

        if ($lines !== []) {
            $pdf->h2('Détail des cotisations');
            $rows = [];
            foreach ($lines as $line) {
                $rows[] = [
                    (string) $line['label'],
                    number_format((float) $line['base_amount'], 2, ',', ' '),
                    rtrim(rtrim(number_format((float) $line['rate_employee'], 3, ',', ' '), '0'), ',') . ' %',
                    money_format((float) $line['amount_employee']),
                    money_format((float) $line['amount_employer']),
                ];
            }
            $pdf->dataTable(
                ['Cotisation', 'Base', 'Taux salarial', 'Part salariale', 'Part patronale'],
                $rows,
                [0, 30, 26, 32, 32],
                ['L', 'R', 'C', 'R', 'R']
            );
        }

        $pdf->h2('Synthèse de la rémunération');
        $pdf->totalsTable([
            ['Salaire brut', money_format((float) $payslip['gross_salary']), false],
            ['Total retenues salariales', money_format((float) $payslip['total_employee']), false],
            ['Réduction générale de cotisations', money_format((float) $payslip['general_reduction']), false],
            ['Net imposable', money_format((float) $payslip['net_taxable']), false],
            ['Net social', money_format((float) $payslip['net_social']), false],
            ['Net à payer', money_format((float) $payslip['net_payable']), true],
        ]);

        $pdf->keyValueTable([
            ['Total cotisations patronales', money_format((float) $payslip['total_employer'])],
            ['Règlement', (int) $payslip['is_paid'] === 1
                ? 'Payé' . ($payslip['payment_date'] ? ' le ' . date('d/m/Y', strtotime((string) $payslip['payment_date'])) : '')
                : 'En attente de paiement'],
        ]);

        $pdf->smallMuted('Document généré par Prime Business Suite. Les taux et plafonds appliqués sont ceux des règles de cotisation en vigueur à la période ; toute régularisation fait l\'objet d\'un bulletin correctif.');

        return $title;
    }
}
