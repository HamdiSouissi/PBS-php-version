<?php

declare(strict_types=1);

namespace Modules\Invoicing\Services;

use App\Core\Database\Connection;
use App\Core\Exception\AppException;

/**
 * Numérotation séquentielle des documents commerciaux, par type et par année.
 * Verrou pessimiste (SELECT ... FOR UPDATE) pour garantir l'absence de trou
 * et de doublon en cas de saisie concurrente.
 *
 * CORRECTIF : LAST_VALUE est un mot réservé de MySQL 8 (fonction de fenêtrage).
 * Utilisé sans guillemets inversés, il provoquait une erreur de syntaxe SQL à
 * la création de toute facture, devis ou avoir. Les identifiants sont
 * désormais protégés par des backticks (compatibles MySQL, MariaDB et SQLite).
 */
final class DocumentNumberingService
{
    public const KIND_INVOICE = 'invoice';
    public const KIND_QUOTE = 'quote';
    public const KIND_CREDIT = 'credit_note';

    private const PREFIXES = [
        self::KIND_INVOICE => 'F',
        self::KIND_QUOTE => 'D',
        self::KIND_CREDIT => 'A',
    ];

    public function __construct(private readonly Connection $db)
    {
    }

    public function next(string $kind, ?\DateTimeInterface $date = null): string
    {
        $date ??= new \DateTimeImmutable();
        $period = $date->format('Y');

        $insert = $this->db->isSqlite() ? 'INSERT OR IGNORE' : 'INSERT IGNORE';
        $this->db->execute(
            $insert . ' INTO document_sequences (`kind`, `period`, `last_value`) VALUES (:k, :p, 0)',
            ['k' => $kind, 'p' => $period]
        );

        $row = $this->db->fetchOne(
            'SELECT `last_value` FROM document_sequences WHERE `kind` = :k AND `period` = :p' . $this->db->rowLockClause(),
            ['k' => $kind, 'p' => $period]
        );
        if ($row === null) {
            throw new AppException('Séquence de numérotation introuvable.');
        }

        $next = (int) $row['last_value'] + 1;
        $this->db->execute(
            'UPDATE document_sequences SET `last_value` = :v, updated_at = ' . $this->db->nowExpression() . '
             WHERE `kind` = :k AND `period` = :p',
            ['v' => $next, 'k' => $kind, 'p' => $period]
        );

        return sprintf('%s-%s-%04d', self::PREFIXES[$kind], $period, $next);
    }
}
