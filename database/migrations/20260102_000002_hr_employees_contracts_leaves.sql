-- =============================================================================
-- Migration 002 - RH : employés, contrats de travail, congés, documents RH
-- MySQL 8.0.16+ / MariaDB 10.11+ (InnoDB, utf8mb4)
-- =============================================================================

CREATE TABLE IF NOT EXISTS employees (
    id                     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    company_id             BIGINT UNSIGNED NOT NULL REFERENCES companies(id) ON DELETE RESTRICT,
    establishment_id       BIGINT UNSIGNED REFERENCES establishments(id) ON DELETE SET NULL,
    department_id          BIGINT UNSIGNED REFERENCES departments(id) ON DELETE SET NULL,
    manager_id             BIGINT UNSIGNED REFERENCES employees(id) ON DELETE SET NULL,
    employee_number        VARCHAR(30) NOT NULL UNIQUE,
    last_name              VARCHAR(100) NOT NULL,
    first_name             VARCHAR(100) NOT NULL,
    birth_date             DATE,
    birth_place            VARCHAR(120) NOT NULL DEFAULT '',
    gender                 VARCHAR(20) NOT NULL DEFAULT 'non_renseigne',
    social_security_number VARCHAR(15) NOT NULL DEFAULT '',
    nationality            VARCHAR(60) NOT NULL DEFAULT 'Française',
    personal_email         VARCHAR(180) NOT NULL DEFAULT '',
    professional_email     VARCHAR(180) NOT NULL DEFAULT '',
    phone                  VARCHAR(40) NOT NULL DEFAULT '',
    address                VARCHAR(255) NOT NULL DEFAULT '',
    postal_code            VARCHAR(20) NOT NULL DEFAULT '',
    city                   VARCHAR(120) NOT NULL DEFAULT '',
    iban                   VARCHAR(34) NOT NULL DEFAULT '',
    bic                    VARCHAR(11) NOT NULL DEFAULT '',
    job_title              VARCHAR(120) NOT NULL DEFAULT '',
    qualification          VARCHAR(120) NOT NULL DEFAULT '',
    coefficient            VARCHAR(30) NOT NULL DEFAULT '',
    status                 VARCHAR(30) NOT NULL DEFAULT 'candidate',
    hire_date              DATE,
    termination_date       DATE,
    termination_reason     VARCHAR(120) NOT NULL DEFAULT '',
    is_portal_enabled      TINYINT(1) NOT NULL DEFAULT 0,
    portal_token           VARCHAR(64),
    created_at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT employees_gender_check CHECK (gender IN ('m','f','non_renseigne')),
    CONSTRAINT employees_status_check CHECK (status IN ('candidate','active','on_leave','suspended','terminated'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX idx_employees_company ON employees(company_id);
CREATE INDEX idx_employees_status ON employees(status);

CREATE TABLE IF NOT EXISTS employment_contracts (
    id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    employee_id        BIGINT UNSIGNED NOT NULL REFERENCES employees(id) ON DELETE CASCADE,
    type               VARCHAR(30) NOT NULL,
    start_date         DATE NOT NULL,
    end_date           DATE,
    trial_period_end   DATE,
    weekly_hours       NUMERIC(5,2) NOT NULL DEFAULT 35.00,
    is_part_time       TINYINT(1) NOT NULL DEFAULT 0,
    part_time_pct      NUMERIC(5,2) NOT NULL DEFAULT 100.00,
    position           VARCHAR(120) NOT NULL DEFAULT '',
    classification     VARCHAR(120) NOT NULL DEFAULT '',
    coefficient        VARCHAR(30) NOT NULL DEFAULT '',
    hourly_rate        NUMERIC(12,4) NOT NULL DEFAULT 0,
    monthly_gross      NUMERIC(12,2) NOT NULL DEFAULT 0,
    qualification      VARCHAR(120) NOT NULL DEFAULT '',
    status             VARCHAR(20) NOT NULL DEFAULT 'draft',
    signed_at          DATE,
    document_path      VARCHAR(255),
    created_by         BIGINT UNSIGNED REFERENCES users(id) ON DELETE SET NULL,
    created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT contracts_type_check CHECK (type IN ('CDI','CDD','apprentissage','professionnalisation','stage','alternance','interim')),
    CONSTRAINT contracts_status_check CHECK (status IN ('draft','active','suspended','ended'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX idx_contracts_employee ON employment_contracts(employee_id);
CREATE INDEX idx_contracts_status ON employment_contracts(status);

CREATE TABLE IF NOT EXISTS leave_types (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    code         VARCHAR(30) NOT NULL UNIQUE,
    label        VARCHAR(120) NOT NULL,
    is_paid      TINYINT(1) NOT NULL DEFAULT 1,
    color        VARCHAR(10) NOT NULL DEFAULT '#0d6efd',
    default_days NUMERIC(6,2) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS leave_balances (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    employee_id    BIGINT UNSIGNED NOT NULL REFERENCES employees(id) ON DELETE CASCADE,
    leave_type_id  BIGINT UNSIGNED NOT NULL REFERENCES leave_types(id) ON DELETE CASCADE,
    period_year    INTEGER NOT NULL,
    initial_days   NUMERIC(6,2) NOT NULL DEFAULT 0,
    acquired_days  NUMERIC(6,2) NOT NULL DEFAULT 0,
    taken_days     NUMERIC(6,2) NOT NULL DEFAULT 0,
    remaining_days NUMERIC(6,2) NOT NULL DEFAULT 0,
    UNIQUE (employee_id, leave_type_id, period_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS leave_requests (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    employee_id   BIGINT UNSIGNED NOT NULL REFERENCES employees(id) ON DELETE CASCADE,
    leave_type_id BIGINT UNSIGNED NOT NULL REFERENCES leave_types(id) ON DELETE RESTRICT,
    starts_on     DATE NOT NULL,
    ends_on       DATE NOT NULL,
    days_count    NUMERIC(6,2) NOT NULL,
    reason        VARCHAR(1000) NOT NULL DEFAULT '',
    status        VARCHAR(20) NOT NULL DEFAULT 'draft',
    reviewed_by   BIGINT UNSIGNED REFERENCES users(id) ON DELETE SET NULL,
    reviewed_at   DATETIME,
    review_note   VARCHAR(1000) NOT NULL DEFAULT '',
    created_by    BIGINT UNSIGNED REFERENCES users(id) ON DELETE SET NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT leave_requests_status_check CHECK (status IN ('draft','submitted','approved','rejected','cancelled'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX idx_leave_requests_employee ON leave_requests(employee_id);
CREATE INDEX idx_leave_requests_status ON leave_requests(status);

CREATE TABLE IF NOT EXISTS employee_documents (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    employee_id BIGINT UNSIGNED NOT NULL REFERENCES employees(id) ON DELETE CASCADE,
    name        VARCHAR(180) NOT NULL,
    category    VARCHAR(60) NOT NULL DEFAULT 'autre',
    path        VARCHAR(255) NOT NULL,
    mime        VARCHAR(100) NOT NULL DEFAULT '',
    size        BIGINT NOT NULL DEFAULT 0,
    uploaded_by BIGINT UNSIGNED REFERENCES users(id) ON DELETE SET NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX idx_employee_documents_emp ON employee_documents(employee_id);