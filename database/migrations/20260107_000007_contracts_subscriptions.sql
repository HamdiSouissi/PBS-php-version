-- =============================================================================
-- Migration 007 - Contrats clients, abonnements, revenus récurrents
-- =============================================================================

CREATE TABLE IF NOT EXISTS client_contracts (
    id             BIGSERIAL PRIMARY KEY,
    third_party_id BIGINT NOT NULL REFERENCES third_parties(id) ON DELETE CASCADE,
    contract_number VARCHAR(60) NOT NULL UNIQUE,
    title          VARCHAR(180) NOT NULL,
    starts_on      DATE NOT NULL,
    ends_on        DATE,
    renewal_mode   VARCHAR(20) NOT NULL DEFAULT 'tacit'
                       CHECK (renewal_mode IN ('none','tacit','express')),
    notice_days    INTEGER NOT NULL DEFAULT 30,
    amount_ht      NUMERIC(14,2) NOT NULL DEFAULT 0,
    billing_cycle  VARCHAR(20) NOT NULL DEFAULT 'monthly'
                       CHECK (billing_cycle IN ('monthly','quarterly','half_yearly','yearly','one_shot')),
    status         VARCHAR(20) NOT NULL DEFAULT 'draft'
                       CHECK (status IN ('draft','active','terminated','expired')),
    signed_on      DATE,
    notes          TEXT NOT NULL DEFAULT '',
    created_by     BIGINT REFERENCES users(id) ON DELETE SET NULL,
    created_at     TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at     TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_client_contracts_party ON client_contracts(third_party_id);
CREATE INDEX IF NOT EXISTS idx_client_contracts_status ON client_contracts(status);

CREATE TABLE IF NOT EXISTS subscription_plans (
    id            BIGSERIAL PRIMARY KEY,
    code          VARCHAR(60) NOT NULL UNIQUE,
    label         VARCHAR(180) NOT NULL,
    description   TEXT NOT NULL DEFAULT '',
    amount_ht     NUMERIC(12,2) NOT NULL DEFAULT 0,
    vat_rate      NUMERIC(6,3) NOT NULL DEFAULT 20.000,
    billing_cycle VARCHAR(20) NOT NULL DEFAULT 'monthly'
                      CHECK (billing_cycle IN ('monthly','quarterly','half_yearly','yearly')),
    is_active     BOOLEAN NOT NULL DEFAULT TRUE,
    created_at    TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS subscriptions (
    id                 BIGSERIAL PRIMARY KEY,
    third_party_id     BIGINT NOT NULL REFERENCES third_parties(id) ON DELETE CASCADE,
    plan_id            BIGINT NOT NULL REFERENCES subscription_plans(id) ON DELETE RESTRICT,
    client_contract_id BIGINT REFERENCES client_contracts(id) ON DELETE SET NULL,
    starts_on          DATE NOT NULL,
    ends_on            DATE,
    status             VARCHAR(20) NOT NULL DEFAULT 'active'
                           CHECK (status IN ('active','paused','cancelled')),
    quantity           NUMERIC(12,3) NOT NULL DEFAULT 1,
    unit_amount_ht     NUMERIC(12,2) NOT NULL DEFAULT 0,
    vat_rate           NUMERIC(6,3) NOT NULL DEFAULT 20.000,
    auto_invoice       BOOLEAN NOT NULL DEFAULT TRUE,
    next_invoice_date  DATE NOT NULL,
    last_invoice_date  DATE,
    created_at         TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at         TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_subscriptions_status ON subscriptions(status, next_invoice_date);

CREATE TABLE IF NOT EXISTS subscription_invoice_runs (
    id              BIGSERIAL PRIMARY KEY,
    run_date        DATE NOT NULL,
    generated_count INTEGER NOT NULL DEFAULT 0,
    total_ttc       NUMERIC(14,2) NOT NULL DEFAULT 0,
    details         JSONB NOT NULL DEFAULT '{}'::jsonb,
    created_by      BIGINT REFERENCES users(id) ON DELETE SET NULL,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS revenue_snapshots (
    id                   BIGSERIAL PRIMARY KEY,
    snapshot_on          DATE NOT NULL UNIQUE,
    mrr                  NUMERIC(14,2) NOT NULL DEFAULT 0,
    arr                  NUMERIC(14,2) NOT NULL DEFAULT 0,
    active_subscriptions INTEGER NOT NULL DEFAULT 0,
    churned              INTEGER NOT NULL DEFAULT 0,
    created_at           TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
