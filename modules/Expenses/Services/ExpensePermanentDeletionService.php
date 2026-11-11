<?php

declare(strict_types=1);

namespace Modules\Expenses\Services;

use App\Core\Database\Connection;
use App\Core\Exception\AppException;
use App\Core\Exception\NotFoundException;
use App\Core\Logger\Logger;

/** Effacement définitif d'un brouillon déjà archivé et de ses fichiers privés. */
final class ExpensePermanentDeletionService
{
    public function __construct(private readonly Connection $db, private readonly Logger $logger)
    {
    }

    /** @return int nombre de fichiers dont la suppression finale a échoué */
    public function deleteArchived(int $id): int
    {
        $staged = [];
        try {
            $this->db->transaction(function (Connection $db) use ($id, &$staged): void {
                $report = $db->fetchOne('SELECT id, status, deleted_at FROM expense_reports WHERE id = :id' . ($db->isSqlite() ? '' : ' FOR UPDATE'), ['id' => $id]);
                if ($report === null) {
                    throw new NotFoundException('Note de frais introuvable.');
                }
                if ($report['deleted_at'] === null || $report['status'] !== 'draft') {
                    throw new AppException('Seuls les brouillons déjà archivés peuvent être supprimés définitivement.');
                }
                if ($db->fetchOne("SELECT id FROM expense_lines WHERE report_id = :id AND receipt_path <> ''", ['id' => $id]) !== null) {
                    throw new AppException('Un justificatif historique utilise un chemin non géré. Vérifiez et traitez ce fichier avant la suppression définitive.');
                }
                $paths = [];
                foreach ($db->fetchAll('SELECT storage_name FROM expense_receipts WHERE report_id = :id', ['id' => $id]) as $receipt) {
                    $name = (string) $receipt['storage_name'];
                    if (!preg_match('/^[a-f0-9]{32}\\.pdf$/D', $name)) {
                        throw new AppException('Chemin de justificatif invalide : suppression annulée.');
                    }
                    $this->assertNotShared('uploads', 'expense-receipts/' . $name, []);
                    $path = $this->safePath('uploads/expense-receipts', $name);
                    if ($path !== null) {
                        $paths[$path] = $path;
                    }
                }
                $documents = $db->fetchAll("SELECT id, disk, path, category, mime FROM documents WHERE entity_type = 'expense_report' AND entity_id = :id", ['id' => $id]);
                $documentIds = array_map(static fn (array $row): int => (int) $row['id'], $documents);
                foreach ($documents as $document) {
                    if ($document['disk'] !== 'exports' || $document['category'] !== 'note_frais' || $document['mime'] !== 'application/pdf') {
                        throw new AppException('Un document lié utilise un stockage non prévu : suppression annulée.');
                    }
                    if ($db->fetchOne('SELECT id FROM contract_signatures WHERE document_id = :id', ['id' => $document['id']]) !== null) {
                        throw new AppException('Un document lié est utilisé par une signature : suppression annulée.');
                    }
                    $versions = $db->fetchAll('SELECT path FROM document_versions WHERE document_id = :id', ['id' => $document['id']]);
                    foreach (array_merge([(string) $document['path']], array_column($versions, 'path')) as $relative) {
                        $relative = (string) $relative;
                        if (!str_starts_with($relative, 'note_frais/') || !str_ends_with(strtolower($relative), '.pdf')) {
                            throw new AppException('Chemin de PDF inattendu : suppression annulée.');
                        }
                        $this->assertNotShared('exports', $relative, $documentIds);
                        $path = $this->safePath('exports', $relative);
                        if ($path !== null) {
                            $paths[$path] = $path;
                        }
                    }
                }
                // Renommage sur le même disque : les fichiers peuvent être restaurés si la transaction échoue.
                foreach ($paths as $path) {
                    $temporary = $path . '.pending-delete-' . bin2hex(random_bytes(12));
                    if (!@rename($path, $temporary)) {
                        throw new AppException('Impossible de préparer la suppression des fichiers. Vérifiez les droits du stockage.');
                    }
                    $staged[$path] = $temporary;
                }
                foreach ($documentIds as $documentId) {
                    $db->execute('DELETE FROM document_versions WHERE document_id = :id', ['id' => $documentId]);
                    $db->execute('DELETE FROM documents WHERE id = :id', ['id' => $documentId]);
                }
                $db->execute('DELETE FROM expense_receipts WHERE report_id = :id', ['id' => $id]);
                $db->execute('DELETE FROM expense_lines WHERE report_id = :id', ['id' => $id]);
                $db->execute('DELETE FROM expense_reports WHERE id = :id', ['id' => $id]);
                audit('expense.hard_delete', 'ExpenseReport', $id, ['documents' => count($documentIds), 'files' => count($paths)]);
            });
        } catch (\Throwable $exception) {
            foreach ($staged as $original => $temporary) {
                if (!@rename($temporary, $original)) {
                    $this->logger->critical('Restauration de fichier après échec de suppression impossible', ['report_id' => $id, 'temporary_path' => $temporary, 'original_path' => $original]);
                }
            }
            throw $exception;
        }
        $failed = 0;
        foreach ($staged as $temporary) {
            if (!@unlink($temporary)) {
                $failed++;
                $this->logger->error('Fichier de note à supprimer manuellement après suppression définitive', ['report_id' => $id, 'temporary_path' => $temporary]);
            }
        }
        return $failed;
    }

    /** Refuse de supprimer un fichier également référencé par un autre document. @param list<int> $documentIds */
    private function assertNotShared(string $disk, string $path, array $documentIds): void
    {
        $references = $this->db->fetchAll('SELECT id FROM documents WHERE disk = :disk AND path = :path', ['disk' => $disk, 'path' => $path]);
        $versions = $this->db->fetchAll('SELECT v.document_id AS id FROM document_versions v JOIN documents d ON d.id = v.document_id WHERE d.disk = :disk AND v.path = :path', ['disk' => $disk, 'path' => $path]);
        foreach (array_merge($references, $versions) as $reference) {
            if (!in_array((int) $reference['id'], $documentIds, true)) {
                throw new AppException('Un PDF est partagé avec un autre document : suppression annulée.');
            }
        }
    }

    /** Résout exclusivement un chemin relatif interne, sans suivre les liens symboliques de fichier. */
    private function safePath(string $disk, string $relative): ?string
    {
        $parts = explode('/', $relative);
        if ($relative === '' || str_contains($relative, "\\") || str_contains($relative, "\0")
            || in_array('', $parts, true) || in_array('.', $parts, true) || in_array('..', $parts, true)) {
            throw new AppException('Chemin de fichier invalide : suppression annulée.');
        }
        $base = storage_path($disk);
        $candidate = $base . DIRECTORY_SEPARATOR . $relative;
        if (is_link($candidate)) {
            throw new AppException('Lien symbolique détecté : suppression annulée.');
        }
        if (!file_exists($candidate)) {
            return null;
        }
        $root = realpath($base);
        $path = realpath($candidate);
        if ($root === false || $path === false || !str_starts_with($path, rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR) || !is_file($path)) {
            throw new AppException('Chemin de fichier non autorisé : suppression annulée.');
        }
        return $path;
    }
}
