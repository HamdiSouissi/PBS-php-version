-- =============================================================================
-- Migration 005 - Trésorerie : comptes bancaires, mouvements, prévisions
-- =============================================================================

CREATE TABLE IF NOT EXISTS bank_accounts (
    id               BIGSERIAL PRIMARY KEY,
    company_id       BIGINT NOT NULL REFERENCES companies(id) ON DELETE CASCADE,
    label            VARCHAR(120) NOT NULL,
    bank_name        VARCHAR(120) NOT NULL DEFAULT '',
    iban             VARCHAR(34) NOT NULL DEFAULT '',
    bic              VARCHAR(11) NOT NULL DEFAULT '',
    currency         VARCHAR(3) NOT NULL DEFAULT 'EUR',
    initial_balance  NUMERIC(14,2) NOT NULL DEFAULT 0,
    current_balance  NUMERIC(14,2) NOT NULL DEFAULT 0,
    is_active        BOOLEAN NOT NULL DEFAULT TRUE,
    created_at       TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_bank_accounts_company ON bank_accounts(company_id);

CREATE TABLE IF NOT EXISTS bank_transactions (
    id              BIGSERIAL PRIMARY KEY,
    bank_account_id BIGINT NOT NULL REFERENCES bank_accounts(id) ON DELETE CASCADE,
    transaction_date DATE NOT NULL,
    value_date      DATE,
    label           VARCHAR(255) NOT NULL,
    amount          NUMERIC(14,2) NOT NULL,
    direction       VARCHAR(10) NOT NULL CHECK (direction IN ('credit','debit')),
    reference       VARCHAR(120) NOT NULL DEFAULT '',
    is_reconciled   BOOLEAN NOT NULL DEFAULT FALSE,
    reconciled_at   TIMESTAMPTZ,
    source_type     VARCHAR(60) NOT NULL DEFAULT 'manual',
    source_id       BIGINT,
    category        VARCHAR(120) NOT NULL DEFAULT '',
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_bank_tx_account ON bank_transactions(bank_account_id, transaction_date);
CREATE INDEX IF NOT EXISTS idx_bank_tx_reconciled ON bank_transactions(is_reconciled);

CREATE TABLE IF NOT EXISTS cash_forecasts (
    id            BIGSERIAL PRIMARY KEY,
    forecast_date DATE NOT NULL,
    label         VARCHAR(255) NOT NULL,
    amount_in     NUMERIC(14,2) NOT NULL DEFAULT 0,
    amount_out    NUMERIC(14,2) NOT NULL DEFAULT 0,
    source        VARCHAR(60) NOT NULL DEFAULT 'manual',
    created_at    TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_cash_forecast_date ON cash_forecasts(forecast_date);
