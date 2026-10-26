<?php

declare(strict_types=1);

namespace Modules\Invoicing\Services;

use App\Core\Database\Connection;
use App\Core\Exception\AppException;
use App\Core\Exception\NotFoundException;
use Modules\Invoicing\Events\InvoiceIssued;
use Modules\Invoicing\Events\PaymentRecorded;
use App\Core\Event\EventDispatcher;

/**
 * Cycle de vie complet des factures de vente :
 * création (brouillon numéroté), émission, paiements partiels, lettrage, avoirs.
 * Toutes les écritures multi-tables sont transactionnelles.
 */
final class InvoiceService
{
    public function __construct(
        private readonly Connection $db,
        private readonly VatService $vat,
        private readonly DocumentNumberingService $numbering,
        private readonly EventDispatcher $events
    ) {
    }

    /**
     * Crée une facture en brouillon avec ses lignes et totaux calculés.
     *
     * @param array<string,mixed> $data third_party_id, issue_date, due_date, notes, lines[]
     */
    public function create(int $companyId, array $data, int $userId): int
    {
        $lines = $this->validateLines($data['lines'] ?? []);
        $totals = $this->vat->computeDocument($lines);

        $issueDate = (string) ($data['issue_date'] ?? date('Y-m-d'));
        $dueDate = (string) ($data['due_date'] ?? date('Y-m-d', strtotime($issueDate . ' +30 days')));
        if ($dueDate < $issueDate) {
            throw new AppException("La date d'échéance ne peut précéder la date d'émission.");
        }

        return $this->db->transaction(function () use ($companyId, $data, $totals, $issueDate, $dueDate, $userId) {
            $number = $this->numbering->next(DocumentNumberingService::KIND_INVOICE, new \DateTimeImmutable($issueDate));
            $this->db->execute(
                'INSERT INTO invoices (company_id, third_party_id, invoice_number, type, status, issue_date, due_date,
                                        total_ht, total_vat, total_ttc, amount_paid, balance, notes, created_by)
                 VALUES (:company, :party, :number, :type, :status, :issue, :due, :ht, :vat, :ttc, 0, :ttc, :notes, :user)',
                [
                    'company' => $companyId,
                    'party' => (int) $data['third_party_id'],
                    'number' => $number,
                    'type' => 'sale',
                    'status' => 'draft',
                    'issue' => $issueDate,
                    'due' => $dueDate,
                    'ht' => $totals['total_ht'],
                    'vat' => $totals['total_vat'],
                    'ttc' => $totals['total_ttc'],
                    'notes' => (string) ($data['notes'] ?? ''),
                    'user' => $userId,
                ]
            );
            $invoiceId = (int) $this->db->lastInsertId();
            $this->insertLines('invoice_lines', 'invoice_id', $invoiceId, $totals['lines']);

            audit('invoice.create', 'Invoice', $invoiceId, ['number' => $number, 'ttc' => $totals['total_ttc']]);
            return $invoiceId;
        });
    }

    /**
     * Émet la facture (brouillon -> émise) et notifie les modules abonnés
     * (comptabilité : écriture de vente).
     */
    public function issue(int $invoiceId, int $userId): void
    {
        $invoice = $this->requireInvoice($invoiceId);
        if ($invoice['status'] !== 'draft') {
            throw new AppException("Seule une facture en brouillon peut être émise (statut actuel : {$invoice['status']}).");
        }

        $this->db->beginTransaction();
        try {
            $this->db->execute(
                "UPDATE invoices SET status = 'issued', updated_at = now() WHERE id = :id",
                ['id' => $invoiceId]
            );
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        audit('invoice.issue', 'Invoice', $invoiceId, ['number' => $invoice['invoice_number']]);
        $this->events->dispatch(new InvoiceIssued($invoiceId, (int) $invoice['company_id'], (int) $invoice['third_party_id']));
    }

    /**
     * Enregistre un paiement (partiel ou solde), met à jour le lettrage et
     * génère le mouvement bancaire correspondant.
     *
     * @param array<string,mixed> $data amount, method, paid_on, bank_account_id, reference
     */
    public function registerPayment(int $invoiceId, array $data, int $userId): int
    {
        $invoice = $this->requireInvoice($invoiceId);
        if (in_array($invoice['status'], ['draft', 'cancelled'], true)) {
            throw new AppException('Impossible de payer une facture en brouillon ou annulée.');
        }

        $amount = round((float) ($data['amount'] ?? 0), 2);
        if ($amount <= 0.0) {
            throw new AppException('Le montant du paiement doit être strictement positif.');
        }
        if ($amount > (float) $invoice['balance'] + 0.001) {
            throw new AppException(
                sprintf('Paiement de %.2f € supérieur au solde dû de %.2f €.', $amount, (float) $invoice['balance'])
            );
        }

        return $this->db->transaction(function () use ($invoice, $invoiceId, $data, $amount, $userId) {
            $this->db->execute(
                'INSERT INTO payments (invoice_id, bank_account_id, paid_on, amount, method, reference, recorded_by)
                 VALUES (:invoice, :bank, :paid, :amount, :method, :ref, :user)',
                [
                    'invoice' => $invoiceId,
                    'bank' => !empty($data['bank_account_id']) ? (int) $data['bank_account_id'] : null,
                    'paid' => (string) ($data['paid_on'] ?? date('Y-m-d')),
                    'amount' => $amount,
                    'method' => (string) ($data['method'] ?? 'virement'),
                    'ref' => (string) ($data['reference'] ?? ''),
                    'user' => $userId,
                ]
            );
            $paymentId = (int) $this->db->lastInsertId();

            if (!empty($data['bank_account_id'])) {
                // Le mouvement bancaire n'est créé que si un compte est désigné.
                $this->db->execute(
                    'INSERT INTO bank_transactions (bank_account_id, transaction_date, value_date, label, amount,
                                                     direction, reference, source_type, source_id, is_reconciled)
                     VALUES (:bank, :d, :d, :label, :amount, :dir, :ref, :stype, :sid, false)',
                    [
                        'bank' => (int) $data['bank_account_id'],
                        'd' => (string) ($data['paid_on'] ?? date('Y-m-d')),
                        'label' => 'Encaissement facture ' . $invoice['invoice_number'],
                        'amount' => $amount,
                        'dir' => 'credit',
                        'ref' => (string) ($data['reference'] ?? ''),
                        'stype' => 'payment',
                        'sid' => $paymentId,
                    ]
                );
            }

            $newPaid = round((float) $invoice['amount_paid'] + $amount, 2);
            $newBalance = round((float) $invoice['total_ttc'] - $newPaid, 2);
            $newStatus = $newBalance <= 0.001 ? 'paid' : 'partial';
            $this->db->execute(
                'UPDATE invoices SET amount_paid = :paid, balance = :balance, status = :status, updated_at = now()
                 WHERE id = :id',
                ['paid' => $newPaid, 'balance' => max(0.0, $newBalance), 'status' => $newStatus, 'id' => $invoiceId]
            );

            audit('invoice.payment', 'Invoice', $invoiceId, ['payment_id' => $paymentId, 'amount' => $amount]);
            $this->events->dispatch(new PaymentRecorded($invoiceId, $paymentId, $amount, $newStatus));
            return $paymentId;
        });
    }

    /**
     * Crée un avoir (note de crédit), reprenant par défaut les lignes de la facture.
     *
     * @param list<array<string,mixed>>|null $lines null = reprise de toutes les lignes
     */
    public function createCreditNote(int $invoiceId, ?array $lines, string $reason, int $userId): int
    {
        $invoice = $this->requireInvoice($invoiceId);
        $sourceLines = $lines ?? $this->db->fetchAll(
            'SELECT label, quantity, unit_price, vat_rate, discount_pct FROM invoice_lines
             WHERE invoice_id = :id ORDER BY sort_order, id',
            ['id' => $invoiceId]
        );
        if ($sourceLines === []) {
            throw new AppException('Un avoir doit comporter au moins une ligne.');
        }
        $totals = $this->vat->computeDocument($sourceLines);

        return $this->db->transaction(function () use ($invoice, $invoiceId, $totals, $reason, $userId) {
            $number = $this->numbering->next(DocumentNumberingService::KIND_CREDIT);
            $this->db->execute(
                'INSERT INTO credit_notes (company_id, third_party_id, invoice_id, credit_number, status, issue_date,
                                            total_ht, total_vat, total_ttc, reason, created_by)
                 VALUES (:company, :party, :invoice, :number, :status, CURRENT_DATE, :ht, :vat, :ttc, :reason, :user)',
                [
                    'company' => (int) $invoice['company_id'],
                    'party' => (int) $invoice['third_party_id'],
                    'invoice' => $invoiceId,
                    'number' => $number,
                    'status' => 'issued',
                    'ht' => $totals['total_ht'],
                    'vat' => $totals['total_vat'],
                    'ttc' => $totals['total_ttc'],
                    'reason' => $reason,
                    'user' => $userId,
                ]
            );
            $creditId = (int) $this->db->lastInsertId();
            $this->insertLines('credit_note_lines', 'credit_note_id', $creditId, $totals['lines']);

            audit('credit_note.create', 'CreditNote', $creditId, ['number' => $number, 'invoice' => $invoice['invoice_number']]);
            return $creditId;
        });
    }

    public function cancel(int $invoiceId): void
    {
        $invoice = $this->requireInvoice($invoiceId);
        if ($invoice['status'] === 'paid') {
            throw new AppException('Une facture soldée ne peut être annulée ; émettez un avoir.');
        }
        $this->db->execute(
            "UPDATE invoices SET status = 'cancelled', updated_at = now() WHERE id = :id",
            ['id' => $invoiceId]
        );
        audit('invoice.cancel', 'Invoice', $invoiceId, ['number' => $invoice['invoice_number']]);
    }

    /**
     * @param list<array<string,mixed>> $rawLines
     * @return list<array<string,mixed>>
     */
    private function validateLines(array $rawLines): array
    {
        $clean = [];
        foreach ($rawLines as $line) {
            $label = trim((string) ($line['label'] ?? ''));
            if ($label === '') {
                throw new AppException('Chaque ligne doit avoir un libellé.');
            }
            $quantity = (float) ($line['quantity'] ?? 1);
            $unitPrice = (float) ($line['unit_price'] ?? 0);
            if ($quantity <= 0 || $unitPrice < 0) {
                throw new AppException("Quantité positive et prix unitaire non négatif requis pour la ligne « {$label} ».");
            }
            $vatRate = (float) ($line['vat_rate'] ?? 20.0);
            if (!in_array($vatRate, VatService::RATES, true)) {
                throw new AppException("Taux de TVA non autorisé : {$vatRate} %.");
            }
            $clean[] = [
                'label' => $label,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'vat_rate' => $vatRate,
                'discount_pct' => (float) ($line['discount_pct'] ?? 0),
            ];
        }
        if ($clean === []) {
            throw new AppException('Une facture doit comporter au moins une ligne.');
        }
        return $clean;
    }

    /**
     * Insertion des lignes calculées. La table credit_note_lines n'a pas de
     * colonne remise : le jeu de colonnes est donc adapté à la table cible.
     *
     * @param list<array<string,mixed>> $lines
     */
    private function insertLines(string $table, string $foreignKey, int $foreignId, array $lines): void
    {
        $hasDiscount = $table !== 'credit_note_lines';
        $columns = $hasDiscount
            ? "({$foreignKey}, label, quantity, unit_price, vat_rate, discount_pct, line_ht, line_vat, line_ttc, sort_order)"
            : "({$foreignKey}, label, quantity, unit_price, vat_rate, line_ht, line_vat, line_ttc, sort_order)";
        $placeholders = $hasDiscount
            ? '(:fk, :label, :qty, :price, :vat, :disc, :ht, :lvat, :ttc, :sort)'
            : '(:fk, :label, :qty, :price, :vat, :ht, :lvat, :ttc, :sort)';

        foreach ($lines as $line) {
            $params = [
                'fk' => $foreignId,
                'label' => $line['label'],
                'qty' => $line['quantity'],
                'price' => $line['unit_price'],
                'vat' => $line['vat_rate'],
                'ht' => $line['line_ht'],
                'lvat' => $line['line_vat'],
                'ttc' => $line['line_ttc'],
                'sort' => (int) $line['sort_order'],
            ];
            if ($hasDiscount) {
                $params['disc'] = $line['discount_pct'];
            }
            $this->db->execute("INSERT INTO {$table} {$columns} VALUES {$placeholders}", $params);
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function requireInvoice(int $invoiceId): array
    {
        $invoice = $this->db->fetchOne('SELECT * FROM invoices WHERE id = :id', ['id' => $invoiceId]);
        if ($invoice === null) {
            throw new NotFoundException('Facture introuvable.');
        }
        return $invoice;
    }
}
