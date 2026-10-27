-- =============================================================================
-- Migration 005 - Trésorerie : comptes bancaires, mouvements, prévisions
-- MySQL 8.0.16+ / MariaDB 10.11+ (InnoDB, utf8mb4)
-- =============================================================================

CREATE TABLE IF NOT EXISTS bank_accounts (
    id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    company_id       BIGINT UNSIGNED NOT NULL REFERENCES companies(id) ON DELETE CASCADE,
    label            VARCHAR(120) NOT NULL,
    bank_name        VARCHAR(120) NOT NULL DEFAULT '',
    iban             VARCHAR(34) NOT NULL DEFAULT '',
    bic              VARCHAR(11) NOT NULL DEFAULT '',
    currency         VARCHAR(3) NOT NULL DEFAULT 'EUR',
    initial_balance  NUMERIC(14,2) NOT NULL DEFAULT 0,
    current_balance  NUMERIC(14,2) NOT NULL DEFAULT 0,
    is_active        TINYINT(1) NOT NULL DEFAULT 1,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX idx_bank_accounts_company ON bank_accounts(company_id);

CREATE TABLE IF NOT EXISTS bank_transactions (
    id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    bank_account_id  BIGINT UNSIGNED NOT NULL REFERENCES bank_accounts(id) ON DELETE CASCADE,
    transaction_date DATE NOT NULL,
    value_date       DATE,
    label            VARCHAR(255) NOT NULL,
    amount           NUMERIC(14,2) NOT NULL,
    direction        VARCHAR(10) NOT NULL,
    reference        VARCHAR(120) NOT NULL DEFAULT '',
    is_reconciled    TINYINT(1) NOT NULL DEFAULT 0,
    reconciled_at    DATETIME,
    source_type      VARCHAR(60) NOT NULL DEFAULT 'manual',
    source_id        BIGINT UNSIGNED,
    category         VARCHAR(120) NOT NULL DEFAULT '',
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT bank_tx_direction_check CHECK (direction IN ('credit','debit'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX idx_bank_tx_account ON bank_transactions(bank_account_id, transaction_date);
CREATE INDEX idx_bank_tx_reconciled ON bank_transactions(is_reconciled);

CREATE TABLE IF NOT EXISTS cash_forecasts (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    forecast_date DATE NOT NULL,
    label         VARCHAR(255) NOT NULL,
    amount_in     NUMERIC(14,2) NOT NULL DEFAULT 0,
    amount_out    NUMERIC(14,2) NOT NULL DEFAULT 0,
    source        VARCHAR(60) NOT NULL DEFAULT 'manual',
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX idx_cash_forecast_date ON cash_forecasts(forecast_date);
