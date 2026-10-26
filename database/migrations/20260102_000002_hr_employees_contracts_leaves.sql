-- =============================================================================
-- Migration 002 - RH : employés, contrats de travail, congés, documents RH
-- =============================================================================

CREATE TABLE IF NOT EXISTS employees (
    id                     BIGSERIAL PRIMARY KEY,
    company_id             BIGINT NOT NULL REFERENCES companies(id) ON DELETE RESTRICT,
    establishment_id       BIGINT REFERENCES establishments(id) ON DELETE SET NULL,
    department_id          BIGINT REFERENCES departments(id) ON DELETE SET NULL,
    manager_id             BIGINT REFERENCES employees(id) ON DELETE SET NULL,
    employee_number        VARCHAR(30) NOT NULL UNIQUE,
    last_name              VARCHAR(100) NOT NULL,
    first_name             VARCHAR(100) NOT NULL,
    birth_date             DATE,
    birth_place            VARCHAR(120) NOT NULL DEFAULT '',
    gender                 VARCHAR(10) NOT NULL DEFAULT 'non_renseigne'
                               CHECK (gender IN ('m','f','non_renseigne')),
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
    status                 VARCHAR(30) NOT NULL DEFAULT 'candidate'
                               CHECK (status IN ('candidate','active','on_leave','suspended','terminated')),
    hire_date              DATE,
    termination_date       DATE,
    termination_reason     VARCHAR(120) NOT NULL DEFAULT '',
    is_portal_enabled      BOOLEAN NOT NULL DEFAULT FALSE,
    portal_token           VARCHAR(64),
    created_at             TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at             TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_employees_company ON employees(company_id);
CREATE INDEX IF NOT EXISTS idx_employees_status ON employees(status);

CREATE TABLE IF NOT EXISTS employment_contracts (
    id                 BIGSERIAL PRIMARY KEY,
    employee_id        BIGINT NOT NULL REFERENCES employees(id) ON DELETE CASCADE,
    type               VARCHAR(30) NOT NULL
                           CHECK (type IN ('CDI','CDD','apprentissage','professionnalisation','stage','alternance','interim')),
    start_date         DATE NOT NULL,
    end_date           DATE,
    trial_period_end   DATE,
    weekly_hours       NUMERIC(5,2) NOT NULL DEFAULT 35.00,
    is_part_time       BOOLEAN NOT NULL DEFAULT FALSE,
    part_time_pct      NUMERIC(5,2) NOT NULL DEFAULT 100.00,
    position           VARCHAR(120) NOT NULL DEFAULT '',
    classification     VARCHAR(120) NOT NULL DEFAULT '',
    coefficient        VARCHAR(30) NOT NULL DEFAULT '',
    hourly_rate        NUMERIC(12,4) NOT NULL DEFAULT 0,
    monthly_gross      NUMERIC(12,2) NOT NULL DEFAULT 0,
    qualification      VARCHAR(120) NOT NULL DEFAULT '',
    status             VARCHAR(20) NOT NULL DEFAULT 'draft'
                           CHECK (status IN ('draft','active','suspended','ended')),
    signed_at          DATE,
    document_path      VARCHAR(255),
    created_by         BIGINT REFERENCES users(id) ON DELETE SET NULL,
    created_at         TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at         TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_contracts_employee ON employment_contracts(employee_id);
CREATE INDEX IF NOT EXISTS idx_contracts_status ON employment_contracts(status);

CREATE TABLE IF NOT EXISTS leave_types (
    id           BIGSERIAL PRIMARY KEY,
    code         VARCHAR(30) NOT NULL UNIQUE,
    label        VARCHAR(120) NOT NULL,
    is_paid      BOOLEAN NOT NULL DEFAULT TRUE,
    color        VARCHAR(10) NOT NULL DEFAULT '#0d6efd',
    default_days NUMERIC(6,2) NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS leave_balances (
    id             BIGSERIAL PRIMARY KEY,
    employee_id    BIGINT NOT NULL REFERENCES employees(id) ON DELETE CASCADE,
    leave_type_id  BIGINT NOT NULL REFERENCES leave_types(id) ON DELETE CASCADE,
    period_year    INTEGER NOT NULL,
    initial_days   NUMERIC(6,2) NOT NULL DEFAULT 0,
    acquired_days  NUMERIC(6,2) NOT NULL DEFAULT 0,
    taken_days     NUMERIC(6,2) NOT NULL DEFAULT 0,
    remaining_days NUMERIC(6,2) NOT NULL DEFAULT 0,
    UNIQUE (employee_id, leave_type_id, period_year)
);

CREATE TABLE IF NOT EXISTS leave_requests (
    id            BIGSERIAL PRIMARY KEY,
    employee_id   BIGINT NOT NULL REFERENCES employees(id) ON DELETE CASCADE,
    leave_type_id BIGINT NOT NULL REFERENCES leave_types(id) ON DELETE RESTRICT,
    starts_on     DATE NOT NULL,
    ends_on       DATE NOT NULL,
    days_count    NUMERIC(6,2) NOT NULL,
    reason        TEXT NOT NULL DEFAULT '',
    status        VARCHAR(20) NOT NULL DEFAULT 'draft'
                      CHECK (status IN ('draft','submitted','approved','rejected','cancelled')),
    reviewed_by   BIGINT REFERENCES users(id) ON DELETE SET NULL,
    reviewed_at   TIMESTAMPTZ,
    review_note   TEXT NOT NULL DEFAULT '',
    created_by    BIGINT REFERENCES users(id) ON DELETE SET NULL,
    created_at    TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at    TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_leave_requests_employee ON leave_requests(employee_id);
CREATE INDEX IF NOT EXISTS idx_leave_requests_status ON leave_requests(status);

CREATE TABLE IF NOT EXISTS employee_documents (
    id          BIGSERIAL PRIMARY KEY,
    employee_id BIGINT NOT NULL REFERENCES employees(id) ON DELETE CASCADE,
    name        VARCHAR(180) NOT NULL,
    category    VARCHAR(60) NOT NULL DEFAULT 'autre',
    path        VARCHAR(255) NOT NULL,
    mime        VARCHAR(100) NOT NULL DEFAULT '',
    size        BIGINT NOT NULL DEFAULT 0,
    uploaded_by BIGINT REFERENCES users(id) ON DELETE SET NULL,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_employee_documents_emp ON employee_documents(employee_id);
