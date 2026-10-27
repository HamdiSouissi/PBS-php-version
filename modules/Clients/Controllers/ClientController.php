<?php

declare(strict_types=1);

namespace Modules\Clients\Controllers;

use App\Core\Database\Connection;
use App\Core\Http\Controller;
use App\Core\Http\RedirectResponse;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Pdf\PdfManager;
use Modules\Clients\Services\ClientService;

/**
 * Espace de gestion des clients : fiches, indicateurs, documents liés
 * et export PDF de la fiche client.
 */
final class ClientController extends Controller
{
    public function __construct(
        private readonly ClientService $clients,
        private readonly Connection $db,
        private readonly PdfManager $pdf
    ) {
    }

    public function index(Request $request): Response
    {
        $this->auth()->requirePermission('clients.read');
        $q = trim((string) $request->query('q', ''));
        return $this->view('Clients::index', [
            'title' => 'Clients',
            'clients' => $this->clients->search($q),
            'q' => $q,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->auth()->requirePermission('clients.write');
        return $this->view('Clients::form', ['title' => 'Nouveau client', 'client' => null]);
    }

    public function store(Request $request): Response
    {
        $this->auth()->requirePermission('clients.write');
        $data = $this->validateClient($request);
        $id = $this->clients->create($data);
        audit('client.create', 'ThirdParty', $id, ['name' => $data['name']]);
        flash('success', 'Client enregistré.');
        return new RedirectResponse('/clients/' . $id);
    }

    public function show(Request $request, int $id): Response
    {
        $this->auth()->requirePermission('clients.read');
        $client = $this->clients->getOrFail($id);
        return $this->view('Clients::show', [
            'title' => $client['name'],
            'client' => $client,
            'stats' => $this->clients->stats($id),
            'invoices' => $this->db->fetchAll(
                "SELECT * FROM invoices WHERE third_party_id = :id AND type = 'sale'
                 ORDER BY issue_date DESC, id DESC LIMIT 15",
                ['id' => $id]
            ),
            'quotes' => $this->db->fetchAll(
                'SELECT * FROM quotes WHERE third_party_id = :id ORDER BY issue_date DESC, id DESC LIMIT 15',
                ['id' => $id]
            ),
            'contracts' => $this->db->fetchAll(
                'SELECT * FROM client_contracts WHERE third_party_id = :id ORDER BY starts_on DESC',
                ['id' => $id]
            ),
            'subscriptions' => $this->db->fetchAll(
                'SELECT s.*, sp.label AS plan_label FROM subscriptions s
                 JOIN subscription_plans sp ON sp.id = s.plan_id
                 WHERE s.third_party_id = :id ORDER BY s.next_invoice_date',
                ['id' => $id]
            ),
            'pdfDoc' => $this->pdf->latestFor('client_sheet', $id),
            'generatedPdfs' => $this->db->fetchAll(
                "SELECT * FROM documents WHERE entity_type IN ('client','client_contract','invoice','quote')
                  AND (entity_id = :id
                       OR entity_id IN (SELECT id FROM client_contracts WHERE third_party_id = :id)
                       OR entity_id IN (SELECT id FROM invoices WHERE third_party_id = :id AND type = 'sale')
                       OR entity_id IN (SELECT id FROM quotes WHERE third_party_id = :id))
                 ORDER BY id DESC LIMIT 25",
                ['id' => $id]
            ),
        ]);
    }

    public function edit(Request $request, int $id): Response
    {
        $this->auth()->requirePermission('clients.write');
        $client = $this->clients->getOrFail($id);
        return $this->view('Clients::form', ['title' => 'Modifier le client', 'client' => $client]);
    }

    public function update(Request $request, int $id): Response
    {
        $this->auth()->requirePermission('clients.write');
        $data = $this->validateClient($request);
        $this->clients->update($id, $data);
        audit('client.update', 'ThirdParty', $id);
        flash('success', 'Client mis à jour.');
        return new RedirectResponse('/clients/' . $id);
    }

    public function generatePdf(Request $request, int $id): Response
    {
        $this->auth()->requirePermission('clients.read');
        $this->clients->getOrFail($id);
        $this->pdf->generate('client_sheet', $id, $this->auth()->id());
        flash('success', 'Fiche client PDF générée.');
        return new RedirectResponse('/clients/' . $id);
    }

    /**
     * @return array<string,mixed>
     */
    private function validateClient(Request $request): array
    {
        return $this->validator()->validate($request->all(), [
            'name' => 'required|string|max:180',
            'legal_form' => 'nullable|string|max:40',
            'siren' => 'nullable|sirene',
            'siret' => 'nullable|siret',
            'vat_number' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'city' => 'nullable|string|max:120',
            'country' => 'nullable|string|max:80',
            'email' => 'nullable|email',
            'phone' => 'nullable|string|max:40',
            'payment_terms_days' => 'nullable|integer',
            'payment_method' => 'nullable|string|max:40',
            'notes' => 'nullable|string|max:1000',
        ]);
    }
}
