<?php

declare(strict_types=1);

namespace Modules\Authentication\Controllers;

use App\Core\Http\Controller;
use App\Core\Http\RedirectResponse;
use App\Core\Http\Request;
use App\Core\Http\Response;
use Modules\Authentication\Services\RoleService;

/**
 * Administration des rôles et de la matrice de permissions RBAC.
 */
final class RoleController extends Controller
{
    public function __construct(private readonly RoleService $roles)
    {
    }

    public function index(Request $request): Response
    {
        $this->auth()->requirePermission('users.read');
        return $this->view('Authentication::roles/index', [
            'title' => 'Rôles & permissions',
            'roles' => $this->roles->allRoles(),
            'matrix' => $this->roles->permissionMatrix(),
            'moduleLabels' => $this->moduleLabels(),
            'editing' => $this->resolveEditingRole($request),
        ]);
    }

    public function store(Request $request): Response
    {
        $this->auth()->requirePermission('users.write');
        $data = $this->validator()->validate($request->all(), [
            'code' => 'required|string|max:60',
            'label' => 'required|string|max:120',
            'description' => 'nullable|string|max:500',
        ]);
        $id = $this->roles->create(
            (string) $data['code'],
            (string) $data['label'],
            (string) ($data['description'] ?? ''),
            $this->permissionsFromRequest($request)
        );
        flash('success', 'Rôle créé avec sa matrice de permissions.');
        return new RedirectResponse('/settings/roles?edit=' . $id);
    }

    public function update(Request $request, int $id): Response
    {
        $this->auth()->requirePermission('users.write');
        $data = $this->validator()->validate($request->all(), [
            'label' => 'required|string|max:120',
            'description' => 'nullable|string|max:500',
        ]);
        $this->roles->update(
            $id,
            (string) $data['label'],
            (string) ($data['description'] ?? ''),
            $this->permissionsFromRequest($request)
        );
        flash('success', 'Rôle et permissions mis à jour.');
        return new RedirectResponse('/settings/roles?edit=' . $id);
    }

    public function delete(Request $request, int $id): Response
    {
        $this->auth()->requirePermission('users.write');
        $this->roles->delete($id);
        flash('success', 'Rôle supprimé.');
        return new RedirectResponse('/settings/roles');
    }

    /**
     * @return list<string>
     */
    private function permissionsFromRequest(Request $request): array
    {
        $permissions = (array) $request->input('permissions', []);
        return array_values(array_map('strval', $permissions));
    }

    /**
     * @return array<string,mixed>|null
     */
    private function resolveEditingRole(Request $request): ?array
    {
        $editId = (int) $request->query('edit', 0);
        if ($editId <= 0) {
            return null;
        }
        $role = $this->roles->getOrFail($editId);
        $role['permission_codes'] = $this->roles->permissionCodes($editId);
        return $role;
    }

    /**
     * @return array<string,string>
     */
    private function moduleLabels(): array
    {
        return [
            'organization' => 'Organisation',
            'users' => 'Utilisateurs',
            'employees' => 'Employés',
            'hr' => 'Ressources humaines',
            'payroll' => 'Paie',
            'accounting' => 'Comptabilité',
            'treasury' => 'Trésorerie',
            'invoicing' => 'Facturation',
            'clients' => 'Clients',
            'contracts' => 'Contrats',
            'subscriptions' => 'Abonnements',
            'expenses' => 'Notes de frais',
            'dividends' => 'Dividendes',
            'compliance' => 'Conformité',
            'documents' => 'Documents',
            'ai' => 'Intelligence artificielle',
            'system' => 'Système',
            'portal' => 'Portail salarié',
        ];
    }
}
