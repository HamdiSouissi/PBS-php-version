<?php

declare(strict_types=1);

namespace Modules\Accounting\Tests\Unit;

use App\Core\Application;
use App\Core\Exception\AppException;
use Modules\Accounting\Services\JournalEntryService;
use Tests\Core\DatabaseTestCase;

final class JournalEntryServiceTest extends DatabaseTestCase
{
    private JournalEntryService $service;
    private int $fiscalYearId;
    private int $journalId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = Application::container()->get(JournalEntryService::class);

        $fiscalYear = $this->db->fetchOne("SELECT id FROM fiscal_years WHERE status = 'open' ORDER BY id DESC LIMIT 1");
        self::assertNotNull($fiscalYear, 'Un exercice ouvert doit être semé par ReferenceSeeder.');
        $this->fiscalYearId = (int) $fiscalYear['id'];

        $this->db->execute("INSERT INTO journals (code, label, type) VALUES ('OD', 'Opérations diverses', 'od')");
        $this->journalId = (int) $this->db->lastInsertId();

        $this->db->execute(
            "INSERT INTO chart_accounts (code, label, account_class, account_type, allow_entries)
             VALUES ('512000', 'Banque', 5, 'asset', 1),
                    ('706000', 'Prestations de services', 7, 'income', 1),
                    ('999000', 'Compte bloqué', 8, 'neutral', 0)"
        );
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function balancedLines(): array
    {
        return [
            'account_code' => ['512000', '706000'],
            'label' => ['Encaissement', 'Vente'],
            'third_party' => ['Client Test', 'Client Test'],
            'debit' => [200.0, 0.0],
            'credit' => [0.0, 200.0],
        ];
    }

    public function testBalancedEntryIsPostedAndAppearsInBalanceView(): void
    {
        $entryId = $this->service->createEntry([
            'journal_id' => $this->journalId,
            'fiscal_year_id' => $this->fiscalYearId,
            'entry_date' => date('Y-m-d'),
            'reference' => 'T-001',
            'label' => 'Écriture équilibrée',
            'lines' => $this->balancedLines(),
        ], 1);

        $entry = $this->db->fetchOne('SELECT status FROM journal_entries WHERE id = :id', ['id' => $entryId]);
        self::assertSame('posted', $entry['status']);
        self::assertSame(2, (int) $this->db->fetchScalar('SELECT COUNT(*) FROM journal_entry_lines WHERE entry_id = :id', ['id' => $entryId]));

        $balance = $this->db->fetchOne("SELECT total_debit FROM v_account_balances WHERE code = '512000'");
        self::assertEqualsWithDelta(200.0, (float) $balance['total_debit'], 0.001);
    }

    public function testUnbalancedEntryIsRejected(): void
    {
        $lines = $this->balancedLines();
        $lines['credit'][1] = 199.0;

        $this->expectException(AppException::class);
        $this->service->createEntry([
            'journal_id' => $this->journalId,
            'fiscal_year_id' => $this->fiscalYearId,
            'entry_date' => date('Y-m-d'),
            'label' => 'Déséquilibrée',
            'lines' => $lines,
        ], 1);
    }

    public function testUnknownAccountIsRejected(): void
    {
        $lines = $this->balancedLines();
        $lines['account_code'][0] = '404000';

        $this->expectException(AppException::class);
        $this->service->createEntry([
            'journal_id' => $this->journalId,
            'fiscal_year_id' => $this->fiscalYearId,
            'entry_date' => date('Y-m-d'),
            'label' => 'Compte inexistant',
            'lines' => $lines,
        ], 1);
    }

    public function testEntryOnClosedFiscalYearIsRejected(): void
    {
        $this->db->execute("UPDATE fiscal_years SET status = 'closed' WHERE id = {$this->fiscalYearId}");

        $this->expectException(AppException::class);
        $this->service->createEntry([
            'journal_id' => $this->journalId,
            'fiscal_year_id' => $this->fiscalYearId,
            'entry_date' => date('Y-m-d'),
            'label' => 'Exercice clos',
            'lines' => $this->balancedLines(),
        ], 1);
    }

    public function testCancelRemovesEntryFromBalanceButKeepsLines(): void
    {
        $entryId = $this->service->createEntry([
            'journal_id' => $this->journalId,
            'fiscal_year_id' => $this->fiscalYearId,
            'entry_date' => date('Y-m-d'),
            'label' => 'À annuler',
            'lines' => $this->balancedLines(),
        ], 1);

        $this->service->cancel($entryId);

        $status = $this->db->fetchScalar('SELECT status FROM journal_entries WHERE id = :id', ['id' => $entryId]);
        self::assertSame('cancelled', $status);
        // Les lignes restent tracées mais la balance ne retient que le « posted ».
        self::assertSame(2, (int) $this->db->fetchScalar('SELECT COUNT(*) FROM journal_entry_lines WHERE entry_id = :id', ['id' => $entryId]));
        $balance = $this->db->fetchOne("SELECT total_debit FROM v_account_balances WHERE code = '512000'");
        self::assertEqualsWithDelta(0.0, (float) $balance['total_debit'], 0.001);
    }

    public function testLineOnBothSidesIsRejected(): void
    {
        $lines = $this->balancedLines();
        $lines['debit'][1] = 50.0;

        $this->expectException(AppException::class);
        $this->service->createEntry([
            'journal_id' => $this->journalId,
            'fiscal_year_id' => $this->fiscalYearId,
            'entry_date' => date('Y-m-d'),
            'label' => 'Ligne bicôtée',
            'lines' => $lines,
        ], 1);
    }
}
