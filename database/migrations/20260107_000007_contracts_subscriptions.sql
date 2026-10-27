-- =============================================================================
-- Migration 007 - Contrats clients, abonnements, revenus récurrents
-- MySQL 8.0.16+ / MariaDB 10.11+ (InnoDB, utf8mb4)
-- =============================================================================

CREATE TABLE IF NOT EXISTS client_contracts (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    third_party_id  BIGINT UNSIGNED NOT NULL REFERENCES third_parties(id) ON DELETE CASCADE,
    contract_number VARCHAR(60) NOT NULL UNIQUE,
    title           VARCHAR(180) NOT NULL,
    starts_on       DATE NOT NULL,
    ends_on         DATE,
    renewal_mode    VARCHAR(20) NOT NULL DEFAULT 'tacit',
    notice_days     INTEGER NOT NULL DEFAULT 30,
    amount_ht       NUMERIC(14,2) NOT NULL DEFAULT 0,
    billing_cycle   VARCHAR(20) NOT NULL DEFAULT 'monthly',
    status          VARCHAR(20) NOT NULL DEFAULT 'draft',
    signed_on       DATE,
    notes           VARCHAR(1000) NOT NULL DEFAULT '',
    created_by      BIGINT UNSIGNED REFERENCES users(id) ON DELETE SET NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT client_contracts_renewal_check CHECK (renewal_mode IN ('none','tacit','express')),
    CONSTRAINT client_contracts_cycle_check CHECK (billing_cycle IN ('monthly','quarterly','half_yearly','yearly','one_shot')),
    CONSTRAINT client_contracts_status_check CHECK (status IN ('draft','active','terminated','expired'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX idx_client_contracts_party ON client_contracts(third_party_id);
CREATE INDEX idx_client_contracts_status ON client_contracts(status);

CREATE TABLE IF NOT EXISTS subscription_plans (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    code          VARCHAR(60) NOT NULL UNIQUE,
    label         VARCHAR(180) NOT NULL,
    description   VARCHAR(1000) NOT NULL DEFAULT '',
    amount_ht     NUMERIC(12,2) NOT NULL DEFAULT 0,
    vat_rate      NUMERIC(6,3) NOT NULL DEFAULT 20.000,
    billing_cycle VARCHAR(20) NOT NULL DEFAULT 'monthly',
    is_active     TINYINT(1) NOT NULL DEFAULT 1,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT plans_cycle_check CHECK (billing_cycle IN ('monthly','quarterly','half_yearly','yearly'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS subscriptions (
    id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    third_party_id     BIGINT UNSIGNED NOT NULL REFERENCES third_parties(id) ON DELETE CASCADE,
    plan_id            BIGINT UNSIGNED NOT NULL REFERENCES subscription_plans(id) ON DELETE RESTRICT,
    client_contract_id BIGINT UNSIGNED REFERENCES client_contracts(id) ON DELETE SET NULL,
    starts_on          DATE NOT NULL,
    ends_on            DATE,
    status             VARCHAR(20) NOT NULL DEFAULT 'active',
    quantity           NUMERIC(12,3) NOT NULL DEFAULT 1,
    unit_amount_ht     NUMERIC(12,2) NOT NULL DEFAULT 0,
    vat_rate           NUMERIC(6,3) NOT NULL DEFAULT 20.000,
    auto_invoice       TINYINT(1) NOT NULL DEFAULT 1,
    next_invoice_date  DATE NOT NULL,
    last_invoice_date  DATE,
    created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT subscriptions_status_check CHECK (status IN ('active','paused','cancelled'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX idx_subscriptions_status ON subscriptions(status, next_invoice_date);

CREATE TABLE IF NOT EXISTS subscription_invoice_runs (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    run_date        DATE NOT NULL,
    generated_count INTEGER NOT NULL DEFAULT 0,
    total_ttc       NUMERIC(14,2) NOT NULL DEFAULT 0,
    details         JSON NOT NULL,
    created_by      BIGINT UNSIGNED REFERENCES users(id) ON DELETE SET NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS revenue_snapshots (
    id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    snapshot_on          DATE NOT NULL UNIQUE,
    mrr                  NUMERIC(14,2) NOT NULL DEFAULT 0,
    arr                  NUMERIC(14,2) NOT NULL DEFAULT 0,
    active_subscriptions INTEGER NOT NULL DEFAULT 0,
    churned              INTEGER NOT NULL DEFAULT 0,
    created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
