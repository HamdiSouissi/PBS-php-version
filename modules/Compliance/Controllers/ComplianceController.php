<?php

declare(strict_types=1);

namespace Modules\Compliance\Controllers;

use App\Core\Database\Connection;
use App\Core\Http\Controller;
use App\Core\Http\RedirectResponse;
use App\Core\Http\Request;
use App\Core\Http\Response;
use Modules\Compliance\Services\BackupService;

/**
 * Conformité : obligations légales, échéances et sauvegardes locales.
 */
final class ComplianceController extends Controller
{
    public function __construct(
        private readonly Connection $db,
        private readonly BackupService $backups
    ) {
    }

    public function index(Request $request): Response
    {
        $this->auth()->requirePermission('compliance.read');
        try {
            $backupRows = array_map(
                static fn (array $b): array => [
                    'file' => $b['file'],
                    'size' => number_format(((float) $b['size']) / 1024, 1, ',', ' ') . ' Ko',
                    'created_at' => $b['created_at'],
                ],
                $this->backups->listBackups()
            );
        } catch (\Throwable) {
            $backupRows = [];
        }
        return $this->view('Compliance::index', [
            'title' => 'Conformité',
            'obligations' => $this->db->fetchAll(
                'SELECT * FROM compliance_obligations ORDER BY (due_on IS NULL), due_on, title'
            ),
            'backups' => $backupRows,
            'authorities' => ['URSSAF', 'DGFIP', 'INSEE', 'greffe', 'medecine_travail', 'interne'],
            'frequencies' => ['monthly' => 'Mensuel', 'quarterly' => 'Trimestriel', 'yearly' => 'Annuel', 'event' => 'Événement', 'continuous' => 'Continu'],
        ]);
    }

    public function storeObligation(Request $request): Response
    {
        $this->auth()->requirePermission('compliance.write');
        $data = $this->validator()->validate($request->all(), [
            'title' => 'required|string|max:180',
            'authority' => 'required|in:URSSAF,DGFIP,INSEE,greffe,medecine_travail,interne',
            'frequency' => 'required|in:monthly,quarterly,yearly,event,continuous',
            'due_on' => 'nullable|date',
            'notes' => 'nullable|string|max:1000',
        ]);
        $code = 'OBL-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        $this->db->execute(
            'INSERT INTO compliance_obligations (code, title, authority, frequency, due_on, status, notes)
             VALUES (:code, :title, :authority, :frequency, :due, \'pending\', :notes)',
            [
                'code' => $code,
                'title' => $data['title'],
                'authority' => $data['authority'],
                'frequency' => $data['frequency'],
                'due' => $data['due_on'] ?: null,
                'notes' => $data['notes'] ?? '',
            ]
        );
        audit('compliance.obligation.create', 'ComplianceObligation', (int) $this->db->lastInsertId());
        flash('success', 'Obligation enregistrée.');
        return new RedirectResponse('/compliance');
    }

    public function complete(Request $request, int $id): Response
    {
        $this->auth()->requirePermission('compliance.write');
        $this->db->execute(
            "UPDATE compliance_obligations SET status = 'done', completed_at = :at, updated_at = " . $this->db->nowExpression() . ' WHERE id = :id',
            ['at' => date('Y-m-d H:i:s'), 'id' => $id]
        );
        audit('compliance.obligation.complete', 'ComplianceObligation', $id);
        flash('success', 'Obligation marquée comme traitée.');
        return new RedirectResponse('/compliance');
    }
}
