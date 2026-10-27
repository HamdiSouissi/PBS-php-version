-- =============================================================================
-- Schéma SQLite de TEST, miroir des migrations MySQL (database/migrations).
-- Utilisé par Tests\Core\DatabaseTestCase quand DB_CONNECTION=sqlite (aucun
-- serveur MySQL requis pour la suite automatisée). Les types sont volontairement
-- souples (SQLite est dynamiquement typé) ; la structure (tables, colonnes,
-- contraintes, vues) reste strictement identique à la cible MySQL 8.
-- =============================================================================

PRAGMA foreign_keys = ON;

-- ---- 001 : noyau RBAC / organisation ---------------------------------------
CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    first_name TEXT NOT NULL DEFAULT '',
    last_name TEXT NOT NULL DEFAULT '',
    is_active INTEGER NOT NULL DEFAULT 1,
    totp_secret TEXT,
    totp_enrolled INTEGER NOT NULL DEFAULT 0,
    totp_confirmed_at TEXT,
    failed_attempts INTEGER NOT NULL DEFAULT 0,
    locked_until TEXT,
    last_login_at TEXT,
    must_change_password INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE roles (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code TEXT NOT NULL UNIQUE,
    label TEXT NOT NULL,
    description TEXT NOT NULL DEFAULT ''
);

CREATE TABLE permissions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code TEXT NOT NULL UNIQUE,
    label TEXT NOT NULL,
    module TEXT NOT NULL
);

CREATE TABLE role_user (
    role_id INTEGER NOT NULL REFERENCES roles(id) ON DELETE CASCADE,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    PRIMARY KEY (role_id, user_id)
);

CREATE TABLE role_permission (
    role_id INTEGER NOT NULL REFERENCES roles(id) ON DELETE CASCADE,
    permission_id INTEGER NOT NULL REFERENCES permissions(id) ON DELETE CASCADE,
    PRIMARY KEY (role_id, permission_id)
);

