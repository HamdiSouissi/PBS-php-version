<?php

declare(strict_types=1);

namespace Modules\Mail\Models;

/**
 * Pièce jointe d'un message lu : métadonnées et contenu décodé, prêt pour
 * le téléchargement ou l'affichage inline.
 */
final class MailAttachment
{
    public function __construct(
        public readonly string $filename,
        public readonly string $mimeType,
        public readonly int $size,
        public readonly string $contentId,
        public readonly string $contents
    ) {
    }
}
