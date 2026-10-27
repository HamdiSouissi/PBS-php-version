<?php

declare(strict_types=1);

namespace App\Core\Pdf;

/**
 * Marqueur des documents devant tenir sur une seule page (ex : bulletin de
 * paie). Le PdfManager contrôle le nombre de pages après rendu et, en cas de
 * débordement, relance la génération avec le compactage maximal du moteur.
 */
interface OnePageDocument
{
}
