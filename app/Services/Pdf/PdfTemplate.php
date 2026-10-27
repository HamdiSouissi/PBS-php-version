<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use App\Core\Database\Connection;
use App\Core\Pdf\PdfDocument;

/**
 * Contrat des générateurs PDF métier : chaque template alimente le
 * PdfDocument à partir des données de l'entité liée et retourne le
 * libellé documentaire utilisé comme nom dans le centre documentaire.
 */
interface PdfTemplate
{
    public function __construct(Connection $db);

    public function render(PdfDocument $pdf, int $entityId): string;
}
