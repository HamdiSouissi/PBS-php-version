<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use App\Core\Database\Connection;
use App\Core\Exception\NotFoundException;
use App\Core\Pdf\OnePageDocument;
use App\Core\Pdf\PdfDocument;
use App\Core\Pdf\PdfLabels;
use App\Core\Pdf\PdfSection;

/**
 * Bulletin de paie PDF : période, salarié, rémunération brute, cotisations
 * et net à payer, conformément aux lignes réellement calculées en base.
 *
 * La mise en page est compacte (densité typographique réduite) afin de
 * garantir un bulletin strictement mono-page, y compris avec le maximum
 * de lignes de cotisations rencontré en production.
 */
final class PayslipPdf implements PdfTemplate, OnePageDocument
{
    public function __construct(private readonly Connection $db)
    {
    }

    public function render(PdfDocument $pdf, int $entityId): string
    {
        $payslip = $this->db->fetchOne(
            'SELECT p.*, per.year, per.month, per.starts_on, per.ends_on,
                    e.employee_number, e.first_name, e.last_name, e.job_title,
                    e.social_security_number, co.legal_name, co.siret AS company_siret,
                    co.address AS company_address, co.postal_code AS company_pc,
                    co.city AS company_city
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

        // Compactage adaptatif : le bulletin doit tenir sur une seule page,
        // y compris avec un grand nombre de lignes de cotisations.
        $lineCount = count($lines);
        if ($lineCount > 20) {
            $pdf->setDensity(0.75)->setCompactRows(true);
        } elseif ($lineCount > 16) {
            $pdf->setDensity(0.78)->setCompactRows(true);
        } else {
            $pdf->setDensity(0.8);
        }

        $period = sprintf('%02d/%d', (int) $payslip['month'], (int) $payslip['year']);
        $title = 'Bulletin de paie ' . $period . ' — ' . $payslip['last_name'] . ' ' . $payslip['first_name'];
        $pdf->setDocumentTitle($title)->addPage();
        $pdf->h1('BULLETIN DE PAIE');

        // Blocs employeur et salarié clairement séparés, présentés côte à côte.
        $pdf->keyValueColumns(
            'Employeur',
            [
                ['Raison sociale', (string) $payslip['legal_name']],
                ['Adresse', implode(', ', array_filter([
                    (string) $payslip['company_address'],
                    trim(($payslip['company_pc'] ?? '') . ' ' . ($payslip['company_city'] ?? '')),
                ])) ?: '—'],
                ['SIRET', (string) ($payslip['company_siret'] ?: '—')],
            ],
            'Salarié',
            [
                ['Nom et prénom', $payslip['first_name'] . ' ' . mb_strtoupper((string) $payslip['last_name'])],
                ['Matricule', (string) $payslip['employee_number']],
                ['Poste', (string) ($payslip['job_title'] ?: '—')],
                ['NIR', (string) ($payslip['social_security_number'] ?: '—')],
            ],
            30
        );

        $pdf->h2('Période et situation');
        $pdf->keyValueTable([
            ['Période', $period . ' (' . date('d/m/Y', strtotime((string) $payslip['starts_on']))
                . ' au ' . date('d/m/Y', strtotime((string) $payslip['ends_on'])) . ')'],
            ['Statut du bulletin', PdfLabels::payslipStatus((string) $payslip['status'])],
            ['Heures travaillées', number_format((float) $payslip['hours_worked'], 2, ',', ' ') . ' h'],
        ], 42);

        if ($lines !== []) {
            $pdf->h2('Détail des cotisations');
            $rows = [];
            foreach ($this->groupLines($lines) as $group => $groupLines) {
                $rows[] = PdfSection::make($group);
                foreach ($groupLines as $line) {
                    $rows[] = [
                        (string) $line['label'],
                        number_format((float) $line['base_amount'], 2, ',', ' '),
                        rtrim(rtrim(number_format((float) $line['rate_employee'], 3, ',', ' '), '0'), ',') . ' %',
                        money_format((float) $line['amount_employee']),
                        money_format((float) $line['amount_employer']),
                    ];
                }
            }
            $pdf->dataTable(
                ['Cotisation', 'Base', 'Taux salarial', 'Part salariale', 'Part patronale'],
                $rows,
                [0, 26, 24, 31, 31],
                ['L', 'R', 'C', 'R', 'R']
            );
        }

        $pdf->h2('Synthèse de la rémunération');
        $pdf->totalsTable([
            ['Salaire brut', money_format((float) $payslip['gross_salary']), false],
            ['Total des retenues salariales', money_format((float) $payslip['total_employee']), false],
            ['Réduction générale de cotisations', money_format((float) $payslip['general_reduction']), false],
            ['Net imposable', money_format((float) $payslip['net_taxable']), false],
            ['Net social', money_format((float) $payslip['net_social']), false],
            ['Total des cotisations patronales', money_format((float) $payslip['total_employer']), false],
            ['Net à payer', money_format((float) $payslip['net_payable']), true],
        ]);

        $payment = (int) $payslip['is_paid'] === 1
            ? 'Payé' . ($payslip['payment_date'] ? ' le ' . date('d/m/Y', strtotime((string) $payslip['payment_date'])) : '')
            : 'En attente de paiement';

        // Ligne récapitulative compacte : cumuls annuels réels et solde de congés,
        // uniquement lorsque les données existent (aucune valeur inventée).
        $summary = [];
        $cumul = $this->db->fetchOne(
            'SELECT COUNT(*) AS bulletin_count,
                    COALESCE(SUM(p.gross_salary), 0) AS cumul_brut,
                    COALESCE(SUM(p.net_taxable), 0) AS cumul_imposable,
                    COALESCE(SUM(p.net_payable), 0) AS cumul_net
             FROM payslips p
             JOIN payroll_periods per ON per.id = p.period_id
             WHERE p.employee_id = :employee AND per.year = :year',
            ['employee' => $payslip['employee_id'], 'year' => $payslip['year']]
        );
        if ($cumul !== null && (int) $cumul['bulletin_count'] >= 2) {
            $summary[] = 'Cumuls ' . (int) $payslip['year'] . ' (' . (int) $cumul['bulletin_count']
                . ' bulletins) — brut ' . money_format((float) $cumul['cumul_brut'])
                . ' ; net imposable ' . money_format((float) $cumul['cumul_imposable'])
                . ' ; net à payer ' . money_format((float) $cumul['cumul_net']) . '.';
        }

        $leaveDays = (float) $this->db->fetchScalar(
            'SELECT COALESCE(SUM(remaining_days), 0) FROM leave_balances WHERE employee_id = :id',
            ['id' => $payslip['employee_id']]
        );
        if ($leaveDays > 0.0) {
            $summary[] = 'Solde de congés restant : ' . number_format($leaveDays, 1, ',', ' ') . ' jours.';
        }
        $summary[] = 'Règlement : ' . $payment . '.';
        $pdf->smallMuted(implode(' ', $summary));

        $pdf->smallMuted('Document généré par Prime Business Suite. Les taux et plafonds appliqués sont ceux des règles de cotisation en vigueur à la période ; toute régularisation fait l\'objet d\'un bulletin correctif.');

        return $title;
    }

    /**
     * Regroupe les lignes de cotisations par famille, dans un ordre stable,
     * sans jamais modifier les montants ni l'ordre d'origine dans chaque groupe.
     *
     * @param list<array<string,string>> $lines
     * @return array<string,list<array<string,string>>>
     */
    private function groupLines(array $lines): array
    {
        $groups = [
            'Santé' => [],
            'Retraite et vieillesse' => [],
            'Famille et accidents du travail' => [],
            'Chômage et garantie des salaires' => [],
            'CSG et CRDS' => [],
            'Autres cotisations' => [],
        ];
        foreach ($lines as $line) {
            $groups[$this->groupOf((string) $line['label'])][] = $line;
        }
        return array_filter($groups, static fn (array $items): bool => $items !== []);
    }

    private function groupOf(string $label): string
    {
        $label = mb_strtolower($label);
        if (str_starts_with($label, 'csg') || str_starts_with($label, 'crds')) {
            return 'CSG et CRDS';
        }
        if (str_contains($label, 'chômage') || str_contains($label, 'ags')) {
            return 'Chômage et garantie des salaires';
        }
        if (str_contains($label, 'maladie')) {
            return 'Santé';
        }
        if (str_contains($label, 'vieillesse') || str_contains($label, 'retraite') || str_contains($label, 'équilibre')) {
            return 'Retraite et vieillesse';
        }
        if (str_contains($label, 'familiales') || str_contains($label, 'accidents')) {
            return 'Famille et accidents du travail';
        }
        return 'Autres cotisations';
    }
}
