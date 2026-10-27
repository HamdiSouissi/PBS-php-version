<?php

declare(strict_types=1);

namespace Modules\Invoicing\Controllers;

use App\Core\Database\Connection;
use App\Core\Exception\NotFoundException;
use App\Core\Http\Controller;
use App\Core\Http\RedirectResponse;
use App\Core\Http\Request;
use App\Core\Http\Response;
use Modules\Invoicing\Services\QuoteService;
use Modules\Invoicing\Services\VatService;

final class QuoteController extends Controller
{
    public function __construct(
        private readonly QuoteService $quotes,
        private readonly Connection $db
    ) {
    }

    public function index(Request $request): Response
    {
        $this->auth()->requirePermission('invoicing.read');
        $quotes = $this->db->fetchAll(
            "SELECT q.*, tp.name AS third_party_name FROM quotes q
             JOIN third_parties tp ON tp.id = q.third_party_id
             ORDER BY q.issue_date DESC, q.id DESC LIMIT 200"
        );
        return $this->view('Invoicing::quotes/index', [
            'title' => 'Devis',
            'quotes' => $quotes,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->auth()->requirePermission('invoicing.write');
        return $this->view('Invoicing::quotes/form', [
            'title' => 'Nouveau devis',
            'clients' => $this->db->fetchAll(
                "SELECT id, name FROM third_parties WHERE type IN ('client','both') AND is_active ORDER BY name"
            ),
            'vatRates' => VatService::RATES,
        ]);
    }

    public function store(Request $request): Response
    {
        $this->auth()->requirePermission('invoicing.write');
        $companyId = (int) $this->db->fetchScalar('SELECT id FROM companies WHERE is_default LIMIT 1');
        $raw = $request->all()['lines'] ?? [];
        $lines = [];
        foreach (($raw['label'] ?? []) as $index => $label) {
            if (trim((string) $label) === '') {
                continue;
            }
            $lines[] = [
                'label' => (string) $label,
                'quantity' => (float) ($raw['quantity'][$index] ?? 1),
                'unit_price' => (float) ($raw['unit_price'][$index] ?? 0),
                'vat_rate' => (float) ($raw['vat_rate'][$index] ?? 20.0),
                'discount_pct' => 0,
            ];
        }
        $quoteId = $this->quotes->create($companyId, [
            'third_party_id' => (int) $request->input('third_party_id'),
            'issue_date' => (string) $request->input('issue_date', date('Y-m-d')),
            'valid_until' => $request->input('valid_until') ?: null,
            'notes' => (string) $request->input('notes', ''),
            'lines' => $lines,
        ], (int) $this->auth()->id());
        flash('success', 'Devis enregistré.');
        return new RedirectResponse('/invoicing/quotes');
    }

    public function markSent(Request $request, int $id): Response
    {
        $this->auth()->requirePermission('invoicing.write');
        $this->quotes->markSent($id);
        flash('info', 'Devis marqué comme envoyé.');
        return new RedirectResponse('/invoicing/quotes');
    }

    public function accept(Request $request, int $id): Response
    {
        $this->auth()->requirePermission('invoicing.validate');
        $this->quotes->accept($id);
        flash('success', 'Devis accepté.');
        return new RedirectResponse('/invoicing/quotes');
    }

    public function reject(Request $request, int $id): Response
    {
        $this->auth()->requirePermission('invoicing.validate');
        $this->quotes->reject($id);
        flash('warning', 'Devis refusé.');
        return new RedirectResponse('/invoicing/quotes');
    }

    public function convert(Request $request, int $id): Response
    {
        $this->auth()->requirePermission('invoicing.validate');
        $invoiceId = $this->quotes->convertToInvoice($id, (int) $this->auth()->id());
        flash('success', "Facture n°{$invoiceId} créée depuis le devis.");
        return new RedirectResponse("/invoicing/invoices/{$invoiceId}");
    }

    public function show(Request $request, int $id): Response
    {
        $this->auth()->requirePermission('invoicing.read');
        $quote = $this->db->fetchOne(
            'SELECT q.*, tp.name AS third_party_name, tp.address, tp.postal_code, tp.city
             FROM quotes q JOIN third_parties tp ON tp.id = q.third_party_id WHERE q.id = :id',
            ['id' => $id]
        );
        if ($quote === null) {
            throw new NotFoundException('Devis introuvable.');
        }
        $quote['lines'] = $this->db->fetchAll(
            'SELECT * FROM quote_lines WHERE quote_id = :id ORDER BY sort_order, id',
            ['id' => $id]
        );
        return $this->view('Invoicing::quotes/show', ['title' => 'Devis ' . $quote['quote_number'], 'quote' => $quote]);
    }
}
