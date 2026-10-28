<?php

declare(strict_types=1);

namespace Modules\HR\Controllers;

use App\Core\Database\Connection;
use App\Core\Exception\NotFoundException;
use App\Core\Http\Controller;
use App\Core\Http\RedirectResponse;
use App\Core\Http\Request;
use App\Core\Http\Response;
use Modules\Employees\Repositories\EmployeeRepository;
use Modules\HR\Services\LeaveService;

/**
 * Portail salarié : self-service (congés, soldes, bulletins, documents).
 */
final class PortalController extends Controller
{
    public function __construct(
        private readonly Connection $db,
        private readonly LeaveService $leaveService,
        private readonly EmployeeRepository $employees
    ) {
    }

    /**
     * Fiche salarié rattachée au compte utilisateur courant (lien direct
     * via employees.user_id, repli historique par e-mail).
     */
    private function currentEmployee(): array
    {
        $user = $this->auth()->user();
        $row = $this->employees->findForUser((int) $user?->id, (string) ($user?->email ?? ''));
        if ($row === null) {
            throw new NotFoundException(
                'Aucune fiche salarié rattachée à votre compte. Contactez votre responsable RH.'
            );
        }
        return $row;
    }

    public function index(Request $request): Response
    {
        $this->auth()->requirePermission('portal.self');
        try {
            $employee = $this->currentEmployee();
        } catch (NotFoundException $exception) {
            // Cas normal pour l'administrateur initial : pas de fiche salarié.
            flash('warning', $exception->getMessage()
                . ' (Renseignez l\'e-mail professionnel d\'une fiche salarié identique à l\'e-mail du compte.)');
            return new RedirectResponse('/dashboard');
        }
        $year = (int) date('Y');
        return $this->view('HR::portal/index', [
            'title' => 'Mon portail salarié',
            'employee' => $employee,
            'balances' => $this->leaveService->employeeBalances((int) $employee['id'], $year),
            'requests' => $this->leaveService->employeeRequests((int) $employee['id']),
            'leaveTypes' => $this->db->fetchAll('SELECT * FROM leave_types ORDER BY label'),
            'payslips' => $this->db->fetchAll(
                'SELECT p.*, per.year, per.month FROM payslips p
                 JOIN payroll_periods per ON per.id = p.period_id
                 WHERE p.employee_id = :e ORDER BY per.year DESC, per.month DESC LIMIT 12',
                ['e' => $employee['id']]
            ),
            'documents' => $this->db->fetchAll(
                'SELECT * FROM documents
                 WHERE employee_id = :e
                 ORDER BY created_at DESC, id DESC LIMIT 25',
                ['e' => $employee['id']]
            ),
        ]);
    }

    public function requestLeave(Request $request): Response
    {
        $this->auth()->requirePermission('hr.leaves.request');
        $employee = $this->currentEmployee();
        $data = $this->validator()->validate($request->all(), [
            'leave_type_id' => 'required|integer',
            'starts_on' => 'required|date',
            'ends_on' => 'required|date',
            'reason' => 'nullable|string',
        ]);
        $this->leaveService->submit((int) $employee['id'], $data, (int) $this->auth()->id());
        flash('success', 'Votre demande de congé a été transmise pour validation.');
        return new RedirectResponse('/portal');
    }

    public function cancelLeave(Request $request, int $id): Response
    {
        $employee = $this->currentEmployee();
        $owned = $this->db->fetchOne(
            'SELECT id FROM leave_requests WHERE id = :id AND employee_id = :e',
            ['id' => $id, 'e' => $employee['id']]
        );
        if ($owned === null) {
            throw new NotFoundException('Demande introuvable.');
        }
        $this->leaveService->cancel($id);
        flash('info', 'Demande annulée.');
        return new RedirectResponse('/portal');
    }
}
