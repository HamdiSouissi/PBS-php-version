<?php

declare(strict_types=1);

namespace Modules\Accounting\Services;

use App\Core\Database\Connection;
use App\Core\Exception\AppException;

/**
 * Saisie d'écritures comptables en partie double : équilibre débit/crédit
 * strict, contrôle des comptes du plan et de l'exercice, traçabilité.
 */
final class JournalEntryService
{
    public function __construct(private readonly Connection $db)
    {
    }

    /**
     * @param array<string,mixed> $data
     * @return int identifiant de l'écriture
     */
    public function createEntry(array $data, int $userId): int
    {
        $fiscalYear = $this->requireFiscalYear((int) $data['fiscal_year_id']);
        if ($fiscalYear['status'] !== 'open') {
            throw new AppException('L\'exercice comptable est clôturé, aucune écriture n\'est possible.');
        }
        $entryDate = (string) $data['entry_date'];
        if ($entryDate < $fiscalYear['starts_on'] || $entryDate > $fiscalYear['ends_on']) {
            throw new AppException('La date d\'écriture appartient à un autre exercice.');
        }
        $journal = $this->db->fetchOne('SELECT * FROM journals WHERE id = :id', ['id' => (int) $data['journal_id']]);
        if ($journal === null) {
            throw new AppException('Journal comptable introuvable.');
        }

        $lines = $this->normalizeLines((array) ($data['lines'] ?? []));
        $totalDebit = 0.0;
        $totalCredit = 0.0;
        foreach ($lines as $line) {
            $totalDebit += $line['debit'];
            $totalCredit += $line['credit'];
        }
        $totalDebit = round($totalDebit, 2);
        $totalCredit = round($totalCredit, 2);
        if ($totalDebit <= 0.0 || count($lines) < 2) {
            throw new AppException('Une écriture équilibrée comporte au moins deux lignes et un montant non nul.');
        }
        if (abs($totalDebit - $totalCredit) > 0.009) {
            throw new AppException('Écriture déséquilibrée : les totaux débit et crédit doivent être égaux.');
        }

        return (int) $this->db->transaction(function (Connection $db) use ($data, $fiscalYear, $entryDate, $lines, $userId): int {
            $db->execute(
                "INSERT INTO journal_entries
                    (journal_id, fiscal_year_id, entry_date, reference, label, status, source_type, created_by, posted_at)
                 VALUES
                    (:journal, :fy, :date, :ref, :label, 'posted', 'manual', :user, " . $db->nowExpression() . ')',
                [
                    'journal' => (int) $data['journal_id'],
                    'fy' => (int) $fiscalYear['id'],
                    'date' => $entryDate,
                    'ref' => mb_substr(trim((string) ($data['reference'] ?? '')), 0, 60),
                    'label' => trim((string) $data['label']),
                    'user' => $userId,
                ]
            );
            $entryId = (int) $db->lastInsertId();
            foreach ($lines as $line) {
                $db->execute(
                    'INSERT INTO journal_entry_lines (entry_id, account_code, label, debit, credit, third_party)
                     VALUES (:entry, :code, :label, :debit, :credit, :third)',
                    [
                        'entry' => $entryId,
                        'code' => $line['account_code'],
                        'label' => $line['label'],
                        'debit' => $line['debit'],
                        'credit' => $line['credit'],
                        'third' => $line['third_party'],
                    ]
                );
            }
            return $entryId;
        });
    }

    /**
     * Annule une écriture (uniquement sur exercice ouvert). Les lignes sont
     * conservées pour l'audit mais sortent de la balance via le statut.
     */
    public function cancel(int $entryId): void
    {
        $entry = $this->getOrFail($entryId);
        if ($entry['status'] !== 'posted') {
            throw new AppException('Seule une écriture comptabilisée peut être annulée.');
        }
        $fiscalYear = $this->requireFiscalYear((int) $entry['fiscal_year_id']);
        if ($fiscalYear['status'] !== 'open') {
            throw new AppException('L\'exercice est clôturé, l\'écriture ne peut plus être annulée.');
        }
        $this->db->execute(
            "UPDATE journal_entries SET status = 'cancelled' WHERE id = :id",
            ['id' => $entryId]
        );
        audit('accounting.entry.cancel', 'JournalEntry', $entryId);
    }

    /**
     * @return array<string,mixed>
     */
    public function getOrFail(int $entryId): array
    {
        $entry = $this->db->fetchOne(
            'SELECT e.*, j.code AS journal_code, j.label AS journal_label, fy.label AS fiscal_year_label
             FROM journal_entries e
             JOIN journals j ON j.id = e.journal_id
             JOIN fiscal_years fy ON fy.id = e.fiscal_year_id
             WHERE e.id = :id',
            ['id' => $entryId]
        );
        if ($entry === null) {
            throw new AppException('Écriture comptable introuvable.');
        }
        return $entry;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function lines(int $entryId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM journal_entry_lines WHERE entry_id = :id ORDER BY id',
            ['id' => $entryId]
        );
    }

    /**
     * @param array<string,mixed> $rawLines
     * @return list<array{account_code:string,label:string,debit:float,credit:float,third_party:string}>
     */
    private function normalizeLines(array $rawLines): array
    {
        $codes = (array) ($rawLines['account_code'] ?? []);
        $labels = (array) ($rawLines['label'] ?? []);
        $debits = (array) ($rawLines['debit'] ?? []);
        $credits = (array) ($rawLines['credit'] ?? []);
        $thirdParties = (array) ($rawLines['third_party'] ?? []);

        $lines = [];
        foreach ($codes as $i => $code) {
            $code = trim((string) $code);
            if ($code === '') {
                continue;
            }
            $account = $this->db->fetchOne(
                'SELECT code, label, allow_entries FROM chart_accounts WHERE code = :code',
                ['code' => $code]
            );
            if ($account === null) {
                throw new AppException("Le compte {$code} n'existe pas dans le plan comptable.");
            }
            if ((int) $account['allow_entries'] !== 1) {
                throw new AppException("Le compte {$code} n'accepte pas la saisie directe.");
            }
            $debit = round((float) ($debits[$i] ?? 0), 2);
            $credit = round((float) ($credits[$i] ?? 0), 2);
            if ($debit < 0 || $credit < 0) {
                throw new AppException('Les montants doivent être positifs.');
            }
            if ($debit > 0.0 && $credit > 0.0) {
                throw new AppException("Une ligne ne peut pas être à la fois débitrice et créditrice (compte {$code}).");
            }
            if ($debit === 0.0 && $credit === 0.0) {
                continue;
            }
            $lines[] = [
                'account_code' => $code,
                'label' => trim((string) ($labels[$i] ?? '')) ?: (string) $account['label'],
                'debit' => $debit,
                'credit' => $credit,
                'third_party' => mb_substr(trim((string) ($thirdParties[$i] ?? '')), 0, 120),
            ];
        }
        return $lines;
    }

    /**
     * @return array<string,mixed>
     */
    private function requireFiscalYear(int $id): array
    {
        $fiscalYear = $this->db->fetchOne('SELECT * FROM fiscal_years WHERE id = :id', ['id' => $id]);
        if ($fiscalYear === null) {
            throw new AppException('Exercice comptable introuvable.');
        }
        return $fiscalYear;
    }
}
