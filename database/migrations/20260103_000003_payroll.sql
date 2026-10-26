-- =============================================================================
-- Migration 003 - Paie française : règles versionnées, périodes, bulletins, DSN
-- =============================================================================

CREATE TABLE IF NOT EXISTS payroll_rule_versions (
    id              BIGSERIAL PRIMARY KEY,
    code            VARCHAR(60) NOT NULL,
    label           VARCHAR(180) NOT NULL,
    category        VARCHAR(60) NOT NULL
                        CHECK (category IN ('sante','vieillesse','retraite_complementaire',
                                            'chomage','famille','accident_travail','csg_crds',
                                            'autres','cotisation_statutaire','reductions')),
    base_type       VARCHAR(30) NOT NULL DEFAULT 'brut'
                        CHECK (base_type IN ('brut','plafond','brut_et_plafond','csg','forfait')),
    rate_employee   NUMERIC(8,4) NOT NULL DEFAULT 0,
    rate_employer   NUMERIC(8,4) NOT NULL DEFAULT 0,
    ceiling_month   NUMERIC(12,2),
    is_deductible   BOOLEAN NOT NULL DEFAULT FALSE,
    is_taxable      BOOLEAN NOT NULL DEFAULT FALSE,
    effective_from  DATE NOT NULL,
    effective_to    DATE,
    source          VARCHAR(120) NOT NULL DEFAULT 'URSSAF',
    notes           TEXT NOT NULL DEFAULT '',
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_payroll_rules_code ON payroll_rule_versions(code, effective_from);

CREATE TABLE IF NOT EXISTS payroll_periods (
    id          BIGSERIAL PRIMARY KEY,
    company_id  BIGINT NOT NULL REFERENCES companies(id) ON DELETE CASCADE,
    year        INTEGER NOT NULL,
    month       INTEGER NOT NULL CHECK (month BETWEEN 1 AND 12),
    starts_on   DATE NOT NULL,
    ends_on     DATE NOT NULL,
    status      VARCHAR(20) NOT NULL DEFAULT 'open'
                    CHECK (status IN ('open','closed')),
    is_locked   BOOLEAN NOT NULL DEFAULT FALSE,
    closed_at   TIMESTAMPTZ,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (company_id, year, month)
);

CREATE TABLE IF NOT EXISTS payslips (
    id                  BIGSERIAL PRIMARY KEY,
    period_id           BIGINT NOT NULL REFERENCES payroll_periods(id) ON DELETE CASCADE,
    employee_id         BIGINT NOT NULL REFERENCES employees(id) ON DELETE CASCADE,
    contract_id         BIGINT REFERENCES employment_contracts(id) ON DELETE SET NULL,
    status              VARCHAR(20) NOT NULL DEFAULT 'draft'
                            CHECK (status IN ('draft','validated','locked')),
    hours_worked        NUMERIC(7,2) NOT NULL DEFAULT 151.67,
    gross_salary        NUMERIC(12,2) NOT NULL DEFAULT 0,
    total_employee      NUMERIC(12,2) NOT NULL DEFAULT 0,
    total_employer      NUMERIC(12,2) NOT NULL DEFAULT 0,
    net_social          NUMERIC(12,2) NOT NULL DEFAULT 0,
    net_taxable         NUMERIC(12,2) NOT NULL DEFAULT 0,
    net_payable         NUMERIC(12,2) NOT NULL DEFAULT 0,
    general_reduction   NUMERIC(12,2) NOT NULL DEFAULT 0,
    is_paid             BOOLEAN NOT NULL DEFAULT FALSE,
    payment_date        DATE,
    document_path       VARCHAR(255),
    generated_by        BIGINT REFERENCES users(id) ON DELETE SET NULL,
    validated_by        BIGINT REFERENCES users(id) ON DELETE SET NULL,
    validated_at        TIMESTAMPTZ,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (period_id, employee_id)
);
CREATE INDEX IF NOT EXISTS idx_payslips_employee ON payslips(employee_id);
CREATE INDEX IF NOT EXISTS idx_payslips_status ON payslips(status);

CREATE TABLE IF NOT EXISTS payslip_lines (
    id               BIGSERIAL PRIMARY KEY,
    payslip_id       BIGINT NOT NULL REFERENCES payslips(id) ON DELETE CASCADE,
    rule_code        VARCHAR(60) NOT NULL,
    label            VARCHAR(180) NOT NULL,
    base_amount      NUMERIC(12,2) NOT NULL DEFAULT 0,
    rate_employee    NUMERIC(8,4) NOT NULL DEFAULT 0,
    rate_employer    NUMERIC(8,4) NOT NULL DEFAULT 0,
    amount_employee  NUMERIC(12,2) NOT NULL DEFAULT 0,
    amount_employer  NUMERIC(12,2) NOT NULL DEFAULT 0,
    sort_order       INTEGER NOT NULL DEFAULT 0
);
CREATE INDEX IF NOT EXISTS idx_payslip_lines_payslip ON payslip_lines(payslip_id);

-- Préparation DSN : aucun envoi automatique, uniquement la normalisation des données.
CREATE TABLE IF NOT EXISTS dsn_preparations (
    id           BIGSERIAL PRIMARY KEY,
    company_id   BIGINT NOT NULL REFERENCES companies(id) ON DELETE CASCADE,
    period_id    BIGINT REFERENCES payroll_periods(id) ON DELETE SET NULL,
    status       VARCHAR(30) NOT NULL DEFAULT 'prepared'
                     CHECK (status IN ('prepared','controlled','ready','exported')),
    file_path    VARCHAR(255),
    declared_at  TIMESTAMPTZ,
    notes        TEXT NOT NULL DEFAULT '',
    created_by   BIGINT REFERENCES users(id) ON DELETE SET NULL,
    created_at   TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
