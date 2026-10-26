-- =============================================================================
-- Migration 008 - Notes de frais, associés et dividendes
-- =============================================================================

CREATE TABLE IF NOT EXISTS expense_reports (
    id           BIGSERIAL PRIMARY KEY,
    employee_id  BIGINT NOT NULL REFERENCES employees(id) ON DELETE CASCADE,
    title        VARCHAR(180) NOT NULL,
    status       VARCHAR(20) NOT NULL DEFAULT 'draft'
                     CHECK (status IN ('draft','submitted','approved','rejected','paid')),
    total_amount NUMERIC(12,2) NOT NULL DEFAULT 0,
    submitted_at TIMESTAMPTZ,
    approved_by  BIGINT REFERENCES users(id) ON DELETE SET NULL,
    approved_at  TIMESTAMPTZ,
    paid_on      DATE,
    notes        TEXT NOT NULL DEFAULT '',
    created_at   TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at   TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_expense_reports_employee ON expense_reports(employee_id);
CREATE INDEX IF NOT EXISTS idx_expense_reports_status ON expense_reports(status);

CREATE TABLE IF NOT EXISTS expense_lines (
    id           BIGSERIAL PRIMARY KEY,
    report_id    BIGINT NOT NULL REFERENCES expense_reports(id) ON DELETE CASCADE,
    spent_on     DATE NOT NULL,
    category     VARCHAR(40) NOT NULL
                     CHECK (category IN ('transport','meal','accommodation','fuel','kilometric','supplies','other')),
    label        VARCHAR(255) NOT NULL,
    amount       NUMERIC(12,2) NOT NULL DEFAULT 0,
    vat_amount   NUMERIC(12,2) NOT NULL DEFAULT 0,
    distance_km  NUMERIC(10,2) NOT NULL DEFAULT 0,
    km_rate      NUMERIC(8,3) NOT NULL DEFAULT 0,
    receipt_path VARCHAR(255) NOT NULL DEFAULT '',
    ocr_data     JSONB NOT NULL DEFAULT '{}'::jsonb,
    created_at   TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_expense_lines_report ON expense_lines(report_id);

CREATE TABLE IF NOT EXISTS shareholders (
    id           BIGSERIAL PRIMARY KEY,
    company_id   BIGINT NOT NULL REFERENCES companies(id) ON DELETE CASCADE,
    third_party_id BIGINT REFERENCES third_parties(id) ON DELETE SET NULL,
    entity_name  VARCHAR(180) NOT NULL DEFAULT '',
    first_name   VARCHAR(100) NOT NULL DEFAULT '',
    last_name    VARCHAR(100) NOT NULL DEFAULT '',
    type         VARCHAR(20) NOT NULL CHECK (type IN ('individual','company')),
    shares_count INTEGER NOT NULL DEFAULT 0 CHECK (shares_count >= 0),
    share_pct    NUMERIC(7,4) NOT NULL DEFAULT 0,
    entry_date   DATE NOT NULL,
    exit_date    DATE,
    created_at   TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_shareholders_company ON shareholders(company_id);

CREATE TABLE IF NOT EXISTS dividends (
    id                 BIGSERIAL PRIMARY KEY,
    company_id         BIGINT NOT NULL REFERENCES companies(id) ON DELETE CASCADE,
    shareholder_id     BIGINT NOT NULL REFERENCES shareholders(id) ON DELETE CASCADE,
    fiscal_year_id     BIGINT REFERENCES fiscal_years(id) ON DELETE SET NULL,
    decided_on         DATE NOT NULL,
    gross_amount       NUMERIC(14,2) NOT NULL CHECK (gross_amount > 0),
    flat_tax_rate      NUMERIC(6,3) NOT NULL DEFAULT 30.000,
    social_rate        NUMERIC(6,3) NOT NULL DEFAULT 17.200,
    tax_option         VARCHAR(20) NOT NULL DEFAULT 'pfuo'
                           CHECK (tax_option IN ('pfuo','income_schedule')),
    social_deductions  NUMERIC(14,2) NOT NULL DEFAULT 0,
    net_amount         NUMERIC(14,2) NOT NULL DEFAULT 0,
    status             VARCHAR(20) NOT NULL DEFAULT 'proposed'
                           CHECK (status IN ('proposed','decided','paid')),
    paid_on            DATE,
    notes              TEXT NOT NULL DEFAULT '',
    created_at         TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_dividends_company ON dividends(company_id);
