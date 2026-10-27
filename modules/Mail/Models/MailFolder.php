<?php

declare(strict_types=1);

namespace Modules\Mail\Models;

/**
 * Dossier IMAP présenté à l'utilisateur, avec libellé francisé et compteurs.
 */
final class MailFolder
{
    public function __construct(
        public readonly string $name,
        public readonly string $label,
        public readonly int $total,
        public readonly int $unseen
    ) {
    }

    /**
     * Libellé humain des dossiers standards ; le nom brut sert de repli.
     */
    public static function labelFor(string $name): string
    {
        $normalized = mb_strtolower($name);
        return match (true) {
            $normalized === 'inbox' => 'Boîte de réception',
            str_contains($normalized, 'envoy'), $normalized === 'sent', str_contains($normalized, 'sent') => 'Éléments envoyés',
            str_contains($normalized, 'brouillon'), str_contains($normalized, 'draft') => 'Brouillons',
            str_contains($normalized, 'indésirable'), str_contains($normalized, 'spam'), str_contains($normalized, 'junk') => 'Courrier indésirable',
            str_contains($normalized, 'corbeille'), str_contains($normalized, 'trash') => 'Corbeille',
            str_contains($normalized, 'archive') => 'Archives',
            default => $name,
        };
    }
}
