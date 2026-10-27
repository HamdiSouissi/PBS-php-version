<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use App\Core\Database\Connection;
use App\Core\Exception\NotFoundException;
use App\Core\Pdf\PdfDocument;

/**
 * Fiche client : coordonnées, encours, factures, devis, contrats et abonnements.
 */
final class ClientSheetPdf implements PdfTemplate
{
    public function __construct(private readonly Connection $db)
    {
    }

    public function render(PdfDocument $pdf, int $entityId): string
    {
        $client = $this->db->fetchOne('SELECT * FROM third_parties WHERE id = :id', ['id' => $entityId]);
        if ($client === null) {
            throw new NotFoundException('Client introuvable.');
        }

        $stats = $this->db->fetchOne(
            "SELECT COUNT(*) AS invoice_count,
                    COALESCE(SUM(total_ttc), 0) AS billed_ttc,
                    COALESCE(SUM(balance), 0) AS outstanding
             FROM invoices WHERE third_party_id = :id AND type = 'sale'",
            ['id' => $entityId]
        );
        $invoices = $this->db->fetchAll(
            "SELECT invoice_number, issue_date, total_ttc, status FROM invoices
             WHERE third_party_id = :id AND type = 'sale' ORDER BY issue_date DESC, id DESC LIMIT 10",
            ['id' => $entityId]
        );
        $contracts = $this->db->fetchAll(
            'SELECT contract_number, title, starts_on, ends_on, amount_ht, status
             FROM client_contracts WHERE third_party_id = :id ORDER BY starts_on DESC',
            ['id' => $entityId]
        );
        $subscriptions = $this->db->fetchAll(
            'SELECT sp.label, s.status, s.quantity, s.unit_amount_ht, s.next_invoice_date
             FROM subscriptions s JOIN subscription_plans sp ON sp.id = s.plan_id
             WHERE s.third_party_id = :id ORDER BY s.next_invoice_date',
            ['id' => $entityId]
        );

        $title = 'Fiche client — ' . $client['name'];
        $pdf->setDocumentTitle($title)->addPage();
        $pdf->h1($title);

        $pdf->h2('Coordonnées');
        $pdf->keyValueTable([
            ['Code', (string) $client['code']],
            ['Raison sociale', (string) $client['name']],
            ['Forme juridique', (string) ($client['legal_form'] ?: '—')],
            ['SIREN / SIRET', trim(($client['siren'] ?: '—') . ' / ' . ($client['siret'] ?: '—'))],
            ['TVA intracommunautaire', (string) ($client['vat_number'] ?: '—')],
            ['Adresse', implode(', ', array_filter([
                $client['address'],
                trim($client['postal_code'] . ' ' . $client['city']),
            ])) ?: '—'],
            ['E-mail', (string) ($client['email'] ?: '—')],
            ['Téléphone', (string) ($client['phone'] ?: '—')],
            ['Conditions de règlement', (int) $client['payment_terms_days'] . ' jours, ' . $client['payment_method']],
        ]);

        $pdf->h2('Indicateurs commerciaux');
        $pdf->keyValueTable([
            ['Factures émises', (string) ($stats['invoice_count'] ?? 0)],
            ['Total facturé TTC', money_format((float) ($stats['billed_ttc'] ?? 0))],
            ['Encours restant dû', money_format((float) ($stats['outstanding'] ?? 0))],
        ]);

        if ($invoices !== []) {
            $pdf->h2('Dernières factures');
            $rows = [];
            foreach ($invoices as $invoice) {
                $rows[] = [
                    (string) $invoice['invoice_number'],
                    date('d/m/Y', strtotime((string) $invoice['issue_date'])),
                    money_format((float) $invoice['total_ttc']),
                    (string) $invoice['status'],
                ];
            }
            $pdf->dataTable(['N° facture', 'Date', 'Montant TTC', 'Statut'], $rows, [45, 35, 45, 0], ['L', 'L', 'R', 'L']);
        }

        if ($contracts !== []) {
            $pdf->h2('Contrats');
            $rows = [];
            foreach ($contracts as $contract) {
                $rows[] = [
                    (string) $contract['contract_number'],
                    (string) $contract['title'],
                    date('d/m/Y', strtotime((string) $contract['starts_on'])),
                    money_format((float) $contract['amount_ht']),
                    (string) $contract['status'],
                ];
            }
            $pdf->dataTable(['Référence', 'Intitulé', 'Effet', 'Montant HT', 'Statut'], $rows, [32, 0, 28, 32, 24]);
        }

        if ($subscriptions !== []) {
            $pdf->h2('Abonnements');
            $rows = [];
            foreach ($subscriptions as $subscription) {
                $rows[] = [
                    (string) $subscription['label'],
                    (string) $subscription['status'],
                    'x' . (string) $subscription['quantity'],
                    money_format((float) $subscription['unit_amount_ht']),
                    date('d/m/Y', strtotime((string) $subscription['next_invoice_date'])),
                ];
            }
            $pdf->dataTable(['Offre', 'Statut', 'Qté', 'Prix unit. HT', 'Prochaine facture'], $rows, [0, 28, 18, 36, 38]);
        }

        return $title;
    }
}
