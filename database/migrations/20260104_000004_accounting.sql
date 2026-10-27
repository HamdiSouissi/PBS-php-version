-- =============================================================================
-- Migration 004 - Comptabilité : exercices, PCG, journaux, écritures, TVA,
-- immobilisations, amortissements, budgets
-- MySQL 8.0.16+ / MariaDB 10.11+ (InnoDB, utf8mb4)
-- =============================================================================

CREATE TABLE IF NOT EXISTS fiscal_years (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    label       VARCHAR(90) NOT NULL,
    starts_on   DATE NOT NULL,
    ends_on     DATE NOT NULL,
    status      VARCHAR(20) NOT NULL DEFAULT 'open',
    closed_at   DATETIME,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_fiscal_dates CHECK (ends_on > starts_on),
    CONSTRAINT fiscal_years_status_check CHECK (status IN ('open','closed'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chart_accounts (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    code           VARCHAR(20) NOT NULL UNIQUE,
    label          VARCHAR(180) NOT NULL,
    account_class  SMALLINT NOT NULL CHECK (account_class BETWEEN 1 AND 8),
    parent_code    VARCHAR(20) REFERENCES chart_accounts(code),
    account_type   VARCHAR(20) NOT NULL,
    is_auxiliary   TINYINT(1) NOT NULL DEFAULT 0,
    allow_entries  TINYINT(1) NOT NULL DEFAULT 1,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chart_accounts_type_check CHECK (account_type IN ('asset','liability','equity','income','expense','neutral'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX idx_chart_class ON chart_accounts(account_class);

CREATE TABLE IF NOT EXISTS journals (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    code        VARCHAR(10) NOT NULL UNIQUE,
    label       VARCHAR(120) NOT NULL,
    type        VARCHAR(30) NOT NULL DEFAULT 'od',
    CONSTRAINT journals_type_check CHECK (type IN ('sales','purchases','bank','cash','od','closing'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS journal_entries (
    id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    journal_id        BIGINT UNSIGNED NOT NULL REFERENCES journals(id) ON DELETE RESTRICT,
    fiscal_year_id    BIGINT UNSIGNED NOT NULL REFERENCES fiscal_years(id) ON DELETE RESTRICT,
    entry_date        DATE NOT NULL,
    reference         VARCHAR(60) NOT NULL DEFAULT '',
    label             VARCHAR(255) NOT NULL,
    status            VARCHAR(20) NOT NULL DEFAULT 'draft',
    reversed_entry_id BIGINT UNSIGNED REFERENCES journal_entries(id) ON DELETE SET NULL,
    source_type       VARCHAR(60) NOT NULL DEFAULT 'manual',
    source_id         BIGINT UNSIGNED,
    created_by        BIGINT UNSIGNED REFERENCES users(id) ON DELETE SET NULL,
    posted_at         DATETIME,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT journal_entries_status_check CHECK (status IN ('draft','posted','cancelled'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX idx_entries_date ON journal_entries(entry_date);
CREATE INDEX idx_entries_fiscal ON journal_entries(fiscal_year_id);

CREATE TABLE IF NOT EXISTS journal_entry_lines (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    entry_id      BIGINT UNSIGNED NOT NULL REFERENCES journal_entries(id) ON DELETE CASCADE,
    account_code  VARCHAR(20) NOT NULL REFERENCES chart_accounts(code),
    label         VARCHAR(255) NOT NULL DEFAULT '',
    debit         NUMERIC(14,2) NOT NULL DEFAULT 0 CHECK (debit >= 0),
    credit        NUMERIC(14,2) NOT NULL DEFAULT 0 CHECK (credit >= 0),
    third_party   VARCHAR(120) NOT NULL DEFAULT '',
    CONSTRAINT chk_line_side CHECK (NOT (debit > 0 AND credit > 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX idx_entry_lines_account ON journal_entry_lines(account_code);
CREATE INDEX idx_entry_lines_entry ON journal_entry_lines(entry_id);

CREATE TABLE IF NOT EXISTS vat_declarations (
    id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    period_label     VARCHAR(60) NOT NULL,
    starts_on        DATE NOT NULL,
    ends_on          DATE NOT NULL,
    collected_vat    NUMERIC(14,2) NOT NULL DEFAULT 0,
    deductible_vat   NUMERIC(14,2) NOT NULL DEFAULT 0,
    due_vat          NUMERIC(14,2) NOT NULL DEFAULT 0,
    status           VARCHAR(20) NOT NULL DEFAULT 'draft',
    file_path        VARCHAR(255),
    submitted_at     DATETIME,
    created_by       BIGINT UNSIGNED REFERENCES users(id) ON DELETE SET NULL,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT vat_declarations_status_check CHECK (status IN ('draft','submitted','paid','cancelled'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fixed_assets (
    id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    code               VARCHAR(30) NOT NULL UNIQUE,
    label              VARCHAR(180) NOT NULL,
    account_code       VARCHAR(20) NOT NULL REFERENCES chart_accounts(code),
    acquisition_date   DATE NOT NULL,
    acquisition_value  NUMERIC(14,2) NOT NULL CHECK (acquisition_value > 0),
    useful_life_years  INTEGER NOT NULL CHECK (useful_life_years > 0),
    residual_value     NUMERIC(14,2) NOT NULL DEFAULT 0,
    depreciation_mode  VARCHAR(20) NOT NULL DEFAULT 'linear',
    status             VARCHAR(20) NOT NULL DEFAULT 'active',
    journal_entry_id   BIGINT UNSIGNED REFERENCES journal_entries(id) ON DELETE SET NULL,
    created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fixed_assets_mode_check CHECK (depreciation_mode IN ('linear','none')),
    CONSTRAINT fixed_assets_status_check CHECK (status IN ('active','disposed'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS asset_depreciation_schedules (
    id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    asset_id         BIGINT UNSIGNED NOT NULL REFERENCES fixed_assets(id) ON DELETE CASCADE,
    fiscal_year_id   BIGINT UNSIGNED NOT NULL REFERENCES fiscal_years(id) ON DELETE CASCADE,
    annuity          NUMERIC(14,2) NOT NULL DEFAULT 0,
    cumulative       NUMERIC(14,2) NOT NULL DEFAULT 0,
    net_book_value   NUMERIC(14,2) NOT NULL DEFAULT 0,
    UNIQUE (asset_id, fiscal_year_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS budgets (
    id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    fiscal_year_id   BIGINT UNSIGNED NOT NULL REFERENCES fiscal_years(id) ON DELETE CASCADE,
    account_code     VARCHAR(20) NOT NULL REFERENCES chart_accounts(code),
    period_year      INTEGER NOT NULL,
    period_month     INTEGER NOT NULL CHECK (period_month BETWEEN 1 AND 12),
    category         VARCHAR(120) NOT NULL DEFAULT '',
    planned_amount   NUMERIC(14,2) NOT NULL DEFAULT 0,
    actual_amount    NUMERIC(14,2) NOT NULL DEFAULT 0,
    UNIQUE (fiscal_year_id, account_code, period_month)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
