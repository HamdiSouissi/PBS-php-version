<?php

declare(strict_types=1);

namespace Modules\Invoicing\Services;

use App\Core\Database\Connection;
use App\Core\Exception\AppException;
use App\Core\Exception\NotFoundException;

/**
 * Devis : création, envoi, acceptation/refus et transformation en facture.
 */
final class QuoteService
{
    public function __construct(
        private readonly Connection $db,
        private readonly VatService $vat,
        private readonly DocumentNumberingService $numbering,
        private readonly InvoiceService $invoices
    ) {
    }

    /**
     * @param array<string,mixed> $data third_party_id, issue_date, valid_until, notes, lines[]
     */
    public function create(int $companyId, array $data, int $userId): int
    {
        $totals = $this->vat->computeDocument($this->requireLines($data['lines'] ?? []));

        return $this->db->transaction(function () use ($companyId, $data, $totals, $userId) {
            $number = $this->numbering->next(DocumentNumberingService::KIND_QUOTE);
            $this->db->execute(
                'INSERT INTO quotes (company_id, third_party_id, quote_number, status, issue_date, valid_until,
                                      total_ht, total_vat, total_ttc, notes, created_by)
                 VALUES (:company, :party, :number, :status, :issue, :valid, :ht, :vat, :ttc, :notes, :user)',
                [
                    'company' => $companyId,
                    'party' => (int) $data['third_party_id'],
                    'number' => $number,
                    'status' => 'draft',
                    'issue' => (string) ($data['issue_date'] ?? date('Y-m-d')),
                    'valid' => $data['valid_until'] ?? null,
                    'ht' => $totals['total_ht'],
                    'vat' => $totals['total_vat'],
                    'ttc' => $totals['total_ttc'],
                    'notes' => (string) ($data['notes'] ?? ''),
                    'user' => $userId,
                ]
            );
            $quoteId = (int) $this->db->lastInsertId();
            foreach ($totals['lines'] as $line) {
                $this->db->execute(
                    'INSERT INTO quote_lines (quote_id, label, quantity, unit_price, vat_rate, discount_pct,
                                               line_ht, line_vat, line_ttc, sort_order)
                     VALUES (:q, :label, :qty, :price, :vat, :disc, :ht, :lvat, :ttc, :sort)',
                    [
                        'q' => $quoteId,
                        'label' => $line['label'],
                        'qty' => $line['quantity'],
                        'price' => $line['unit_price'],
                        'vat' => $line['vat_rate'],
                        'disc' => $line['discount_pct'],
                        'ht' => $line['line_ht'],
                        'lvat' => $line['line_vat'],
                        'ttc' => $line['line_ttc'],
                        'sort' => (int) $line['sort_order'],
                    ]
                );
            }
            audit('quote.create', 'Quote', $quoteId, ['number' => $number]);
            return $quoteId;
        });
    }

    public function markSent(int $quoteId): void
    {
        $this->transition($quoteId, ['draft', 'accepted'], 'sent');
    }

    public function accept(int $quoteId): void
    {
        $this->transition($quoteId, ['draft', 'sent'], 'accepted');
    }

    public function reject(int $quoteId): void
    {
        $this->transition($quoteId, ['draft', 'sent'], 'rejected');
    }

    /**
     * Transforme un devis accepté en facture (brouillon numéroté).
     */
    public function convertToInvoice(int $quoteId, int $userId): int
    {
        $quote = $this->requireQuote($quoteId);
        if ($quote['status'] !== 'accepted') {
            throw new AppException("Seul un devis accepté peut être facturé (statut actuel : {$quote['status']}).");
        }

        $lines = $this->db->fetchAll(
            'SELECT label, quantity, unit_price, vat_rate, discount_pct FROM quote_lines
             WHERE quote_id = :id ORDER BY sort_order, id',
            ['id' => $quoteId]
        );

        return $this->db->transaction(function () use ($quote, $quoteId, $lines, $userId) {
            $invoiceId = $this->invoices->create(
                (int) $quote['company_id'],
                [
                    'third_party_id' => (int) $quote['third_party_id'],
                    'issue_date' => date('Y-m-d'),
                    'due_date' => date('Y-m-d', strtotime('+30 days')),
                    'notes' => 'Facture issue du devis ' . $quote['quote_number'],
                    'lines' => $lines,
                ],
                $userId
            );
            $this->db->execute(
                "UPDATE quotes SET status = 'invoiced', converted_invoice_id = :invoice, updated_at = now()
                 WHERE id = :id",
                ['invoice' => $invoiceId, 'id' => $quoteId]
            );
            audit('quote.convert', 'Quote', $quoteId, ['invoice_id' => $invoiceId]);
            return $invoiceId;
        });
    }

    /**
     * @param list<string> $from
     */
    private function transition(int $quoteId, array $from, string $to): void
    {
        $quote = $this->requireQuote($quoteId);
        if (!in_array($quote['status'], $from, true)) {
            throw new AppException("Transition {$quote['status']} → {$to} impossible pour le devis {$quote['quote_number']}.");
        }
        $this->db->execute(
            'UPDATE quotes SET status = :status, updated_at = now() WHERE id = :id',
            ['status' => $to, 'id' => $quoteId]
        );
        audit('quote.' . $to, 'Quote', $quoteId);
    }

    /**
     * @return array<string,mixed>
     */
    private function requireQuote(int $quoteId): array
    {
        $quote = $this->db->fetchOne('SELECT * FROM quotes WHERE id = :id', ['id' => $quoteId]);
        if ($quote === null) {
            throw new NotFoundException('Devis introuvable.');
        }
        return $quote;
    }

    /**
     * @param list<array<string,mixed>> $rawLines
     * @return list<array<string,mixed>>
     */
    private function requireLines(array $rawLines): array
    {
        if ($rawLines === []) {
            throw new AppException('Un devis doit comporter au moins une ligne.');
        }
        return $rawLines;
    }
}
