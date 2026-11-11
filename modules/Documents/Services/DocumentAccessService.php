<?php

declare(strict_types=1);

namespace Modules\Documents\Services;

use App\Core\Auth\AuthManager;
use App\Core\Database\Connection;
use App\Core\Exception\NotFoundException;
use Modules\Authentication\Models\User;
use Modules\Employees\Repositories\EmployeeRepository;

/**
 * Règles d'accès au centre documentaire.
 *
 * Les documents métier exigent la permission correspondante ou appartiennent
 * au salarié connecté. Les documents génériques relèvent des gestionnaires.
 */
final class DocumentAccessService
{
    public function __construct(
        private readonly Connection $db,
        private readonly EmployeeRepository $employees,
        private readonly AuthManager $auth
    ) {
    }

    public function isManager(?User $user = null): bool
    {
        $user ??= $this->auth->user();
        return $user !== null && $this->auth->hasPermission('documents.write');
    }

    /**
     * Identifiant de la fiche salarié rattachée au compte.
     */
    public function employeeIdFor(User $user): ?int
    {
        $employee = $this->employees->findForUser($user->id, $user->email);
        return $employee === null ? null : (int) $employee['id'];
    }

    /**
     * Documents visibles par l'utilisateur courant.
     *
     * @return list<array<string,mixed>>
     */
    public function visibleDocuments(): array
    {
        $user = $this->auth->user();
        if ($user === null) {
            return [];
        }
        $visible = [];
        $employeeId = $this->employeeIdFor($user);
        $offset = 0;
        do {
            $documents = $this->db->fetchAll(
                'SELECT d.*, u.email AS uploader FROM documents d
                 LEFT JOIN users u ON u.id = d.uploaded_by ORDER BY d.created_at DESC, d.id DESC LIMIT 200 OFFSET ' . $offset
            );
            foreach ($documents as $document) {
                if ($this->canAccess($document, $user, $employeeId)) {
                    $visible[] = $document;
                    if (count($visible) === 200) {
                        break 2;
                    }
                }
            }
            $offset += count($documents);
        } while (count($documents) === 200);
        return $visible;
    }

    /**
     * Autorise l'accès à un document précis (téléchargement, aperçu PDF).
     * Un 404 (et non un 403) est renvoyé pour ne pas révéler l'existence
     * d'un document appartenant à un autre salarié.
     *
     * @param array<string,mixed> $document
     */
    public function assertCanAccess(array $document): void
    {
        $user = $this->auth->user();
        if ($user === null) {
            throw new NotFoundException('Document introuvable.');
        }
        if (!$this->canAccess($document, $user, $this->employeeIdFor($user))) {
            throw new NotFoundException('Document introuvable.');
        }
    }

    private function canAccess(array $document, User $user, ?int $employeeId): bool
    {
        $type = (string) ($document['entity_type'] ?? '');
        if ($type === 'expense_report') {
            if (!$this->auth->hasPermission('expenses.read')) {
                return false;
            }
            $report = $this->db->fetchOne('SELECT employee_id FROM expense_reports WHERE id = :id AND deleted_at IS NULL', ['id' => $document['entity_id']]);
            return $report !== null && ($this->auth->hasAnyPermission(['expenses.approve', 'expenses.pay'])
                || ($employeeId !== null && (int) $report['employee_id'] === $employeeId));
        }
        if ($employeeId !== null && (int) ($document['employee_id'] ?? 0) === $employeeId) {
            return true;
        }
        $permission = \App\Core\Application::container()->get(\App\Core\Pdf\PdfManager::class)->permissionForEntity($type);
        if ($permission !== null) {
            return $this->auth->hasPermission($permission);
        }
        // Un document sans type mais lié à un salarié est traité comme RH.
        if (!empty($document['employee_id'])) {
            return $this->auth->hasPermission('employees.read');
        }
        return $this->isManager($user);
    }
}
