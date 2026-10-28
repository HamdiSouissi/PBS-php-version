<?php

declare(strict_types=1);

namespace App\Core\Pdf;

use App\Core\Database\Connection;
use App\Core\Exception\NotFoundException;
use App\Services\Pdf\ClientContractPdf;
use App\Services\Pdf\ClientSheetPdf;
use App\Services\Pdf\EmployeeContractPdf;
use App\Services\Pdf\EmployeeSheetPdf;
use App\Services\Pdf\ExpenseReportPdf;
use App\Services\Pdf\InvoicePdf;
use App\Services\Pdf\PayslipPdf;
use App\Services\Pdf\QuotePdf;

/**
 * Génération, stockage et traçabilité des documents PDF produits
 * par l'application. Chaque génération est historisée dans la table
 * `documents` (disque "exports") et journalisée dans le piste d'audit.
 */
final class PdfManager
{
    /**
     * Catalogue des types de documents : template, catégorie, entité liée,
     * permission requise et libellé du fichier.
     *
     * @var array<string,array{template:class-string,category:string,entity:string,permission:string,label:string}>
     */
    public const CATALOG = [
        'invoice' => [
            'template' => InvoicePdf::class, 'category' => 'facture', 'entity' => 'invoice',
            'permission' => 'invoicing.read', 'label' => 'Facture',
        ],
        'quote' => [
            'template' => QuotePdf::class, 'category' => 'devis', 'entity' => 'quote',
            'permission' => 'invoicing.read', 'label' => 'Devis',
        ],
        'employee_contract' => [
            'template' => EmployeeContractPdf::class, 'category' => 'contrat_travail', 'entity' => 'employment_contract',
            'permission' => 'employees.read', 'label' => 'Contrat de travail',
        ],
        'client_contract' => [
            'template' => ClientContractPdf::class, 'category' => 'contrat_client', 'entity' => 'client_contract',
            'permission' => 'contracts.read', 'label' => 'Contrat client',
        ],
        'employee_sheet' => [
            'template' => EmployeeSheetPdf::class, 'category' => 'fiche_employe', 'entity' => 'employee',
            'permission' => 'employees.read', 'label' => 'Fiche salarié',
        ],
        'client_sheet' => [
            'template' => ClientSheetPdf::class, 'category' => 'fiche_client', 'entity' => 'client',
            'permission' => 'clients.read', 'label' => 'Fiche client',
        ],
        'payslip' => [
            'template' => PayslipPdf::class, 'category' => 'bulletin_paie', 'entity' => 'payslip',
            'permission' => 'payroll.read', 'label' => 'Bulletin de paie',
        ],
        'expense_report' => [
            'template' => ExpenseReportPdf::class, 'category' => 'note_frais', 'entity' => 'expense_report',
            'permission' => 'expenses.read', 'label' => 'Note de frais',
        ],
    ];

    public function __construct(private readonly Connection $db)
    {
    }

