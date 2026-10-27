-- =============================================================================
-- Migration 013 - Correctif de fiabilité financière :
-- la vue v_account_balances intégrait les lignes des écritures annulées ou
-- brouillons (un LEFT JOIN ... ON e.status = 'posted' ne filtre pas la table
-- de gauche). Le solde comptable ne doit retenir que les écritures 'posted'.
-- MySQL 8.0.16+ / MariaDB 10.11+ (InnoDB, utf8mb4)
-- =============================================================================

CREATE OR REPLACE VIEW v_account_balances AS
SELECT a.code,
       a.label,
       a.account_class,
       a.account_type,
       COALESCE(SUM(CASE WHEN e.status = 'posted' THEN l.debit  ELSE 0 END), 0) AS total_debit,
       COALESCE(SUM(CASE WHEN e.status = 'posted' THEN l.credit ELSE 0 END), 0) AS total_credit,
       COALESCE(SUM(CASE WHEN e.status = 'posted' THEN l.debit  ELSE 0 END), 0)
         - COALESCE(SUM(CASE WHEN e.status = 'posted' THEN l.credit ELSE 0 END), 0) AS solde_debit,
       COALESCE(SUM(CASE WHEN e.status = 'posted' THEN l.credit ELSE 0 END), 0)
         - COALESCE(SUM(CASE WHEN e.status = 'posted' THEN l.debit  ELSE 0 END), 0) AS solde_credit
FROM chart_accounts a
LEFT JOIN journal_entry_lines l ON l.account_code = a.code
LEFT JOIN journal_entries e ON e.id = l.entry_id
GROUP BY a.code, a.label, a.account_class, a.account_type;
