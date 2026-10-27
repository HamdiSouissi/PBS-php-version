<?php

declare(strict_types=1);

namespace Modules\Contracts\Controllers;

use App\Core\Database\Connection;
use App\Core\Http\Controller;
use App\Core\Http\RedirectResponse;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Pdf\PdfManager;
use Modules\Contracts\Services\ClientContractService;

/**
 * Contrats commerciaux clients avec génération/régénération PDF.
 */
final class ClientContractController extends Controller
{
    public function __construct(
        private readonly ClientContractService $contracts,
        private readonly Connection $db,
        private readonly PdfManager $pdf
    ) {
    }

    public function index(Request $request): Response
    {
        $this->auth()->requirePermission('contracts.read');
        return $this->view('Contracts::index', [
            'title' => 'Contrats clients',
            'contracts' => $this->contracts->all(),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->auth()->requirePermission('contracts.write');
        return $this->view('Contracts::form', [
            'title' => 'Nouveau contrat client',
            'clients' => $this->clients(),
        ]);
    }

    public function store(Request $request): Response
    {
        $this->auth()->requirePermission('contracts.write');
        $data = $this->validator()->validate($request->all(), [
            'third_party_id' => 'required|integer',
            'title' => 'required|string|max:180',
            'starts_on' => 'required|date',
            'ends_on' => 'nullable|date',
            'renewal_mode' => 'required|in:none,tacit,express',
            'notice_days' => 'required|integer',
            'amount_ht' => 'required|decimal:2',
            'billing_cycle' => 'required|in:monthly,quarterly,half_yearly,yearly,one_shot',
            'status' => 'required|in:draft,active,terminated,expired',
            'signed_on' => 'nullable|date',
            'notes' => 'nullable|string|max:1000',
        ]);
        $id = $this->contracts->create($data, $this->auth()->id());
        $this->safePdf($id);
        audit('client_contract.create', 'ClientContract', $id);
        flash('success', 'Contrat enregistré et PDF généré.');
        return new RedirectResponse('/contracts/' . $id);
    }

    public function show(Request $request, int $id): Response
    {
        $this->auth()->requirePermission('contracts.read');
        return $this->view('Contracts::show', [
            'title' => 'Contrat ' . $this->contracts->getOrFail($id)['contract_number'],
            'contract' => $this->contracts->getOrFail($id),
            'pdfDoc' => $this->pdf->latestFor('client_contract', $id),
            'history' => $this->pdf->historyFor('client_contract', $id),
        ]);
    }

    public function regeneratePdf(Request $request, int $id): Response
    {
        $this->auth()->requirePermission('contracts.write');
        $this->contracts->getOrFail($id);
        $this->safePdf($id);
        return new RedirectResponse('/contracts/' . $id);
    }

    private function safePdf(int $id): void
    {
        try {
            $this->pdf->generate('client_contract', $id, $this->auth()->id());
            flash('success', 'PDF du contrat généré.');
        } catch (\Throwable $exception) {
            logger()->error('PDF contrat client échoué', ['id' => $id, 'error' => $exception->getMessage()]);
            flash('error', 'Génération PDF indisponible, réessayez.');
        }
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function clients(): array
    {
        return $this->db->fetchAll(
            "SELECT id, code, name FROM third_parties WHERE type IN ('client','both') AND is_active = 1 ORDER BY name"
        );
    }
}