    /**
     * Génère (ou régénère) un PDF et l'enregistre dans le centre documentaire.
     *
     * @return array<string,mixed> ligne documents nouvellement créée
     */
    public function generate(string $kind, int $entityId, ?int $userId = null): array
    {
        if (!isset(self::CATALOG[$kind])) {
            throw new NotFoundException("Type de document inconnu : {$kind}.");
        }
        $config = self::CATALOG[$kind];

        $company = $this->db->fetchOne('SELECT * FROM companies ORDER BY is_default DESC, id ASC LIMIT 1')
            ?? ['legal_name' => 'Prime Business Suite'];

        /** @var PdfTemplate $template */
        $template = new $config['template']($this->db);

        $document = new PdfDocument();
        $document->setCompany($company);
        $title = $template->render($document, $entityId);

        // Garantie mono-page : si un document marqué OnePageDocument déborde,
        // une seconde génération est lancée avec le compactage maximal.
        if ($template instanceof OnePageDocument && $document->pageCount() > 1) {
            $document = new PdfDocument();
            $document->setCompany($company)
                ->setDensity(0.75)
                ->setCompactRows(true)
                ->setTightSpacing(true);
            $title = $template->render($document, $entityId);
        }

        $binary = $document->output();

        $year = date('Y');
        $relativeDir = $config['category'] . '/' . $year;
        $absoluteDir = storage_path('exports/' . $relativeDir);
        if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0775, true) && !is_dir($absoluteDir)) {
            throw new \RuntimeException("Impossible de créer le dossier d'export : {$absoluteDir}");
        }

        $baseName = $this->slug($config['category']) . '-' . $entityId . '-' . date('YmdHis');
        $relativePath = $relativeDir . '/' . $baseName . '.pdf';
        $absolutePath = $absoluteDir . '/' . $baseName . '.pdf';
        file_put_contents($absolutePath, $binary);

        // Les PDF relatifs à un salarié (bulletin, contrat, fiche) sont
        // rattachés à sa fiche et visibles depuis son portail ; les autres
        // documents (factures, devis…) restent des documents d'entreprise.
        $employeeId = $this->resolveEmployeeId($kind, $entityId);
        $visibility = $employeeId !== null ? 'portal' : 'company';

        $this->db->execute(
            'INSERT INTO documents (name, original_name, disk, path, mime, size, category, entity_type, entity_id,
                                     employee_id, visibility, checksum, uploaded_by)
             VALUES (:name, :original, :disk, :path, :mime, :size, :category, :entity, :entity_id,
                     :employee_id, :visibility, :checksum, :user)',
            [
                'name' => $title,
                'original' => $baseName . '.pdf',
                'disk' => 'exports',
                'path' => $relativePath,
                'mime' => 'application/pdf',
                'size' => strlen($binary),
                'category' => $config['category'],
                'entity' => $config['entity'],
                'entity_id' => $entityId,
                'employee_id' => $employeeId,
                'visibility' => $visibility,
                'checksum' => hash('sha256', $binary),
                'user' => $userId,
            ]
        );
        $documentId = (int) $this->db->lastInsertId();
        audit('pdf.generate', 'Document', $documentId, [
            'kind' => $kind, 'entity' => $config['entity'], 'entity_id' => $entityId,
        ]);

        return $this->db->fetchOne('SELECT * FROM documents WHERE id = :id', ['id' => $documentId]) ?: [];
    }

    /**
     * Dernier PDF généré pour une entité donnée (null si aucun).
     *
     * @return array<string,mixed>|null
     */
    public function latestFor(string $kind, int $entityId): ?array
    {
        $config = self::CATALOG[$kind] ?? null;
        if ($config === null) {
            return null;
        }
        $row = $this->db->fetchOne(
            'SELECT * FROM documents WHERE entity_type = :type AND entity_id = :id AND category = :category
             ORDER BY id DESC LIMIT 1',
            ['type' => $config['entity'], 'id' => $entityId, 'category' => $config['category']]
        );
        return $row ?: null;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function historyFor(string $kind, int $entityId): array
    {
        $config = self::CATALOG[$kind] ?? null;
        if ($config === null) {
            return [];
        }
        return $this->db->fetchAll(
            'SELECT * FROM documents WHERE entity_type = :type AND entity_id = :id AND category = :category
             ORDER BY id DESC',
            ['type' => $config['entity'], 'id' => $entityId, 'category' => $config['category']]
        );
    }

    public function permissionForEntity(string $entityType): ?string
    {
        foreach (self::CATALOG as $config) {
            if ($config['entity'] === $entityType) {
                return $config['permission'];
            }
        }
        return null;
    }

    /**
     * Fiche salarié propriétaire du document, selon le type de génération.
     * Null pour les documents sans lien salarié (facture, devis, note de frais…).
     */
    private function resolveEmployeeId(string $kind, int $entityId): ?int
    {
        return match ($kind) {
            'payslip' => $this->fetchEmployeeId(
                'SELECT employee_id FROM payslips WHERE id = :id',
                $entityId
            ),
            'employee_contract' => $this->fetchEmployeeId(
                'SELECT employee_id FROM employment_contracts WHERE id = :id',
                $entityId
            ),
            'employee_sheet' => $this->fetchEmployeeId(
                'SELECT id AS employee_id FROM employees WHERE id = :id',
                $entityId
            ),
            default => null,
        };
    }

    private function fetchEmployeeId(string $sql, int $entityId): ?int
    {
        $id = $this->db->fetchScalar($sql, ['id' => $entityId]);
        return $id === false || $id === null ? null : (int) $id;
    }

    private function slug(string $value): string
    {
        $value = trim((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $value), '-');
        return $value !== '' ? strtolower($value) : 'document';
    }
}
