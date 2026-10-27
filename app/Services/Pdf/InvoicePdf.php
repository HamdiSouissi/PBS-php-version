<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use App\Core\Database\Connection;
use App\Core\Exception\NotFoundException;
use App\Core\Pdf\PdfDocument;

/**
 * Facture client PDF : en-tête société, adresse de facturation, lignes
 * avec TVA, totaux et conditions de règlement.
 */
final class InvoicePdf implements PdfTemplate
{
    public function __construct(private readonly Connection $db)
    {
    }

    public function render(PdfDocument $pdf, int $entityId): string
    {
        $invoice = $this->db->fetchOne(
            'SELECT i.*, t.name AS client_name, t.address AS client_address, t.postal_code AS client_pc,
                    t.city AS client_city, t.email AS client_email, t.vat_number AS client_vat
             FROM invoices i JOIN third_parties t ON t.id = i.third_party_id
             WHERE i.id = :id',
            ['id' => $entityId]
        );
        if ($invoice === null) {
            throw new NotFoundException('Facture introuvable.');
        }
        $lines = $this->db->fetchAll(
            'SELECT * FROM invoice_lines WHERE invoice_id = :id ORDER BY sort_order, id',
            ['id' => $entityId]
        );
        $bank = $this->db->fetchOne(
            'SELECT * FROM bank_accounts WHERE is_active = 1 ORDER BY id LIMIT 1'
        );
        $company = $this->db->fetchOne('SELECT * FROM companies ORDER BY is_default DESC, id ASC LIMIT 1') ?? [];

        $title = 'Facture ' . $invoice['invoice_number'];
        $pdf->setDocumentTitle($title)->addPage();
        $pdf->h1($title);

        $pdf->keyValueTable([
            ['Numéro', (string) $invoice['invoice_number']],
            ["Date d'émission", date('d/m/Y', strtotime((string) $invoice['issue_date']))],
            ['Date d\'échéance', date('d/m/Y', strtotime((string) $invoice['due_date']))],
            ['Statut', $this->statusLabel((string) $invoice['status'])],
        ]);

        $pdf->h2('Adresse de facturation');
        $address = array_filter([
            $invoice['client_name'],
            $invoice['client_address'],
            trim($invoice['client_pc'] . ' ' . $invoice['client_city']),
            $invoice['client_vat'] ? 'TVA : ' . $invoice['client_vat'] : '',
            $invoice['client_email'],
        ]);
        $pdf->paragraph(implode("\n", $address));

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
            ['Total HT', money_format((float) $invoice['total_ht']), false],
            ['TVA', money_format((float) $invoice['total_vat']), false],
            ['Total TTC', money_format((float) $invoice['total_ttc']), true],
            ['Déjà réglé', money_format((float) $invoice['amount_paid']), false],
            ['Solde restant dû', money_format((float) $invoice['balance']), false],
        ]);

        if ($bank !== null) {
            $pdf->h2('Coordonnées de règlement');
            $pdf->paragraph(implode("\n", array_filter([
                $bank['label'] . ($bank['bank_name'] ? ' — ' . $bank['bank_name'] : ''),
                'IBAN : ' . $bank['iban'],
                $bank['bic'] ? 'BIC : ' . $bank['bic'] : '',
            ])));
        }
        if (($invoice['notes'] ?? '') !== '') {
            $pdf->h2('Notes');
            $pdf->paragraph((string) $invoice['notes']);
        }
        $pdf->smallMuted('Conditions de paiement : 30 jours nets. En cas de retard, des pénalités peuvent être appliquées (art. L441-10 Code de commerce).');
        if (($company['vat_number'] ?? '') !== '') {
            $pdf->smallMuted('N° TVA intracommunautaire : ' . $company['vat_number']);
        }

        return $title;
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'draft' => 'Brouillon',
            'issued' => 'Émise',
            'partial' => 'Paiement partiel',
            'paid' => 'Payée',
            'overdue' => 'En retard',
            'cancelled' => 'Annulée',
            default => $status,
        };
    }
}
