<?php

declare(strict_types=1);

namespace Modules\HR\Controllers;

use App\Core\Database\Connection;
use App\Core\Http\Controller;
use App\Core\Http\RedirectResponse;
use App\Core\Http\Request;
use App\Core\Http\Response;
use Modules\HR\Services\LeaveService;

/**
 * Gestion RH des demandes de congés (vue gestionnaire).
 */
final class LeaveController extends Controller
{
    public function __construct(
        private readonly LeaveService $leaveService,
        private readonly Connection $db
    ) {
    }

    public function index(Request $request): Response
    {
        $this->auth()->requireAnyPermission(['hr.leaves.read', 'hr.leaves.approve']);
        $status = (string) $request->query('status', '');
        $sql = "SELECT lr.*, e.first_name, e.last_name, e.employee_number, lt.label AS type_label
                FROM leave_requests lr
                JOIN employees e ON e.id = lr.employee_id
                JOIN leave_types lt ON lt.id = lr.leave_type_id";
        $params = [];
        if ($status !== '') {
            $sql .= ' WHERE lr.status = :status';
            $params['status'] = $status;
        }
        $sql .= ' ORDER BY lr.created_at DESC LIMIT 200';
        return $this->view('HR::leaves/index', [
            'title' => 'Congés',
            'requests' => $this->db->fetchAll($sql, $params),
            'pending' => $this->leaveService->pendingRequests(),
            'status' => $status,
        ]);
    }

    public function approve(Request $request, int $id): Response
    {
        $this->auth()->requirePermission('hr.leaves.approve');
        $this->leaveService->review($id, true, (int) $this->auth()->id(), (string) $request->input('review_note', ''));
        flash('success', 'Demande approuvée, solde mis à jour.');
        return new RedirectResponse('/hr/leaves');
    }

    public function reject(Request $request, int $id): Response
    {
        $this->auth()->requirePermission('hr.leaves.approve');
        $this->leaveService->review($id, false, (int) $this->auth()->id(), (string) $request->input('review_note', ''));
        flash('warning', 'Demande rejetée.');
        return new RedirectResponse('/hr/leaves');
    }
}
