<?php

declare(strict_types=1);

namespace Modules\Invoicing\Controllers\Api;

use App\Core\Database\Connection;
use App\Core\Http\Controller;
use App\Core\Http\JsonResponse;
use App\Core\Http\Request;
use Modules\Invoicing\Repositories\InvoiceRepository;
use Modules\Invoicing\Services\InvoiceService;

/**
 * API REST /api/v1/invoices — authentification JWT, réponses JSON.
 */
final class InvoiceApiController extends Controller
{
    public function __construct(
        private readonly InvoiceRepository $repository,
        private readonly InvoiceService $invoices,
        private readonly Connection $db
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->auth()->requirePermission('invoicing.read');
        $page = max(1, (int) $request->query('page', 1));
        $status = (string) $request->query('status', '');
        return json_response(['success' => true, 'data' => $this->repository->paginateInvoices($page, 25, 'sale', $status)]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $this->auth()->requirePermission('invoicing.read');
        $invoice = $this->repository->findDetailed($id);
        if ($invoice === null) {
            return json_response(['success' => false, 'error' => 'Facture introuvable.'], 404);
        }
        return json_response(['success' => true, 'data' => $invoice]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->auth()->requirePermission('invoicing.write');
        $companyId = (int) $this->db->fetchScalar('SELECT id FROM companies WHERE is_default LIMIT 1');
        $invoiceId = $this->invoices->create($companyId, $request->all(), (int) $this->auth()->id());
        return json_response(['success' => true, 'data' => ['id' => $invoiceId]], 201);
    }

    public function issue(Request $request, int $id): JsonResponse
    {
        $this->auth()->requirePermission('invoicing.validate');
        $this->invoices->issue($id, (int) $this->auth()->id());
        return json_response(['success' => true, 'data' => ['id' => $id, 'status' => 'issued']]);
    }

    public function payment(Request $request, int $id): JsonResponse
    {
        $this->auth()->requirePermission('invoicing.payments');
        $paymentId = $this->invoices->registerPayment($id, $request->all(), (int) $this->auth()->id());
        return json_response(['success' => true, 'data' => ['payment_id' => $paymentId]], 201);
    }
}
