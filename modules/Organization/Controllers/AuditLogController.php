<?php

declare(strict_types=1);

namespace Modules\Organization\Controllers;

use App\Core\Database\Connection;
use App\Core\Http\Controller;
use App\Core\Http\Request;
use App\Core\Http\Response;

/**
 * Consultation du journal d'audit (lecture seule, permission dédiée).
 */
final class AuditLogController extends Controller
{
    public function __construct(private readonly Connection $db)
    {
    }

    public function index(Request $request): Response
    {
        $this->auth()->requirePermission('audit.read');
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 50;
        $offset = ($page - 1) * $perPage;
        $filters = [];
        $params = [];
        if ($request->query('entity_type')) {
            $filters[] = 'a.entity_type ILIKE :entity';
            $params['entity'] = '%' . $request->query('entity_type') . '%';
        }
        if ($request->query('action')) {
            $filters[] = 'a.action ILIKE :action';
            $params['action'] = '%' . $request->query('action') . '%';
        }
        $where = $filters === [] ? '' : 'WHERE ' . implode(' AND ', $filters);
        $logs = $this->db->fetchAll(
            "SELECT a.*, u.email
             FROM audit_logs a
             LEFT JOIN users u ON u.id = a.user_id
             {$where}
             ORDER BY a.created_at DESC, a.id DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );
        $total = (int) $this->db->fetchScalar(
            "SELECT COUNT(*) FROM audit_logs a {$where}",
            $params
        );
        return $this->view('Organization::audit', [
            'title' => 'Journal d\'audit',
            'logs' => $logs,
            'total' => $total,
            'page' => $page,
            'totalPages' => max(1, (int) ceil($total / $perPage)),
        ]);
    }
}
