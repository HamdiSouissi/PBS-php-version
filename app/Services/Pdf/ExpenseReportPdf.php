<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use App\Core\Database\Connection;
use App\Core\Exception\NotFoundException;
use App\Core\Pdf\PdfDocument;

/**
 * Note de frais PDF : en-tête salarié, lignes de dépenses et totaux.
 */
final class ExpenseReportPdf implements PdfTemplate
{
    public function __construct(private readonly Connection $db)
    {
    }

    public function render(PdfDocument $pdf, int $entityId): string
    {
        $report = $this->db->fetchOne(
            'SELECT r.*, e.employee_number, e.first_name, e.last_name, co.legal_name
             FROM expense_reports r
             JOIN employees e ON e.id = r.employee_id
             JOIN companies co ON co.id = e.company_id
             WHERE r.id = :id',
            ['id' => $entityId]
        );
        if ($report === null) {
            throw new NotFoundException('Note de frais introuvable.');
        }
        $lines = $this->db->fetchAll(
            'SELECT * FROM expense_lines WHERE report_id = :id ORDER BY spent_on, id',
            ['id' => $entityId]
        );

        $title = 'Note de frais n°' . $entityId . ' — ' . $report['last_name'] . ' ' . $report['first_name'];
        $pdf->setDocumentTitle($title)->addPage();
        $pdf->h1('NOTE DE FRAIS');

        $pdf->keyValueTable([
            ['Référence', 'NF-' . str_pad((string) $entityId, 4, '0', STR_PAD_LEFT)],
            ['Salarié', $report['first_name'] . ' ' . mb_strtoupper((string) $report['last_name']) . ' (' . $report['employee_number'] . ')'],
            ['Intitulé', (string) $report['title']],
            ['Statut', $this->statusLabel((string) $report['status'])],
            ['Date de soumission', $report['submitted_at'] ? date('d/m/Y', strtotime((string) $report['submitted_at'])) : '—'],
            ['Date de paiement', $report['paid_on'] ? date('d/m/Y', strtotime((string) $report['paid_on'])) : '—'],
        ]);

        $rows = [];
        $totalVat = 0.0;
        foreach ($lines as $line) {
            $totalVat += (float) $line['vat_amount'];
            $rows[] = [
                date('d/m/Y', strtotime((string) $line['spent_on'])),
                $this->categoryLabel((string) $line['category']),
                (string) $line['label'],
                money_format((float) $line['amount']),
                money_format((float) $line['vat_amount']),
            ];
        }
        $pdf->h2('Dépenses engagées');
        $pdf->dataTable(
            ['Date', 'Catégorie', 'Libellé', 'Montant TTC', 'TVA'],
            $rows,
            [24, 34, 0, 32, 28],
            ['L', 'L', 'L', 'R', 'R']
        );

        $pdf->totalsTable([
            ['TVA récupérable', money_format($totalVat), false],
            ['Total TTC', money_format((float) $report['total_amount']), true],
        ]);

        if (($report['notes'] ?? '') !== '') {
            $pdf->h2('Notes');
            $pdf->paragraph((string) $report['notes']);
        }
        $pdf->smallMuted('Les justificatifs correspondants sont conservés dans le centre documentaire de l\'application.');
        $pdf->signatureBlock('Signature du salarié', 'Validation du manager');

        return $title;
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'draft' => 'Brouillon',
            'submitted' => 'Soumise',
            'approved' => 'Validée',
            'rejected' => 'Rejetée',
            'paid' => 'Payée',
            default => $status,
        };
    }

    private function categoryLabel(string $category): string
    {
        return match ($category) {
            'transport' => 'Transport',
            'meal' => 'Repas',
            'accommodation' => 'Hébergement',
            'fuel' => 'Carburant',
            'kilometric' => 'Frais kilométriques',
            'supplies' => 'Fournitures',
            default => 'Autre',
        };
    }
}
