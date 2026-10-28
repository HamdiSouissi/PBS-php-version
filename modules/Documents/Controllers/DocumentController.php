<?php

declare(strict_types=1);

namespace Modules\Documents\Controllers;

use App\Core\Database\Connection;
use App\Core\Exception\AppException;
use App\Core\Exception\NotFoundException;
use App\Core\Exception\ValidationException;
use App\Core\Http\Controller;
use App\Core\Http\RedirectResponse;
use App\Core\Http\Request;
use App\Core\Http\Response;
use Modules\Documents\Services\DocumentAccessService;

/**
 * Centre documentaire : liste, dépôt, téléchargement sécurisé et suppression.
 */
final class DocumentController extends Controller
{
    /** Disques autorisés (dossiers sous storage/). */
    private const DISKS = ['documents', 'uploads', 'exports'];

    public function __construct(
        private readonly Connection $db,
        private readonly DocumentAccessService $access
    ) {
    }

    public function index(Request $request): Response
    {
        $this->auth()->requirePermission('documents.read');
        $scoped = !$this->access->isManager();
        return $this->view('Documents::index', [
            'title' => $scoped ? 'Mes documents' : 'Documents',
            'documents' => $this->access->visibleDocuments(),
            'scoped' => $scoped,
            'canWrite' => $this->auth()->hasPermission('documents.write'),
            'maxSizeMb' => (int) ((int) config('security.upload.max_size') / 1048576),
            'extensions' => (array) config('security.upload.allowed_extensions'),
        ]);
    }

    public function upload(Request $request): Response
    {
        $this->auth()->requirePermission('documents.write');
        try {
            $file = $request->file('file');
            if ($file === null) {
                throw new ValidationException(['file' => 'Aucun fichier sélectionné.']);
            }
            $config = (array) config('security.upload');
            if ($file->size() > (int) $config['max_size']) {
                throw new ValidationException(['file' => 'Fichier trop volumineux.']);
            }
            if (!in_array($file->extension(), $config['allowed_extensions'], true)) {
                throw new ValidationException(['file' => 'Extension non autorisée : .' . $file->extension()]);
            }

            $relativeDir = date('Y/m');
            $absolute = $file->moveTo(storage_path('documents/' . $relativeDir));

            $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($absolute) ?: 'application/octet-stream';
            $isOfficeZip = $mime === 'application/zip' && in_array($file->extension(), ['docx', 'xlsx'], true);
            if (!$isOfficeZip && !in_array($mime, $config['allowed_mimes'], true)) {
                @unlink($absolute);
                throw new ValidationException(['file' => 'Type de fichier non autorisé (' . $mime . ').']);
            }

            $displayName = trim((string) $request->input('name', ''));
            $this->db->execute(
                'INSERT INTO documents (name, original_name, disk, path, mime, size, category, visibility, checksum, uploaded_by)
                 VALUES (:name, :original, :disk, :path, :mime, :size, :category, :visibility, :checksum, :user)',
                [
                    'name' => $displayName !== '' ? mb_substr($displayName, 0, 255) : $file->originalName(),
                    'original' => mb_substr($file->originalName(), 0, 255),
                    'disk' => 'documents',
                    'path' => $relativeDir . '/' . basename($absolute),
                    'mime' => $mime,
                    'size' => (int) filesize($absolute),
                    'category' => mb_substr((string) $request->input('category', 'autre'), 0, 60) ?: 'autre',
                    'visibility' => 'private',
                    'checksum' => (string) hash_file('sha256', $absolute),
                    'user' => $this->auth()->id(),
                ]
            );
            audit('document.upload', 'Document', (int) $this->db->lastInsertId(), ['name' => $file->originalName()]);
            flash('success', 'Document déposé.');
        } catch (ValidationException $exception) {
            flash('danger', implode(' ', $exception->errors()));
        }
        return new RedirectResponse('/documents');
    }

    public function download(Request $request, int $id): Response
    {
        $this->auth()->requirePermission('documents.read');
        $document = $this->findOrFail($id);
        // Un salarié ne peut télécharger que ses propres documents.
        $this->access->assertCanAccess($document);
        $full = $this->resolvePath($document);
        $name = preg_replace('/[^\w.\- ]+/u', '_', (string) ($document['original_name'] ?: $document['name'])) ?: 'document';
        audit('document.download', 'Document', $id);
        return new Response((string) file_get_contents($full), 200, [
            'Content-Type' => (string) ($document['mime'] ?: 'application/octet-stream'),
            'Content-Length' => (string) filesize($full),
            'Content-Disposition' => 'attachment; filename="' . $name . '"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function delete(Request $request, int $id): Response
    {
        $this->auth()->requirePermission('documents.write');
        $document = $this->findOrFail($id);
        try {
            @unlink($this->resolvePath($document));
        } catch (NotFoundException) {
            // Fichier déjà absent : on supprime quand même l'entrée.
        }
        $this->db->execute('DELETE FROM documents WHERE id = :id', ['id' => $id]);
        audit('document.delete', 'Document', $id, ['name' => $document['name']]);
        flash('warning', 'Document supprimé.');
        return new RedirectResponse('/documents');
    }

    /** @return array<string,mixed> */
    private function findOrFail(int $id): array
    {
        $document = $this->db->fetchOne('SELECT * FROM documents WHERE id = :id', ['id' => $id]);
        if ($document === null) {
            throw new NotFoundException('Document introuvable.');
        }
        return $document;
    }

    /**
     * Chemin absolu validé : reste obligatoirement dans le disque déclaré
     * (protection contre la traversée de répertoires).
     *
     * @param array<string,mixed> $document
     */
    private function resolvePath(array $document): string
    {
        $disk = (string) $document['disk'];
        if (!in_array($disk, self::DISKS, true)) {
            throw new AppException('Disque de stockage non autorisé.');
        }
        $root = realpath(storage_path($disk));
        $full = $root === false ? false : realpath($root . DIRECTORY_SEPARATOR . (string) $document['path']);
        if ($root === false || $full === false || !str_starts_with($full, $root) || !is_file($full)) {
            throw new NotFoundException('Fichier introuvable sur le disque.');
        }
        return $full;
    }
}
