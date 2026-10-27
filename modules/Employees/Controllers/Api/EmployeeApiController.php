<?php

declare(strict_types=1);

namespace Modules\Employees\Controllers\Api;

use App\Core\Http\Controller;
use App\Core\Http\JsonResponse;
use App\Core\Http\Request;
use Modules\Employees\Models\Employee;
use Modules\Employees\Repositories\EmployeeRepository;
use Modules\Organization\Services\OrganizationService;

/**
 * API REST des salariés (/api/v1/employees).
 */
final class EmployeeApiController extends Controller
{
    public function __construct(
        private readonly EmployeeRepository $repository,
        private readonly OrganizationService $organization
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->auth()->requirePermission('employees.read');
        $company = $this->organization->defaultCompany();
        $rows = $company === null ? [] : $this->repository->search(
            (int) $company['id'],
            (string) $request->query('q', '')
        );
        $data = array_map(static fn (array $row) => Employee::fromRow($row)->toArray(), $rows);
        return json_response(['success' => true, 'data' => $data, 'meta' => ['count' => count($data)]]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $this->auth()->requirePermission('employees.read');
        $row = $this->repository->findDetailed($id);
        if ($row === null) {
            return json_response(['success' => false, 'error' => 'Salarié introuvable.'], 404);
        }
        return json_response(['success' => true, 'data' => Employee::fromRow($row)->toArray()]);
    }
}
