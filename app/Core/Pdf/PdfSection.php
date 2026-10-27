<?php

declare(strict_types=1);

namespace App\Core\Pdf;

/**
 * Marqueur de ligne de section à l'intérieur d'un PdfDocument::dataTable().
 * Une section rassemble visuellement un groupe de lignes (ex : cotisations
 * sociales d'un bulletin de paie) sans interrompre la logique du tableau :
 * les en-têtes de colonnes restent répétés automatiquement après chaque saut.
 */
final class PdfSection
{
    public function __construct(public readonly string $label)
    {
    }

    public static function make(string $label): self
    {
        return new self($label);
    }
}
