<?php

declare(strict_types=1);

namespace Modules\Organization\Controllers;

use App\Core\Http\Controller;
use App\Core\Http\RedirectResponse;
use App\Core\Http\Request;
use App\Core\Http\Response;
use Modules\Organization\Services\OrganizationService;

final class OrganizationController extends Controller
{
    public function __construct(private readonly OrganizationService $service)
    {
    }

    public function index(Request $request): Response
    {
        $this->auth()->requirePermission('organization.read');
        $company = $this->service->defaultCompany();
        return $this->view('Organization::index', [
            'title' => 'Entreprise & organisation',
            'company' => $company,
            'establishments' => $company ? $this->service->establishments((int) $company['id']) : [],
            'departments' => $company ? $this->service->departments((int) $company['id']) : [],
        ]);
    }

    public function updateCompany(Request $request, int $id): Response
    {
        $this->auth()->requirePermission('organization.write');
        $data = $this->validator()->validate($request->all(), [
            'legal_name' => 'required|string|max:180',
            'commercial_name' => 'nullable|string|max:180',
            'siren' => 'nullable|sirene',
            'siret' => 'nullable|siret',
            'vat_number' => 'nullable|string|max:20',
            'legal_form' => 'required|string|max:40',
            'email' => 'nullable|email',
            'phone' => 'nullable|string|max:40',
            'address' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'city' => 'nullable|string|max:120',
            'iban' => 'nullable|iban',
        ]);
        $this->service->updateCompany($id, $data);
        flash('success', 'Informations de la société mises à jour.');
        return new RedirectResponse('/organization');
    }

    public function storeEstablishment(Request $request): Response
    {
        $this->auth()->requirePermission('organization.write');
        $data = $this->validator()->validate($request->all(), [
            'company_id' => 'required|integer',
            'name' => 'required|string|max:180',
            'siret' => 'nullable|siret',
            'city' => 'nullable|string|max:120',
        ]);
        $this->service->addEstablishment((int) $data['company_id'], $data);
        flash('success', 'Établissement ajouté.');
        return new RedirectResponse('/organization');
    }

    public function storeDepartment(Request $request): Response
    {
        $this->auth()->requirePermission('organization.write');
        $data = $this->validator()->validate($request->all(), [
            'company_id' => 'required|integer',
            'code' => 'required|string|max:30',
            'name' => 'required|string|max:120',
        ]);
        $this->service->addDepartment((int) $data['company_id'], $data);
        flash('success', 'Service ajouté.');
        return new RedirectResponse('/organization');
    }
}
