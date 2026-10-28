<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Exception\NotFoundException;
use App\Core\Http\Controller;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Pdf\PdfManager;
use Modules\Documents\Services\DocumentAccessService;

/**
 * Visualisation (inline) et téléchargement des PDF générés par l'application
 * et historisés dans la table `documents`. Le droit d'accès est déduit de
 * l'entité métier liée (facture, contrat, bulletin…), avec repli sur la
 * propriété de la fiche salarié pour le portail des employés.
 */
final class GeneratedDocumentController extends Controller
{
    public function __construct(
        private readonly PdfManager $pdf,
        private readonly DocumentAccessService $documentAccess
    ) {
    }

    public function preview(Request $request, int $id): Response
    {
        return $this->serve($id, true);
    }

    public function download(Request $request, int $id): Response
    {
        return $this->serve($id, false);
    }

    private function serve(int $id, bool $inline): Response
    {
        $db = db();
        $document = $db->fetchOne('SELECT * FROM documents WHERE id = :id', ['id' => $id]);
        if ($document === null) {
            throw new NotFoundException('Document introuvable.');
        }

        $permission = $this->pdf->permissionForEntity((string) $document['entity_type']);
        $allowed = $permission === null
            ? $this->auth()->hasPermission('documents.read')
            : $this->auth()->hasPermission($permission);
        if (!$allowed) {
            // Repli portail salarié : accès aux seuls PDF de sa propre fiche.
            $this->documentAccess->assertCanAccess($document);
        }

        // Résolution sécurisée : disque "exports" uniquement, sans traversée.
        $root = realpath(storage_path('exports'));
        $full = realpath($root . DIRECTORY_SEPARATOR . (string) $document['path']);
        if ($root === false || $full === false || !str_starts_with($full, $root) || !is_file($full)) {
            throw new NotFoundException('Fichier PDF introuvable sur le disque.');
        }

        $disposition = ($inline ? 'inline' : 'attachment')
            . '; filename="' . preg_replace('/[^\w.\- ]+/u', '_', (string) ($document['original_name'] ?: 'document.pdf')) . '"';

        return new Response((string) file_get_contents($full), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Length' => (string) filesize($full),
            'Content-Disposition' => $disposition,
            'Cache-Control' => 'private, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
