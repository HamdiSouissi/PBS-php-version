-- =============================================================================
-- Migration 010 - Vues SQL métier (MySQL 8 / MariaDB 10.11+)
-- =============================================================================

CREATE OR REPLACE VIEW v_active_contracts AS
SELECT c.*, e.employee_number, e.last_name, e.first_name, e.company_id
FROM employment_contracts c
JOIN employees e ON e.id = c.employee_id
WHERE c.status = 'active'
  AND (c.end_date IS NULL OR c.end_date >= CURRENT_DATE);

CREATE OR REPLACE VIEW v_account_balances AS
SELECT a.code,
       a.label,
       a.account_class,
       a.account_type,
       COALESCE(SUM(l.debit), 0)  AS total_debit,
       COALESCE(SUM(l.credit), 0) AS total_credit,
       COALESCE(SUM(l.debit), 0) - COALESCE(SUM(l.credit), 0) AS solde_debit,
       COALESCE(SUM(l.credit), 0) - COALESCE(SUM(l.debit), 0) AS solde_credit
FROM chart_accounts a
LEFT JOIN journal_entry_lines l ON l.account_code = a.code
LEFT JOIN journal_entries e ON e.id = l.entry_id AND e.status = 'posted'
GROUP BY a.code, a.label, a.account_class, a.account_type;

CREATE OR REPLACE VIEW v_general_ledger AS
SELECT e.entry_date,
       j.code AS journal_code,
       e.reference,
       e.label AS entry_label,
       l.account_code,
       a.label AS account_label,
       l.third_party,
       l.debit,
       l.credit
FROM journal_entries e
JOIN journals j ON j.id = e.journal_id
JOIN journal_entry_lines l ON l.entry_id = e.id
JOIN chart_accounts a ON a.code = l.account_code
WHERE e.status = 'posted';

CREATE OR REPLACE VIEW v_invoice_balances AS
SELECT i.id,
       i.invoice_number,
       i.type,
       i.status,
       t.name AS third_party_name,
       i.issue_date,
       i.due_date,
       i.total_ht,
       i.total_vat,
       i.total_ttc,
       i.amount_paid,
       i.balance,
       CASE WHEN i.status IN ('issued','partial') AND i.due_date < CURRENT_DATE
            THEN TRUE ELSE FALSE END AS is_overdue
FROM invoices i
JOIN third_parties t ON t.id = i.third_party_id;

CREATE OR REPLACE VIEW v_payslip_summary AS
SELECT p.id,
       per.year,
       per.month,
       e.employee_number,
       e.last_name,
       e.first_name,
       p.status,
       p.gross_salary,
       p.total_employee,
       p.total_employer,
       p.net_taxable,
       p.net_payable,
       p.is_paid
FROM payslips p
JOIN payroll_periods per ON per.id = p.period_id
JOIN employees e ON e.id = p.employee_id;

CREATE OR REPLACE VIEW v_cash_position AS
SELECT b.id,
       b.label,
       b.iban,
       b.initial_balance,
       COALESCE(SUM(CASE WHEN t.direction = 'credit' THEN t.amount ELSE -t.amount END), 0) AS movements,
       b.initial_balance
         + COALESCE(SUM(CASE WHEN t.direction = 'credit' THEN t.amount ELSE -t.amount END), 0) AS computed_balance
FROM bank_accounts b
LEFT JOIN bank_transactions t ON t.bank_account_id = b.id
GROUP BY b.id, b.label, b.iban, b.initial_balance;

CREATE OR REPLACE VIEW v_subscription_mrr AS
SELECT COALESCE(SUM(
        CASE s.plan_id IS NOT NULL WHEN TRUE THEN
            CASE sp.billing_cycle
                WHEN 'monthly' THEN s.unit_amount_ht * s.quantity
                WHEN 'quarterly' THEN s.unit_amount_ht * s.quantity / 3
                WHEN 'half_yearly' THEN s.unit_amount_ht * s.quantity / 6
                WHEN 'yearly' THEN s.unit_amount_ht * s.quantity / 12
            END
        END
       ), 0) AS mrr_ht
FROM subscriptions s
JOIN subscription_plans sp ON sp.id = s.plan_id
WHERE s.status = 'active'
  AND (s.ends_on IS NULL OR s.ends_on >= CURRENT_DATE);
