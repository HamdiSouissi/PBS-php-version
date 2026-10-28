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
 * Les gestionnaires documentaires (permission documents.write : RH, paie,
 * comptable, administrateur) voient tous les documents. Un salarié ne voit
 * et ne télécharge que les documents rattachés à sa propre fiche
 * (documents.employee_id), notamment ses bulletins et contrats générés.
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
        if ($this->isManager($user)) {
            return $this->db->fetchAll(
                'SELECT d.*, u.email AS uploader
                 FROM documents d
                 LEFT JOIN users u ON u.id = d.uploaded_by
                 ORDER BY d.created_at DESC, d.id DESC
                 LIMIT 200'
            );
        }

        $employeeId = $this->employeeIdFor($user);
        if ($employeeId === null) {
            return [];
        }
        return $this->db->fetchAll(
            'SELECT d.*, u.email AS uploader
             FROM documents d
             LEFT JOIN users u ON u.id = d.uploaded_by
             WHERE d.employee_id = :employee
             ORDER BY d.created_at DESC, d.id DESC
             LIMIT 200',
            ['employee' => $employeeId]
        );
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
        if ($this->isManager($user)) {
            return;
        }
        $employeeId = $this->employeeIdFor($user);
        if ($employeeId !== null && (int) ($document['employee_id'] ?? 0) === $employeeId) {
            return;
        }
        throw new NotFoundException('Document introuvable.');
    }
}
