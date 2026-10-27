<?php

declare(strict_types=1);

namespace Modules\Invoicing\Controllers;

use App\Core\Database\Connection;
use App\Core\Http\Controller;
use App\Core\Http\RedirectResponse;
use App\Core\Http\Request;
use App\Core\Http\Response;
use Modules\Invoicing\Repositories\InvoiceRepository;
use Modules\Invoicing\Services\EInvoiceService;
use Modules\Invoicing\Services\InvoiceService;

final class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceRepository $repository,
        private readonly InvoiceService $invoices,
        private readonly EInvoiceService $eInvoices,
        private readonly Connection $db
    ) {
    }

    public function index(Request $request): Response
    {
        $this->auth()->requirePermission('invoicing.read');
        $page = max(1, (int) $request->query('page', 1));
        $status = (string) $request->query('status', '');
        $result = $this->repository->paginateInvoices($page, 20, 'sale', $status);
        return $this->view('Invoicing::invoices/index', [
            'title' => 'Factures',
            'invoices' => $result['rows'],
            'pager' => $result,
            'status' => $status,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->auth()->requirePermission('invoicing.write');
        return $this->view('Invoicing::invoices/form', [
            'title' => 'Nouvelle facture',
            'clients' => $this->clients(),
            'bankAccounts' => $this->bankAccounts(),
            'vatRates' => \Modules\Invoicing\Services\VatService::RATES,
        ]);
    }

    public function store(Request $request): Response
    {
        $this->auth()->requirePermission('invoicing.write');
        $companyId = (int) $this->db->fetchScalar('SELECT id FROM companies WHERE is_default LIMIT 1');
        $lines = $this->extractLines($request);
        $invoiceId = $this->invoices->create($companyId, [
            'third_party_id' => (int) $request->input('third_party_id'),
            'issue_date' => (string) $request->input('issue_date', date('Y-m-d')),
            'due_date' => (string) $request->input('due_date', date('Y-m-d', strtotime('+30 days'))),
            'notes' => (string) $request->input('notes', ''),
            'lines' => $lines,
        ], (int) $this->auth()->id());
        flash('success', 'Facture enregistrée en brouillon.');
        return new RedirectResponse("/invoicing/invoices/{$invoiceId}");
    }

    public function show(Request $request, int $id): Response
    {
        $this->auth()->requirePermission('invoicing.read');
        $invoice = $this->repository->findDetailed($id);
        if ($invoice === null) {
            throw new \App\Core\Exception\NotFoundException('Facture introuvable.');
        }
        return $this->view('Invoicing::invoices/show', [
            'title' => 'Facture ' . $invoice['invoice_number'],
            'invoice' => $invoice,
            'bankAccounts' => $this->bankAccounts(),
            'outbox' => $this->db->fetchAll(
                'SELECT * FROM e_invoice_outbox WHERE invoice_id = :id ORDER BY id DESC',
                ['id' => $id]
            ),
        ]);
    }

    public function issue(Request $request, int $id): Response
    {
        $this->auth()->requirePermission('invoicing.validate');
        $this->invoices->issue($id, (int) $this->auth()->id());
        flash('success', 'Facture émise.');
        return new RedirectResponse("/invoicing/invoices/{$id}");
    }

    public function payment(Request $request, int $id): Response
    {
        $this->auth()->requirePermission('invoicing.payments');
        $this->invoices->registerPayment($id, [
            'amount' => (float) $request->input('amount'),
            'method' => (string) $request->input('method', 'virement'),
            'paid_on' => (string) $request->input('paid_on', date('Y-m-d')),
            'bank_account_id' => $request->input('bank_account_id') ?: null,
            'reference' => (string) $request->input('reference', ''),
        ], (int) $this->auth()->id());
        flash('success', 'Paiement enregistré et lettrage mis à jour.');
        return new RedirectResponse("/invoicing/invoices/{$id}");
    }

    public function creditNote(Request $request, int $id): Response
    {
        $this->auth()->requirePermission('invoicing.validate');
        $creditId = $this->invoices->createCreditNote($id, null, (string) $request->input('reason', 'Avoir commercial'), (int) $this->auth()->id());
        flash('warning', "Avoir n°{$creditId} créé.");
        return new RedirectResponse("/invoicing/invoices/{$id}");
    }

    public function cancel(Request $request, int $id): Response
    {
        $this->auth()->requirePermission('invoicing.validate');
        $this->invoices->cancel($id);
        flash('warning', 'Facture annulée.');
        return new RedirectResponse("/invoicing/invoices/{$id}");
    }

    public function generateEInvoice(Request $request, int $id): Response
    {
        $this->auth()->requirePermission('invoicing.einvoice');
        $format = (string) $request->input('format', 'UBL');
        $result = $this->eInvoices->generate($id, $format);
        flash('success', "Fichier {$format} généré (empreinte SHA-256 enregistrée).");
        return new RedirectResponse("/invoicing/invoices/{$id}");
    }

    public function markExported(Request $request, int $outboxId): Response
    {
        $this->auth()->requirePermission('invoicing.einvoice');
        $row = $this->db->fetchOne('SELECT invoice_id FROM e_invoice_outbox WHERE id = :id', ['id' => $outboxId]);
        $this->eInvoices->markExported($outboxId, (string) $request->input('external_reference', ''));
        flash('info', 'Message marqué comme exporté. La preuve de dépôt PPF/PDP reste externe.');
        return new RedirectResponse('/invoicing/invoices/' . (int) ($row['invoice_id'] ?? 0));
    }

    public function downloadEInvoice(Request $request, int $outboxId): Response
    {
        $this->auth()->requirePermission('invoicing.einvoice');
        $row = $this->db->fetchOne('SELECT * FROM e_invoice_outbox WHERE id = :id', ['id' => $outboxId]);
        if ($row === null || !is_file($row['file_path'])) {
            throw new \App\Core\Exception\NotFoundException('Fichier e-facturation introuvable.');
        }
        return Response::download($row['file_path'], 'application/xml');
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function extractLines(Request $request): array
    {
        $raw = $request->all()['lines'] ?? [];
        $labels = $raw['label'] ?? [];
        $quantities = $raw['quantity'] ?? [];
        $prices = $raw['unit_price'] ?? [];
        $rates = $raw['vat_rate'] ?? [];
        $lines = [];
        foreach ($labels as $index => $label) {
            if (trim((string) $label) === '') {
                continue;
            }
            $lines[] = [
                'label' => (string) $label,
                'quantity' => (float) ($quantities[$index] ?? 1),
                'unit_price' => (float) ($prices[$index] ?? 0),
                'vat_rate' => (float) ($rates[$index] ?? 20.0),
                'discount_pct' => 0,
            ];
        }
        return $lines;
    }

    private function clients(): array
    {
        return $this->db->fetchAll(
            "SELECT id, name, code FROM third_parties WHERE type IN ('client','both') AND is_active ORDER BY name"
        );
    }

    private function bankAccounts(): array
    {
        return $this->db->fetchAll('SELECT id, label, iban FROM bank_accounts ORDER BY label');
    }
}
