-- =============================================================================
-- Migration 003 - Paie française : règles versionnées, périodes, bulletins, DSN
-- MySQL 8.0.16+ / MariaDB 10.11+ (InnoDB, utf8mb4)
-- =============================================================================

CREATE TABLE IF NOT EXISTS payroll_rule_versions (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    code            VARCHAR(60) NOT NULL,
    label           VARCHAR(180) NOT NULL,
    category        VARCHAR(60) NOT NULL,
    base_type       VARCHAR(30) NOT NULL DEFAULT 'brut',
    rate_employee   NUMERIC(8,4) NOT NULL DEFAULT 0,
    rate_employer   NUMERIC(8,4) NOT NULL DEFAULT 0,
    ceiling_month   NUMERIC(12,2),
    is_deductible   TINYINT(1) NOT NULL DEFAULT 0,
    is_taxable      TINYINT(1) NOT NULL DEFAULT 0,
    effective_from  DATE NOT NULL,
    effective_to    DATE,
    source          VARCHAR(120) NOT NULL DEFAULT 'URSSAF',
    notes           VARCHAR(1000) NOT NULL DEFAULT '',
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT payroll_rules_category_check CHECK (category IN ('sante','vieillesse','retraite_complementaire',
                                            'chomage','famille','accident_travail','csg_crds',
                                            'autres','cotisation_statutaire','reductions')),
    CONSTRAINT payroll_rules_base_type_check CHECK (base_type IN ('brut','plafond','brut_et_plafond','csg','forfait'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX idx_payroll_rules_code ON payroll_rule_versions(code, effective_from);

CREATE TABLE IF NOT EXISTS payroll_periods (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    company_id  BIGINT UNSIGNED NOT NULL REFERENCES companies(id) ON DELETE CASCADE,
    year        INTEGER NOT NULL,
    month       INTEGER NOT NULL CHECK (month BETWEEN 1 AND 12),
    starts_on   DATE NOT NULL,
    ends_on     DATE NOT NULL,
    status      VARCHAR(20) NOT NULL DEFAULT 'open',
    is_locked   TINYINT(1) NOT NULL DEFAULT 0,
    closed_at   DATETIME,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (company_id, year, month),
    CONSTRAINT payroll_periods_status_check CHECK (status IN ('open','closed'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS payslips (
    id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    period_id           BIGINT UNSIGNED NOT NULL REFERENCES payroll_periods(id) ON DELETE CASCADE,
    employee_id         BIGINT UNSIGNED NOT NULL REFERENCES employees(id) ON DELETE CASCADE,
    contract_id         BIGINT UNSIGNED REFERENCES employment_contracts(id) ON DELETE SET NULL,
    status              VARCHAR(20) NOT NULL DEFAULT 'draft',
    hours_worked        NUMERIC(7,2) NOT NULL DEFAULT 151.67,
    gross_salary        NUMERIC(12,2) NOT NULL DEFAULT 0,
    total_employee      NUMERIC(12,2) NOT NULL DEFAULT 0,
    total_employer      NUMERIC(12,2) NOT NULL DEFAULT 0,
    net_social          NUMERIC(12,2) NOT NULL DEFAULT 0,
    net_taxable         NUMERIC(12,2) NOT NULL DEFAULT 0,
    net_payable         NUMERIC(12,2) NOT NULL DEFAULT 0,
    general_reduction   NUMERIC(12,2) NOT NULL DEFAULT 0,
    is_paid             TINYINT(1) NOT NULL DEFAULT 0,
    payment_date        DATE,
    document_path       VARCHAR(255),
    generated_by        BIGINT UNSIGNED REFERENCES users(id) ON DELETE SET NULL,
    validated_by        BIGINT UNSIGNED REFERENCES users(id) ON DELETE SET NULL,
    validated_at        DATETIME,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (period_id, employee_id),
    CONSTRAINT payslips_status_check CHECK (status IN ('draft','validated','locked'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX idx_payslips_employee ON payslips(employee_id);
CREATE INDEX idx_payslips_status ON payslips(status);

CREATE TABLE IF NOT EXISTS payslip_lines (
    id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    payslip_id       BIGINT UNSIGNED NOT NULL REFERENCES payslips(id) ON DELETE CASCADE,
    rule_code        VARCHAR(60) NOT NULL,
    label            VARCHAR(180) NOT NULL,
    base_amount      NUMERIC(12,2) NOT NULL DEFAULT 0,
    rate_employee    NUMERIC(8,4) NOT NULL DEFAULT 0,
    rate_employer    NUMERIC(8,4) NOT NULL DEFAULT 0,
    amount_employee  NUMERIC(12,2) NOT NULL DEFAULT 0,
    amount_employer  NUMERIC(12,2) NOT NULL DEFAULT 0,
    sort_order       INTEGER NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX idx_payslip_lines_payslip ON payslip_lines(payslip_id);

-- Préparation DSN : aucun envoi automatique, uniquement la normalisation des données.
CREATE TABLE IF NOT EXISTS dsn_preparations (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    company_id   BIGINT UNSIGNED NOT NULL REFERENCES companies(id) ON DELETE CASCADE,
    period_id    BIGINT UNSIGNED REFERENCES payroll_periods(id) ON DELETE SET NULL,
    status       VARCHAR(30) NOT NULL DEFAULT 'prepared',
    file_path    VARCHAR(255),
    declared_at  DATETIME,
    notes        VARCHAR(1000) NOT NULL DEFAULT '',
    created_by   BIGINT UNSIGNED REFERENCES users(id) ON DELETE SET NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT dsn_status_check CHECK (status IN ('prepared','controlled','ready','exported'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
