<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use App\Core\Database\Connection;
use App\Core\Exception\NotFoundException;
use App\Core\Pdf\PdfDocument;

/**
 * Devis client PDF.
 */
final class QuotePdf implements PdfTemplate
{
    public function __construct(private readonly Connection $db)
    {
    }

    public function render(PdfDocument $pdf, int $entityId): string
    {
        $quote = $this->db->fetchOne(
            'SELECT q.*, t.name AS client_name, t.address AS client_address, t.postal_code AS client_pc,
                    t.city AS client_city, t.vat_number AS client_vat
             FROM quotes q JOIN third_parties t ON t.id = q.third_party_id
             WHERE q.id = :id',
            ['id' => $entityId]
        );
        if ($quote === null) {
            throw new NotFoundException('Devis introuvable.');
        }
        $lines = $this->db->fetchAll(
            'SELECT * FROM quote_lines WHERE quote_id = :id ORDER BY sort_order, id',
            ['id' => $entityId]
        );

        $title = 'Devis ' . $quote['quote_number'];
        $pdf->setDocumentTitle($title)->addPage();
        $pdf->h1($title);

        $pdf->keyValueTable([
            ['Numéro', (string) $quote['quote_number']],
            ["Date d'émission", date('d/m/Y', strtotime((string) $quote['issue_date']))],
            ['Validité', $quote['valid_until'] ? date('d/m/Y', strtotime((string) $quote['valid_until'])) : '—'],
            ['Statut', $this->statusLabel((string) $quote['status'])],
        ]);

        $pdf->h2('Adresse de facturation');
        $pdf->paragraph(implode("\n", array_filter([
            $quote['client_name'],
            $quote['client_address'],
            trim($quote['client_pc'] . ' ' . $quote['client_city']),
            $quote['client_vat'] ? 'TVA : ' . $quote['client_vat'] : '',
        ])));

        $pdf->h2('Prestations');
        $rows = [];
        foreach ($lines as $line) {
            $rows[] = [
                (string) $line['label'],
                number_format((float) $line['quantity'], 2, ',', ' '),
                money_format((float) $line['unit_price']),
                rtrim(rtrim(number_format((float) $line['vat_rate'], 3, ',', ' '), '0'), ',') . ' %',
                money_format((float) $line['line_ht']),
            ];
        }
        $pdf->dataTable(
            ['Désignation', 'Qté', 'Prix unit. HT', 'TVA', 'Montant HT'],
            $rows,
            [0, 16, 34, 22, 34],
            ['L', 'C', 'R', 'C', 'R']
        );

        $pdf->totalsTable([
            ['Total HT', money_format((float) $quote['total_ht']), false],
            ['TVA', money_format((float) $quote['total_vat']), false],
            ['Total TTC', money_format((float) $quote['total_ttc']), true],
        ]);

        if (($quote['notes'] ?? '') !== '') {
            $pdf->h2('Notes');
            $pdf->paragraph((string) $quote['notes']);
        }
        $pdf->smallMuted('Ce devis est valable 30 jours à compter de sa date d\'émission. Bon pour accord :');
        $pdf->signatureBlock('Fait à ……………………, le ……………………', 'Signature du client (précédée de « Bon pour accord »)');

        return $title;
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'draft' => 'Brouillon',
            'sent' => 'Envoyé',
            'accepted' => 'Accepté',
            'rejected' => 'Refusé',
            'invoiced' => 'Facturé',
            'expired' => 'Expiré',
            default => $status,
        };
    }
}