CREATE TABLE password_resets (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token_hash TEXT NOT NULL,
    expires_at TEXT NOT NULL,
    used_at TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_password_resets_user ON password_resets(user_id);

CREATE TABLE remember_tokens (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token_hash TEXT NOT NULL,
    expires_at TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_remember_tokens_user ON remember_tokens(user_id);

CREATE TABLE auth_login_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    session_id TEXT NOT NULL DEFAULT '',
    ip_address TEXT NOT NULL DEFAULT '',
    user_agent TEXT NOT NULL DEFAULT '',
    remember_me INTEGER NOT NULL DEFAULT 0,
    logout_at TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_login_logs_user ON auth_login_logs(user_id, created_at);

CREATE TABLE audit_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
    action TEXT NOT NULL,
    entity_type TEXT NOT NULL,
    entity_id TEXT,
    changes TEXT NOT NULL,
    ip_address TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_audit_entity ON audit_logs(entity_type, entity_id);
CREATE INDEX idx_audit_user ON audit_logs(user_id, created_at);

CREATE TABLE settings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    group_name TEXT NOT NULL,
    key_name TEXT NOT NULL,
    value TEXT NOT NULL,
    is_encrypted INTEGER NOT NULL DEFAULT 0,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (group_name, key_name)
);

CREATE TABLE companies (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    legal_name TEXT NOT NULL,
    commercial_name TEXT NOT NULL DEFAULT '',
    siren TEXT,
    siret TEXT,
    vat_number TEXT,
    legal_form TEXT NOT NULL DEFAULT 'SAS',
    address TEXT NOT NULL DEFAULT '',
    postal_code TEXT NOT NULL DEFAULT '',
    city TEXT NOT NULL DEFAULT '',
    country TEXT NOT NULL DEFAULT 'France',
    phone TEXT NOT NULL DEFAULT '',
    email TEXT NOT NULL DEFAULT '',
    iban TEXT NOT NULL DEFAULT '',
    bic TEXT NOT NULL DEFAULT '',
    urssaf_number TEXT NOT NULL DEFAULT '',
    collective_agreement TEXT NOT NULL DEFAULT '',
    logo_path TEXT,
    is_default INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE establishments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    company_id INTEGER NOT NULL REFERENCES companies(id) ON DELETE CASCADE,
    name TEXT NOT NULL,
    siret TEXT,
    address TEXT NOT NULL DEFAULT '',
    postal_code TEXT NOT NULL DEFAULT '',
    city TEXT NOT NULL DEFAULT '',
    head_office INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_establishments_company ON establishments(company_id);

CREATE TABLE departments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    company_id INTEGER NOT NULL REFERENCES companies(id) ON DELETE CASCADE,
    code TEXT NOT NULL,
    name TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (company_id, code)
);

CREATE TABLE notifications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    type TEXT NOT NULL,
    title TEXT NOT NULL,
    body TEXT NOT NULL,
    read_at TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_notifications_user ON notifications(user_id, read_at);

-- ---- 002 : RH ---------------------------------------------------------------
CREATE TABLE employees (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    company_id INTEGER NOT NULL REFERENCES companies(id) ON DELETE RESTRICT,
    establishment_id INTEGER REFERENCES establishments(id) ON DELETE SET NULL,
    department_id INTEGER REFERENCES departments(id) ON DELETE SET NULL,
    manager_id INTEGER REFERENCES employees(id) ON DELETE SET NULL,
    employee_number TEXT NOT NULL UNIQUE,
    last_name TEXT NOT NULL,
    first_name TEXT NOT NULL,
    birth_date TEXT,
    birth_place TEXT NOT NULL DEFAULT '',
    gender TEXT NOT NULL DEFAULT 'non_renseigne' CHECK (gender IN ('m','f','non_renseigne')),
    social_security_number TEXT NOT NULL DEFAULT '',
    nationality TEXT NOT NULL DEFAULT 'Française',
    personal_email TEXT NOT NULL DEFAULT '',
    professional_email TEXT NOT NULL DEFAULT '',
    phone TEXT NOT NULL DEFAULT '',
    address TEXT NOT NULL DEFAULT '',
    postal_code TEXT NOT NULL DEFAULT '',
    city TEXT NOT NULL DEFAULT '',
    iban TEXT NOT NULL DEFAULT '',
    bic TEXT NOT NULL DEFAULT '',
    job_title TEXT NOT NULL DEFAULT '',
    qualification TEXT NOT NULL DEFAULT '',
    coefficient TEXT NOT NULL DEFAULT '',
    status TEXT NOT NULL DEFAULT 'candidate'
        CHECK (status IN ('candidate','active','on_leave','suspended','terminated')),
    hire_date TEXT,
    termination_date TEXT,
    termination_reason TEXT NOT NULL DEFAULT '',
    is_portal_enabled INTEGER NOT NULL DEFAULT 0,
    portal_token TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_employees_company ON employees(company_id);
CREATE INDEX idx_employees_status ON employees(status);

CREATE TABLE employment_contracts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    employee_id INTEGER NOT NULL REFERENCES employees(id) ON DELETE CASCADE,
    type TEXT NOT NULL CHECK (type IN ('CDI','CDD','apprentissage','professionnalisation','stage','alternance','interim')),
    start_date TEXT NOT NULL,
    end_date TEXT,
    trial_period_end TEXT,
    weekly_hours NUMERIC NOT NULL DEFAULT 35.00,
    is_part_time INTEGER NOT NULL DEFAULT 0,
    part_time_pct NUMERIC NOT NULL DEFAULT 100.00,
    position TEXT NOT NULL DEFAULT '',
    classification TEXT NOT NULL DEFAULT '',
    coefficient TEXT NOT NULL DEFAULT '',
    hourly_rate NUMERIC NOT NULL DEFAULT 0,
    monthly_gross NUMERIC NOT NULL DEFAULT 0,
    qualification TEXT NOT NULL DEFAULT '',
    status TEXT NOT NULL DEFAULT 'draft' CHECK (status IN ('draft','active','suspended','ended')),
    signed_at TEXT,
    document_path TEXT,
    created_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_contracts_employee ON employment_contracts(employee_id);
CREATE INDEX idx_contracts_status ON employment_contracts(status);

CREATE TABLE leave_types (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code TEXT NOT NULL UNIQUE,
    label TEXT NOT NULL,
    is_paid INTEGER NOT NULL DEFAULT 1,
    color TEXT NOT NULL DEFAULT '#0d6efd',
    default_days NUMERIC NOT NULL DEFAULT 0
);

CREATE TABLE leave_balances (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    employee_id INTEGER NOT NULL REFERENCES employees(id) ON DELETE CASCADE,
    leave_type_id INTEGER NOT NULL REFERENCES leave_types(id) ON DELETE CASCADE,
    period_year INTEGER NOT NULL,
    initial_days NUMERIC NOT NULL DEFAULT 0,
    acquired_days NUMERIC NOT NULL DEFAULT 0,
    taken_days NUMERIC NOT NULL DEFAULT 0,
    remaining_days NUMERIC NOT NULL DEFAULT 0,
    UNIQUE (employee_id, leave_type_id, period_year)
);

CREATE TABLE leave_requests (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    employee_id INTEGER NOT NULL REFERENCES employees(id) ON DELETE CASCADE,
    leave_type_id INTEGER NOT NULL REFERENCES leave_types(id) ON DELETE RESTRICT,
    starts_on TEXT NOT NULL,
    ends_on TEXT NOT NULL,
    days_count NUMERIC NOT NULL,
    reason TEXT NOT NULL DEFAULT '',
    status TEXT NOT NULL DEFAULT 'draft'
        CHECK (status IN ('draft','submitted','approved','rejected','cancelled')),
    reviewed_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    reviewed_at TEXT,
    review_note TEXT NOT NULL DEFAULT '',
    created_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_leave_requests_employee ON leave_requests(employee_id);
CREATE INDEX idx_leave_requests_status ON leave_requests(status);

CREATE TABLE employee_documents (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    employee_id INTEGER NOT NULL REFERENCES employees(id) ON DELETE CASCADE,
    name TEXT NOT NULL,
    category TEXT NOT NULL DEFAULT 'autre',
    path TEXT NOT NULL,
    mime TEXT NOT NULL DEFAULT '',
    size INTEGER NOT NULL DEFAULT 0,
    uploaded_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_employee_documents_emp ON employee_documents(employee_id);

-- ---- 003 : paie -------------------------------------------------------------
CREATE TABLE payroll_rule_versions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code TEXT NOT NULL,
    label TEXT NOT NULL,
    category TEXT NOT NULL
        CHECK (category IN ('sante','vieillesse','retraite_complementaire',
                            'chomage','famille','accident_travail','csg_crds',
                            'autres','cotisation_statutaire','reductions')),
    base_type TEXT NOT NULL DEFAULT 'brut'
        CHECK (base_type IN ('brut','plafond','brut_et_plafond','csg','forfait')),
    rate_employee NUMERIC NOT NULL DEFAULT 0,
    rate_employer NUMERIC NOT NULL DEFAULT 0,
    ceiling_month NUMERIC,
    is_deductible INTEGER NOT NULL DEFAULT 0,
    is_taxable INTEGER NOT NULL DEFAULT 0,
    effective_from TEXT NOT NULL,
    effective_to TEXT,
    source TEXT NOT NULL DEFAULT 'URSSAF',
    notes TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_payroll_rules_code ON payroll_rule_versions(code, effective_from);

CREATE TABLE payroll_periods (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    company_id INTEGER NOT NULL REFERENCES companies(id) ON DELETE CASCADE,
    year INTEGER NOT NULL,
    month INTEGER NOT NULL CHECK (month BETWEEN 1 AND 12),
    starts_on TEXT NOT NULL,
    ends_on TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'open' CHECK (status IN ('open','closed')),
    is_locked INTEGER NOT NULL DEFAULT 0,
    closed_at TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (company_id, year, month)
);

CREATE TABLE payslips (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    period_id INTEGER NOT NULL REFERENCES payroll_periods(id) ON DELETE CASCADE,
    employee_id INTEGER NOT NULL REFERENCES employees(id) ON DELETE CASCADE,
    contract_id INTEGER REFERENCES employment_contracts(id) ON DELETE SET NULL,
    status TEXT NOT NULL DEFAULT 'draft' CHECK (status IN ('draft','validated','locked')),
    hours_worked NUMERIC NOT NULL DEFAULT 151.67,
    gross_salary NUMERIC NOT NULL DEFAULT 0,
    total_employee NUMERIC NOT NULL DEFAULT 0,
    total_employer NUMERIC NOT NULL DEFAULT 0,
    net_social NUMERIC NOT NULL DEFAULT 0,
    net_taxable NUMERIC NOT NULL DEFAULT 0,
    net_payable NUMERIC NOT NULL DEFAULT 0,
    general_reduction NUMERIC NOT NULL DEFAULT 0,
    is_paid INTEGER NOT NULL DEFAULT 0,
    payment_date TEXT,
    document_path TEXT,
    generated_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    validated_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    validated_at TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (period_id, employee_id)
);
CREATE INDEX idx_payslips_employee ON payslips(employee_id);
CREATE INDEX idx_payslips_status ON payslips(status);

CREATE TABLE payslip_lines (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    payslip_id INTEGER NOT NULL REFERENCES payslips(id) ON DELETE CASCADE,
    rule_code TEXT NOT NULL,
    label TEXT NOT NULL,
    base_amount NUMERIC NOT NULL DEFAULT 0,
    rate_employee NUMERIC NOT NULL DEFAULT 0,
    rate_employer NUMERIC NOT NULL DEFAULT 0,
    amount_employee NUMERIC NOT NULL DEFAULT 0,
    amount_employer NUMERIC NOT NULL DEFAULT 0,
    sort_order INTEGER NOT NULL DEFAULT 0
);
CREATE INDEX idx_payslip_lines_payslip ON payslip_lines(payslip_id);

CREATE TABLE dsn_preparations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    company_id INTEGER NOT NULL REFERENCES companies(id) ON DELETE CASCADE,
    period_id INTEGER REFERENCES payroll_periods(id) ON DELETE SET NULL,
    status TEXT NOT NULL DEFAULT 'prepared'
        CHECK (status IN ('prepared','controlled','ready','exported')),
    file_path TEXT,
    declared_at TEXT,
    notes TEXT NOT NULL DEFAULT '',
    created_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ---- 004 : comptabilité -----------------------------------------------------
CREATE TABLE fiscal_years (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    label TEXT NOT NULL,
    starts_on TEXT NOT NULL,
    ends_on TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'open' CHECK (status IN ('open','closed')),
    closed_at TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_fiscal_dates CHECK (ends_on > starts_on)
);

CREATE TABLE chart_accounts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code TEXT NOT NULL UNIQUE,
    label TEXT NOT NULL,
    account_class INTEGER NOT NULL CHECK (account_class BETWEEN 1 AND 8),
    parent_code TEXT REFERENCES chart_accounts(code),
    account_type TEXT NOT NULL
        CHECK (account_type IN ('asset','liability','equity','income','expense','neutral')),
    is_auxiliary INTEGER NOT NULL DEFAULT 0,
    allow_entries INTEGER NOT NULL DEFAULT 1,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_chart_class ON chart_accounts(account_class);

CREATE TABLE journals (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code TEXT NOT NULL UNIQUE,
    label TEXT NOT NULL,
    type TEXT NOT NULL DEFAULT 'od'
        CHECK (type IN ('sales','purchases','bank','cash','od','closing'))
);

CREATE TABLE journal_entries (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    journal_id INTEGER NOT NULL REFERENCES journals(id) ON DELETE RESTRICT,
    fiscal_year_id INTEGER NOT NULL REFERENCES fiscal_years(id) ON DELETE RESTRICT,
    entry_date TEXT NOT NULL,
    reference TEXT NOT NULL DEFAULT '',
    label TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'draft' CHECK (status IN ('draft','posted','cancelled')),
    reversed_entry_id INTEGER REFERENCES journal_entries(id) ON DELETE SET NULL,
    source_type TEXT NOT NULL DEFAULT 'manual',
    source_id INTEGER,
    created_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    posted_at TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_entries_date ON journal_entries(entry_date);
CREATE INDEX idx_entries_fiscal ON journal_entries(fiscal_year_id);

CREATE TABLE journal_entry_lines (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    entry_id INTEGER NOT NULL REFERENCES journal_entries(id) ON DELETE CASCADE,
    account_code TEXT NOT NULL REFERENCES chart_accounts(code),
    label TEXT NOT NULL DEFAULT '',
    debit NUMERIC NOT NULL DEFAULT 0 CHECK (debit >= 0),
    credit NUMERIC NOT NULL DEFAULT 0 CHECK (credit >= 0),
    third_party TEXT NOT NULL DEFAULT '',
    CONSTRAINT chk_line_side CHECK (NOT (debit > 0 AND credit > 0))
);
CREATE INDEX idx_entry_lines_account ON journal_entry_lines(account_code);
CREATE INDEX idx_entry_lines_entry ON journal_entry_lines(entry_id);

CREATE TABLE vat_declarations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    period_label TEXT NOT NULL,
    starts_on TEXT NOT NULL,
    ends_on TEXT NOT NULL,
    collected_vat NUMERIC NOT NULL DEFAULT 0,
    deductible_vat NUMERIC NOT NULL DEFAULT 0,
    due_vat NUMERIC NOT NULL DEFAULT 0,
    status TEXT NOT NULL DEFAULT 'draft'
        CHECK (status IN ('draft','submitted','paid','cancelled')),
    file_path TEXT,
    submitted_at TEXT,
    created_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE fixed_assets (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code TEXT NOT NULL UNIQUE,
    label TEXT NOT NULL,
    account_code TEXT NOT NULL REFERENCES chart_accounts(code),
    acquisition_date TEXT NOT NULL,
    acquisition_value NUMERIC NOT NULL CHECK (acquisition_value > 0),
    useful_life_years INTEGER NOT NULL CHECK (useful_life_years > 0),
    residual_value NUMERIC NOT NULL DEFAULT 0,
    depreciation_mode TEXT NOT NULL DEFAULT 'linear' CHECK (depreciation_mode IN ('linear','none')),
    status TEXT NOT NULL DEFAULT 'active' CHECK (status IN ('active','disposed')),
    journal_entry_id INTEGER REFERENCES journal_entries(id) ON DELETE SET NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE asset_depreciation_schedules (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    asset_id INTEGER NOT NULL REFERENCES fixed_assets(id) ON DELETE CASCADE,
    fiscal_year_id INTEGER NOT NULL REFERENCES fiscal_years(id) ON DELETE CASCADE,
    annuity NUMERIC NOT NULL DEFAULT 0,
    cumulative NUMERIC NOT NULL DEFAULT 0,
    net_book_value NUMERIC NOT NULL DEFAULT 0,
    UNIQUE (asset_id, fiscal_year_id)
);

CREATE TABLE budgets (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    fiscal_year_id INTEGER NOT NULL REFERENCES fiscal_years(id) ON DELETE CASCADE,
    account_code TEXT NOT NULL REFERENCES chart_accounts(code),
    period_year INTEGER NOT NULL,
    period_month INTEGER NOT NULL CHECK (period_month BETWEEN 1 AND 12),
    category TEXT NOT NULL DEFAULT '',
    planned_amount NUMERIC NOT NULL DEFAULT 0,
    actual_amount NUMERIC NOT NULL DEFAULT 0,
    UNIQUE (fiscal_year_id, account_code, period_month)
);

-- ---- 005 : trésorerie -------------------------------------------------------
CREATE TABLE bank_accounts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    company_id INTEGER NOT NULL REFERENCES companies(id) ON DELETE CASCADE,
    label TEXT NOT NULL,
    bank_name TEXT NOT NULL DEFAULT '',
    iban TEXT NOT NULL DEFAULT '',
    bic TEXT NOT NULL DEFAULT '',
    currency TEXT NOT NULL DEFAULT 'EUR',
    initial_balance NUMERIC NOT NULL DEFAULT 0,
    current_balance NUMERIC NOT NULL DEFAULT 0,
    is_active INTEGER NOT NULL DEFAULT 1,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_bank_accounts_company ON bank_accounts(company_id);

CREATE TABLE bank_transactions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    bank_account_id INTEGER NOT NULL REFERENCES bank_accounts(id) ON DELETE CASCADE,
    transaction_date TEXT NOT NULL,
    value_date TEXT,
    label TEXT NOT NULL,
    amount NUMERIC NOT NULL,
    direction TEXT NOT NULL CHECK (direction IN ('credit','debit')),
    reference TEXT NOT NULL DEFAULT '',
    is_reconciled INTEGER NOT NULL DEFAULT 0,
    reconciled_at TEXT,
    source_type TEXT NOT NULL DEFAULT 'manual',
    source_id INTEGER,
    category TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_bank_tx_account ON bank_transactions(bank_account_id, transaction_date);
CREATE INDEX idx_bank_tx_reconciled ON bank_transactions(is_reconciled);

CREATE TABLE cash_forecasts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    forecast_date TEXT NOT NULL,
    label TEXT NOT NULL,
    amount_in NUMERIC NOT NULL DEFAULT 0,
    amount_out NUMERIC NOT NULL DEFAULT 0,
    source TEXT NOT NULL DEFAULT 'manual',
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_cash_forecast_date ON cash_forecasts(forecast_date);

-- ---- 006 : facturation ------------------------------------------------------
CREATE TABLE third_parties (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    type TEXT NOT NULL CHECK (type IN ('client','supplier','both')),
    code TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    legal_form TEXT NOT NULL DEFAULT '',
    siren TEXT NOT NULL DEFAULT '',
    siret TEXT NOT NULL DEFAULT '',
    vat_number TEXT NOT NULL DEFAULT '',
    address TEXT NOT NULL DEFAULT '',
    postal_code TEXT NOT NULL DEFAULT '',
    city TEXT NOT NULL DEFAULT '',
    country TEXT NOT NULL DEFAULT 'France',
    email TEXT NOT NULL DEFAULT '',
    phone TEXT NOT NULL DEFAULT '',
    iban TEXT NOT NULL DEFAULT '',
    bic TEXT NOT NULL DEFAULT '',
    payment_terms_days INTEGER NOT NULL DEFAULT 30,
    payment_method TEXT NOT NULL DEFAULT 'virement',
    notes TEXT NOT NULL DEFAULT '',
    is_active INTEGER NOT NULL DEFAULT 1,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_third_parties_type ON third_parties(type);

CREATE TABLE invoices (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    company_id INTEGER NOT NULL REFERENCES companies(id) ON DELETE RESTRICT,
    third_party_id INTEGER NOT NULL REFERENCES third_parties(id) ON DELETE RESTRICT,
    invoice_number TEXT NOT NULL UNIQUE,
    type TEXT NOT NULL CHECK (type IN ('sale','purchase')),
    status TEXT NOT NULL DEFAULT 'draft'
        CHECK (status IN ('draft','issued','partial','paid','overdue','cancelled')),
    issue_date TEXT NOT NULL,
    due_date TEXT NOT NULL,
    currency TEXT NOT NULL DEFAULT 'EUR',
    total_ht NUMERIC NOT NULL DEFAULT 0,
    total_vat NUMERIC NOT NULL DEFAULT 0,
    total_ttc NUMERIC NOT NULL DEFAULT 0,
    amount_paid NUMERIC NOT NULL DEFAULT 0,
    balance NUMERIC NOT NULL DEFAULT 0,
    electronic_status TEXT NOT NULL DEFAULT 'none'
        CHECK (electronic_status IN ('none','generated','submitted','acknowledged','rejected')),
    electronic_reference TEXT NOT NULL DEFAULT '',
    notes TEXT NOT NULL DEFAULT '',
    created_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_invoices_party ON invoices(third_party_id);
CREATE INDEX idx_invoices_status ON invoices(status, due_date);
CREATE INDEX idx_invoices_type ON invoices(type);

CREATE TABLE invoice_lines (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    invoice_id INTEGER NOT NULL REFERENCES invoices(id) ON DELETE CASCADE,
    label TEXT NOT NULL,
    quantity NUMERIC NOT NULL DEFAULT 1,
    unit_price NUMERIC NOT NULL DEFAULT 0,
    vat_rate NUMERIC NOT NULL DEFAULT 20.000,
    discount_pct NUMERIC NOT NULL DEFAULT 0,
    line_ht NUMERIC NOT NULL DEFAULT 0,
    line_vat NUMERIC NOT NULL DEFAULT 0,
    line_ttc NUMERIC NOT NULL DEFAULT 0,
    account_code TEXT NOT NULL DEFAULT '706000',
    sort_order INTEGER NOT NULL DEFAULT 0
);
CREATE INDEX idx_invoice_lines_invoice ON invoice_lines(invoice_id);

CREATE TABLE payments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    invoice_id INTEGER NOT NULL REFERENCES invoices(id) ON DELETE CASCADE,
    bank_account_id INTEGER REFERENCES bank_accounts(id) ON DELETE SET NULL,
    paid_on TEXT NOT NULL,
    amount NUMERIC NOT NULL CHECK (amount > 0),
    method TEXT NOT NULL DEFAULT 'virement',
    reference TEXT NOT NULL DEFAULT '',
    recorded_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_payments_invoice ON payments(invoice_id);

-- ---- 007 : contrats / abonnements -------------------------------------------
CREATE TABLE client_contracts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    third_party_id INTEGER NOT NULL REFERENCES third_parties(id) ON DELETE CASCADE,
    contract_number TEXT NOT NULL UNIQUE,
    title TEXT NOT NULL,
    starts_on TEXT NOT NULL,
    ends_on TEXT,
    renewal_mode TEXT NOT NULL DEFAULT 'tacit'
        CHECK (renewal_mode IN ('none','tacit','express')),
    notice_days INTEGER NOT NULL DEFAULT 30,
    amount_ht NUMERIC NOT NULL DEFAULT 0,
    billing_cycle TEXT NOT NULL DEFAULT 'monthly'
        CHECK (billing_cycle IN ('monthly','quarterly','half_yearly','yearly','one_shot')),
    status TEXT NOT NULL DEFAULT 'draft'
        CHECK (status IN ('draft','active','terminated','expired')),
    signed_on TEXT,
    notes TEXT NOT NULL DEFAULT '',
    created_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_client_contracts_party ON client_contracts(third_party_id);
CREATE INDEX idx_client_contracts_status ON client_contracts(status);

CREATE TABLE subscription_plans (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code TEXT NOT NULL UNIQUE,
    label TEXT NOT NULL,
    description TEXT NOT NULL DEFAULT '',
    amount_ht NUMERIC NOT NULL DEFAULT 0,
    vat_rate NUMERIC NOT NULL DEFAULT 20.000,
    billing_cycle TEXT NOT NULL DEFAULT 'monthly'
        CHECK (billing_cycle IN ('monthly','quarterly','half_yearly','yearly')),
    is_active INTEGER NOT NULL DEFAULT 1,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE subscriptions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    third_party_id INTEGER NOT NULL REFERENCES third_parties(id) ON DELETE CASCADE,
    plan_id INTEGER NOT NULL REFERENCES subscription_plans(id) ON DELETE RESTRICT,
    client_contract_id INTEGER REFERENCES client_contracts(id) ON DELETE SET NULL,
    starts_on TEXT NOT NULL,
    ends_on TEXT,
    status TEXT NOT NULL DEFAULT 'active' CHECK (status IN ('active','paused','cancelled')),
    quantity NUMERIC NOT NULL DEFAULT 1,
    unit_amount_ht NUMERIC NOT NULL DEFAULT 0,
    vat_rate NUMERIC NOT NULL DEFAULT 20.000,
    auto_invoice INTEGER NOT NULL DEFAULT 1,
    next_invoice_date TEXT NOT NULL,
    last_invoice_date TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_subscriptions_status ON subscriptions(status, next_invoice_date);

CREATE TABLE subscription_invoice_runs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    run_date TEXT NOT NULL,
    generated_count INTEGER NOT NULL DEFAULT 0,
    total_ttc NUMERIC NOT NULL DEFAULT 0,
    details TEXT NOT NULL,
    created_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE revenue_snapshots (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    snapshot_on TEXT NOT NULL UNIQUE,
    mrr NUMERIC NOT NULL DEFAULT 0,
    arr NUMERIC NOT NULL DEFAULT 0,
    active_subscriptions INTEGER NOT NULL DEFAULT 0,
    churned INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ---- 008 : frais / dividendes -----------------------------------------------
CREATE TABLE expense_reports (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    employee_id INTEGER NOT NULL REFERENCES employees(id) ON DELETE CASCADE,
    title TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'draft'
        CHECK (status IN ('draft','submitted','approved','rejected','paid')),
    total_amount NUMERIC NOT NULL DEFAULT 0,
    submitted_at TEXT,
    approved_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    approved_at TEXT,
    paid_on TEXT,
    notes TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_expense_reports_employee ON expense_reports(employee_id);
CREATE INDEX idx_expense_reports_status ON expense_reports(status);

CREATE TABLE expense_lines (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    report_id INTEGER NOT NULL REFERENCES expense_reports(id) ON DELETE CASCADE,
    spent_on TEXT NOT NULL,
    category TEXT NOT NULL
        CHECK (category IN ('transport','meal','accommodation','fuel','kilometric','supplies','other')),
    label TEXT NOT NULL,
    amount NUMERIC NOT NULL DEFAULT 0,
    vat_amount NUMERIC NOT NULL DEFAULT 0,
    distance_km NUMERIC NOT NULL DEFAULT 0,
    km_rate NUMERIC NOT NULL DEFAULT 0,
    receipt_path TEXT NOT NULL DEFAULT '',
    ocr_data TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_expense_lines_report ON expense_lines(report_id);

CREATE TABLE shareholders (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    company_id INTEGER NOT NULL REFERENCES companies(id) ON DELETE CASCADE,
    third_party_id INTEGER REFERENCES third_parties(id) ON DELETE SET NULL,
    entity_name TEXT NOT NULL DEFAULT '',
    first_name TEXT NOT NULL DEFAULT '',
    last_name TEXT NOT NULL DEFAULT '',
    type TEXT NOT NULL CHECK (type IN ('individual','company')),
    shares_count INTEGER NOT NULL DEFAULT 0 CHECK (shares_count >= 0),
    share_pct NUMERIC NOT NULL DEFAULT 0,
    entry_date TEXT NOT NULL,
    exit_date TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_shareholders_company ON shareholders(company_id);

CREATE TABLE dividends (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    company_id INTEGER NOT NULL REFERENCES companies(id) ON DELETE CASCADE,
    shareholder_id INTEGER NOT NULL REFERENCES shareholders(id) ON DELETE CASCADE,
    fiscal_year_id INTEGER REFERENCES fiscal_years(id) ON DELETE SET NULL,
    decided_on TEXT NOT NULL,
    gross_amount NUMERIC NOT NULL CHECK (gross_amount > 0),
    flat_tax_rate NUMERIC NOT NULL DEFAULT 30.000,
    social_rate NUMERIC NOT NULL DEFAULT 17.200,
    tax_option TEXT NOT NULL DEFAULT 'pfuo' CHECK (tax_option IN ('pfuo','income_schedule')),
    social_deductions NUMERIC NOT NULL DEFAULT 0,
    net_amount NUMERIC NOT NULL DEFAULT 0,
    status TEXT NOT NULL DEFAULT 'proposed' CHECK (status IN ('proposed','decided','paid')),
    paid_on TEXT,
    notes TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_dividends_company ON dividends(company_id);

-- ---- 009 : conformité / documents / IA --------------------------------------
CREATE TABLE compliance_obligations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    code TEXT NOT NULL UNIQUE,
    title TEXT NOT NULL,
    authority TEXT NOT NULL
        CHECK (authority IN ('URSSAF','DGFIP','INSEE','greffe','medecine_travail','interne')),
    frequency TEXT NOT NULL
        CHECK (frequency IN ('monthly','quarterly','yearly','event','continuous')),
    period_label TEXT NOT NULL DEFAULT '',
    due_on TEXT,
    status TEXT NOT NULL DEFAULT 'pending'
        CHECK (status IN ('pending','done','overdue','not_applicable')),
    completed_at TEXT,
    notes TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_obligations_status ON compliance_obligations(status, due_on);

CREATE TABLE documents (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    original_name TEXT NOT NULL DEFAULT '',
    disk TEXT NOT NULL DEFAULT 'documents',
    path TEXT NOT NULL,
    mime TEXT NOT NULL DEFAULT '',
    size INTEGER NOT NULL DEFAULT 0,
    category TEXT NOT NULL DEFAULT 'autre',
    entity_type TEXT NOT NULL DEFAULT '',
    entity_id INTEGER,
    visibility TEXT NOT NULL DEFAULT 'private'
        CHECK (visibility IN ('private','company','portal')),
    checksum TEXT NOT NULL DEFAULT '',
    uploaded_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_documents_entity ON documents(entity_type, entity_id);
CREATE INDEX idx_documents_category ON documents(category);

CREATE TABLE document_versions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    document_id INTEGER NOT NULL REFERENCES documents(id) ON DELETE CASCADE,
    version INTEGER NOT NULL,
    path TEXT NOT NULL,
    size INTEGER NOT NULL DEFAULT 0,
    created_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (document_id, version)
);

CREATE TABLE ai_provider_settings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    provider TEXT NOT NULL UNIQUE CHECK (provider IN ('ollama','codecraft')),
    is_enabled INTEGER NOT NULL DEFAULT 0,
    url TEXT NOT NULL DEFAULT '',
    model TEXT NOT NULL DEFAULT '',
    api_key_encrypted TEXT NOT NULL DEFAULT '',
    temperature NUMERIC NOT NULL DEFAULT 0.70,
    timeout INTEGER NOT NULL DEFAULT 30,
    system_prompt TEXT NOT NULL DEFAULT '',
    extra TEXT NOT NULL,
    updated_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE ai_conversations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    title TEXT NOT NULL DEFAULT 'Nouvelle conversation',
    provider TEXT NOT NULL DEFAULT 'ollama',
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE ai_messages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    conversation_id INTEGER NOT NULL REFERENCES ai_conversations(id) ON DELETE CASCADE,
    role TEXT NOT NULL CHECK (role IN ('user','assistant','system')),
    content TEXT NOT NULL,
    tokens INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_ai_messages_conversation ON ai_messages(conversation_id);

-- ---- 012 : numérotation, devis, avoirs, récurrent, e-facturation ------------
CREATE TABLE document_sequences (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    kind TEXT NOT NULL,
    period TEXT NOT NULL,
    last_value INTEGER NOT NULL DEFAULT 0,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT document_sequences_unique UNIQUE (kind, period)
);

CREATE TABLE quotes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    company_id INTEGER NOT NULL REFERENCES companies(id) ON DELETE RESTRICT,
    third_party_id INTEGER NOT NULL REFERENCES third_parties(id) ON DELETE RESTRICT,
    quote_number TEXT NOT NULL UNIQUE,
    status TEXT NOT NULL DEFAULT 'draft'
        CHECK (status IN ('draft','sent','accepted','rejected','invoiced','expired')),
    issue_date TEXT NOT NULL,
    valid_until TEXT,
    currency TEXT NOT NULL DEFAULT 'EUR',
    total_ht NUMERIC NOT NULL DEFAULT 0,
    total_vat NUMERIC NOT NULL DEFAULT 0,
    total_ttc NUMERIC NOT NULL DEFAULT 0,
    notes TEXT NOT NULL DEFAULT '',
    converted_invoice_id INTEGER REFERENCES invoices(id) ON DELETE SET NULL,
    created_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_quotes_party ON quotes(third_party_id);
CREATE INDEX idx_quotes_status ON quotes(status);

CREATE TABLE quote_lines (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    quote_id INTEGER NOT NULL REFERENCES quotes(id) ON DELETE CASCADE,
    label TEXT NOT NULL,
    quantity NUMERIC NOT NULL DEFAULT 1,
    unit_price NUMERIC NOT NULL DEFAULT 0,
    vat_rate NUMERIC NOT NULL DEFAULT 20.000,
    discount_pct NUMERIC NOT NULL DEFAULT 0,
    line_ht NUMERIC NOT NULL DEFAULT 0,
    line_vat NUMERIC NOT NULL DEFAULT 0,
    line_ttc NUMERIC NOT NULL DEFAULT 0,
    sort_order INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE credit_notes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    company_id INTEGER NOT NULL REFERENCES companies(id) ON DELETE RESTRICT,
    third_party_id INTEGER NOT NULL REFERENCES third_parties(id) ON DELETE RESTRICT,
    invoice_id INTEGER REFERENCES invoices(id) ON DELETE SET NULL,
    credit_number TEXT NOT NULL UNIQUE,
    status TEXT NOT NULL DEFAULT 'draft' CHECK (status IN ('draft','issued','cancelled')),
    issue_date TEXT NOT NULL,
    currency TEXT NOT NULL DEFAULT 'EUR',
    total_ht NUMERIC NOT NULL DEFAULT 0,
    total_vat NUMERIC NOT NULL DEFAULT 0,
    total_ttc NUMERIC NOT NULL DEFAULT 0,
    reason TEXT NOT NULL DEFAULT '',
    created_by INTEGER REFERENCES users(id) ON DELETE SET NULL,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_credit_notes_invoice ON credit_notes(invoice_id);

CREATE TABLE credit_note_lines (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    credit_note_id INTEGER NOT NULL REFERENCES credit_notes(id) ON DELETE CASCADE,
    label TEXT NOT NULL,
    quantity NUMERIC NOT NULL DEFAULT 1,
    unit_price NUMERIC NOT NULL DEFAULT 0,
    vat_rate NUMERIC NOT NULL DEFAULT 20.000,
    line_ht NUMERIC NOT NULL DEFAULT 0,
    line_vat NUMERIC NOT NULL DEFAULT 0,
    line_ttc NUMERIC NOT NULL DEFAULT 0,
    sort_order INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE recurring_invoice_templates (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    company_id INTEGER NOT NULL REFERENCES companies(id) ON DELETE RESTRICT,
    third_party_id INTEGER NOT NULL REFERENCES third_parties(id) ON DELETE RESTRICT,
    label TEXT NOT NULL,
    frequency TEXT NOT NULL
        CHECK (frequency IN ('monthly','quarterly','half_yearly','yearly')),
    next_run_on TEXT NOT NULL,
    lines_payload TEXT NOT NULL,
    is_active INTEGER NOT NULL DEFAULT 1,
    last_run_on TEXT,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_recurring_next_run ON recurring_invoice_templates(next_run_on);

CREATE TABLE e_invoice_outbox (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    invoice_id INTEGER NOT NULL REFERENCES invoices(id) ON DELETE CASCADE,
    format TEXT NOT NULL CHECK (format IN ('UBL','CII')),
    platform TEXT NOT NULL DEFAULT 'PPF',
    status TEXT NOT NULL DEFAULT 'generated'
        CHECK (status IN ('generated','exported','transmission_pending','acknowledged','rejected')),
    file_path TEXT NOT NULL,
    checksum_sha256 TEXT NOT NULL DEFAULT '',
    external_reference TEXT NOT NULL DEFAULT '',
    error_message TEXT NOT NULL DEFAULT '',
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_e_invoice_outbox_status ON e_invoice_outbox(status);

-- ---- Vues métier (miroir des migrations 010 et 012) -------------------------
CREATE VIEW IF NOT EXISTS v_active_contracts AS
SELECT c.*, e.employee_number, e.last_name, e.first_name, e.company_id
FROM employment_contracts c
JOIN employees e ON e.id = c.employee_id
WHERE c.status = 'active'
  AND (c.end_date IS NULL OR c.end_date >= CURRENT_DATE);

DROP VIEW IF EXISTS v_account_balances;
CREATE VIEW v_account_balances AS
SELECT a.code,
       a.label,
       a.account_class,
       a.account_type,
       COALESCE(SUM(CASE WHEN e.status = 'posted' THEN l.debit  ELSE 0 END), 0) AS total_debit,
       COALESCE(SUM(CASE WHEN e.status = 'posted' THEN l.credit ELSE 0 END), 0) AS total_credit,
       COALESCE(SUM(CASE WHEN e.status = 'posted' THEN l.debit  ELSE 0 END), 0)
         - COALESCE(SUM(CASE WHEN e.status = 'posted' THEN l.credit ELSE 0 END), 0) AS solde_debit,
       COALESCE(SUM(CASE WHEN e.status = 'posted' THEN l.credit ELSE 0 END), 0)
         - COALESCE(SUM(CASE WHEN e.status = 'posted' THEN l.debit  ELSE 0 END), 0) AS solde_credit
FROM chart_accounts a
LEFT JOIN journal_entry_lines l ON l.account_code = a.code
LEFT JOIN journal_entries e ON e.id = l.entry_id
GROUP BY a.code, a.label, a.account_class, a.account_type;

CREATE VIEW IF NOT EXISTS v_general_ledger AS
SELECT e.entry_date,
       j.code AS journal_code,
       e.reference,
       e.label AS entry_label,
       l.account_code,
       a.label AS account_label,
       l.third_party,
       l.debit,
       l.credit
FROM journal_entries e
JOIN journals j ON j.id = e.journal_id
JOIN journal_entry_lines l ON l.entry_id = e.id
JOIN chart_accounts a ON a.code = l.account_code
WHERE e.status = 'posted';

CREATE VIEW IF NOT EXISTS v_invoice_balances AS
SELECT i.id,
       i.invoice_number,
       i.type,
       i.status,
       t.name AS third_party_name,
       i.issue_date,
       i.due_date,
       i.total_ht,
       i.total_vat,
       i.total_ttc,
       i.amount_paid,
       i.balance,
       CASE WHEN i.status IN ('issued','partial') AND i.due_date < CURRENT_DATE
            THEN 1 ELSE 0 END AS is_overdue
FROM invoices i
JOIN third_parties t ON t.id = i.third_party_id;

CREATE VIEW IF NOT EXISTS v_payslip_summary AS
SELECT p.id,
       per.year,
       per.month,
       e.employee_number,
       e.last_name,
       e.first_name,
       p.status,
       p.gross_salary,
       p.total_employee,
       p.total_employer,
       p.net_taxable,
       p.net_payable,
       p.is_paid
FROM payslips p
JOIN payroll_periods per ON per.id = p.period_id
JOIN employees e ON e.id = p.employee_id;

CREATE VIEW IF NOT EXISTS v_cash_position AS
SELECT b.id,
       b.label,
       b.iban,
       b.initial_balance,
       COALESCE(SUM(CASE WHEN t.direction = 'credit' THEN t.amount ELSE -t.amount END), 0) AS movements,
       b.initial_balance
         + COALESCE(SUM(CASE WHEN t.direction = 'credit' THEN t.amount ELSE -t.amount END), 0) AS computed_balance
FROM bank_accounts b
LEFT JOIN bank_transactions t ON t.bank_account_id = b.id
GROUP BY b.id, b.label, b.iban, b.initial_balance;

CREATE VIEW IF NOT EXISTS v_subscription_mrr AS
SELECT COALESCE(SUM(
        CASE s.plan_id IS NOT NULL WHEN TRUE THEN
            CASE sp.billing_cycle
                WHEN 'monthly' THEN s.unit_amount_ht * s.quantity
                WHEN 'quarterly' THEN s.unit_amount_ht * s.quantity / 3
                WHEN 'half_yearly' THEN s.unit_amount_ht * s.quantity / 6
                WHEN 'yearly' THEN s.unit_amount_ht * s.quantity / 12
            END
        END
       ), 0) AS mrr_ht
FROM subscriptions s
JOIN subscription_plans sp ON sp.id = s.plan_id
WHERE s.status = 'active'
  AND (s.ends_on IS NULL OR s.ends_on >= CURRENT_DATE);

CREATE VIEW IF NOT EXISTS v_invoice_aging AS
SELECT i.id, i.invoice_number, i.third_party_id, tp.name AS third_party_name,
       i.issue_date, i.due_date, i.total_ttc, i.amount_paid, i.balance,
       CASE
           WHEN i.balance <= 0 THEN 0
           WHEN CAST(julianday('now') - julianday(i.due_date) AS INTEGER) <= 0 THEN 0
           WHEN CAST(julianday('now') - julianday(i.due_date) AS INTEGER) <= 30 THEN 1
           WHEN CAST(julianday('now') - julianday(i.due_date) AS INTEGER) <= 60 THEN 2
           WHEN CAST(julianday('now') - julianday(i.due_date) AS INTEGER) <= 90 THEN 3
           ELSE 4
       END AS aging_bucket
FROM invoices i
JOIN third_parties tp ON tp.id = i.third_party_id
WHERE i.type = 'sale' AND i.status NOT IN ('draft','cancelled');

-- =============================================================================
-- Boîtes mail d'entreprise (miroir de la migration 20261027_000014_mailbox.sql)
-- Secrets chiffrés au repos par la couche applicative (libsodium, enc::).
-- =============================================================================
CREATE TABLE IF NOT EXISTS mail_accounts (
    id               INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id          INTEGER NOT NULL UNIQUE REFERENCES users(id) ON DELETE CASCADE,
    email            TEXT NOT NULL,
    display_name     TEXT NOT NULL DEFAULT '',
    imap_host        TEXT NOT NULL,
    imap_port        INTEGER NOT NULL DEFAULT 993,
    imap_encryption  TEXT NOT NULL DEFAULT 'ssl'
        CHECK (imap_encryption IN ('ssl','tls','none')),
    imap_username    TEXT NOT NULL,
    imap_secret      TEXT NOT NULL,
    smtp_host        TEXT NOT NULL,
    smtp_port        INTEGER NOT NULL DEFAULT 465,
    smtp_encryption  TEXT NOT NULL DEFAULT 'ssl'
        CHECK (smtp_encryption IN ('ssl','tls','none')),
    smtp_username    TEXT NOT NULL,
    smtp_secret      TEXT NOT NULL,
    is_active        INTEGER NOT NULL DEFAULT 1,
    last_sync_at     TEXT,
    created_at       TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_mail_accounts_email ON mail_accounts(email);
CREATE INDEX IF NOT EXISTS idx_mail_accounts_active ON mail_accounts(is_active);
