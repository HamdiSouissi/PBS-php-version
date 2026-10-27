<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database\Connection;

/**
 * Agrégats du tableau de bord (indicateurs RH, paie, facturation, trésorerie).
 * Toutes les requêtes sont tolérantes à une base vierge.
 */
final class DashboardService
{
    public function __construct(private readonly Connection $db)
    {
    }

    /**
     * @return array<string,mixed>
     */
    public function kpis(): array
    {
        return [
            'active_employees' => (int) $this->scalar('SELECT COUNT(*) FROM employees WHERE status = \'active\''),
            'pending_leaves' => (int) $this->scalar('SELECT COUNT(*) FROM leave_requests WHERE status = \'submitted\''),
            'open_payslips' => (int) $this->scalar('SELECT COUNT(*) FROM payslips WHERE status = \'draft\''),
            'unpaid_invoices' => (int) $this->scalar(
                "SELECT COUNT(*) FROM invoices WHERE type = 'sale' AND status IN ('issued','partial','overdue')"
            ),
            'overdue_invoices' => (int) $this->scalar(
                "SELECT COUNT(*) FROM invoices WHERE type = 'sale' AND status IN ('issued','partial') AND due_date < CURRENT_DATE"
            ),
            'cash_balance' => (float) $this->scalar('SELECT COALESCE(SUM(current_balance), 0) FROM bank_accounts WHERE is_active = TRUE'),
            'mrr' => (float) $this->scalar('SELECT COALESCE(mrr_ht, 0) FROM v_subscription_mrr'),
            'pending_expenses' => (int) $this->scalar("SELECT COUNT(*) FROM expense_reports WHERE status IN ('draft','submitted')"),
            'open_obligations' => (int) $this->scalar("SELECT COUNT(*) FROM compliance_obligations WHERE status = 'pending'"),
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function recentInvoices(int $limit = 6): array
    {
        return $this->db->fetchAll(
            "SELECT i.id, i.invoice_number, i.total_ttc, i.status, i.due_date, t.name
             FROM invoices i JOIN third_parties t ON t.id = i.third_party_id
             WHERE i.type = 'sale' ORDER BY i.created_at DESC LIMIT {$limit}"
        );
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function upcomingObligations(int $limit = 6): array
    {
        return $this->db->fetchAll(
            "SELECT title, authority, due_on, status, frequency
             FROM compliance_obligations
             WHERE status IN ('pending','overdue')
             ORDER BY (due_on IS NULL), due_on LIMIT {$limit}"
        );
    }

    /**
     * Chiffre d'affaires facturé par mois sur 12 mois.
     *
     * @return list<array{month:string,total:float}>
     */
    public function revenueSeries(): array
    {
        return array_map(
            static fn (array $row) => ['month' => (string) $row['month'], 'total' => (float) $row['total']],
            $this->db->fetchAll(
                "SELECT " . $this->db->yearMonth('issue_date') . " AS month, COALESCE(SUM(total_ht), 0) AS total
                 FROM invoices
                 WHERE type = 'sale' AND issue_date >= " . $this->db->dateSub('CURRENT_DATE', 12, 'month') . "
                 GROUP BY month ORDER BY month"
            )
        );
    }

    private function scalar(string $sql): mixed
    {
        try {
            return $this->db->fetchScalar($sql);
        } catch (\Throwable) {
            return 0;
        }
    }
}
