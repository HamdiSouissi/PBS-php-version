-- =============================================================================
-- Migration 006 - Tiers, factures clients/fournisseurs, lignes, paiements
-- =============================================================================

CREATE TABLE IF NOT EXISTS third_parties (
    id                  BIGSERIAL PRIMARY KEY,
    type                VARCHAR(10) NOT NULL CHECK (type IN ('client','supplier','both')),
    code                VARCHAR(30) NOT NULL UNIQUE,
    name                VARCHAR(180) NOT NULL,
    legal_form          VARCHAR(40) NOT NULL DEFAULT '',
    siren               VARCHAR(9) NOT NULL DEFAULT '',
    siret               VARCHAR(14) NOT NULL DEFAULT '',
    vat_number          VARCHAR(20) NOT NULL DEFAULT '',
    address             VARCHAR(255) NOT NULL DEFAULT '',
    postal_code         VARCHAR(20) NOT NULL DEFAULT '',
    city                VARCHAR(120) NOT NULL DEFAULT '',
    country             VARCHAR(60) NOT NULL DEFAULT 'France',
    email               VARCHAR(180) NOT NULL DEFAULT '',
    phone               VARCHAR(40) NOT NULL DEFAULT '',
    iban                VARCHAR(34) NOT NULL DEFAULT '',
    bic                 VARCHAR(11) NOT NULL DEFAULT '',
    payment_terms_days  INTEGER NOT NULL DEFAULT 30,
    payment_method      VARCHAR(40) NOT NULL DEFAULT 'virement',
    notes               TEXT NOT NULL DEFAULT '',
    is_active           BOOLEAN NOT NULL DEFAULT TRUE,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_third_parties_type ON third_parties(type);

CREATE TABLE IF NOT EXISTS invoices (
    id                   BIGSERIAL PRIMARY KEY,
    company_id           BIGINT NOT NULL REFERENCES companies(id) ON DELETE RESTRICT,
    third_party_id       BIGINT NOT NULL REFERENCES third_parties(id) ON DELETE RESTRICT,
    invoice_number       VARCHAR(60) NOT NULL UNIQUE,
    type                 VARCHAR(10) NOT NULL CHECK (type IN ('sale','purchase')),
    status               VARCHAR(20) NOT NULL DEFAULT 'draft'
                             CHECK (status IN ('draft','issued','partial','paid','overdue','cancelled')),
    issue_date           DATE NOT NULL,
    due_date             DATE NOT NULL,
    currency             VARCHAR(3) NOT NULL DEFAULT 'EUR',
    total_ht             NUMERIC(14,2) NOT NULL DEFAULT 0,
    total_vat            NUMERIC(14,2) NOT NULL DEFAULT 0,
    total_ttc            NUMERIC(14,2) NOT NULL DEFAULT 0,
    amount_paid          NUMERIC(14,2) NOT NULL DEFAULT 0,
    balance              NUMERIC(14,2) NOT NULL DEFAULT 0,
    electronic_status    VARCHAR(30) NOT NULL DEFAULT 'none'
                             CHECK (electronic_status IN ('none','generated','submitted','acknowledged','rejected')),
    electronic_reference VARCHAR(120) NOT NULL DEFAULT '',
    notes                TEXT NOT NULL DEFAULT '',
    created_by           BIGINT REFERENCES users(id) ON DELETE SET NULL,
    created_at           TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at           TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_invoices_party ON invoices(third_party_id);
CREATE INDEX IF NOT EXISTS idx_invoices_status ON invoices(status, due_date);
CREATE INDEX IF NOT EXISTS idx_invoices_type ON invoices(type);

CREATE TABLE IF NOT EXISTS invoice_lines (
    id           BIGSERIAL PRIMARY KEY,
    invoice_id   BIGINT NOT NULL REFERENCES invoices(id) ON DELETE CASCADE,
    label        VARCHAR(255) NOT NULL,
    quantity     NUMERIC(12,3) NOT NULL DEFAULT 1,
    unit_price   NUMERIC(12,4) NOT NULL DEFAULT 0,
    vat_rate     NUMERIC(6,3) NOT NULL DEFAULT 20.000,
    discount_pct NUMERIC(6,3) NOT NULL DEFAULT 0,
    line_ht      NUMERIC(14,2) NOT NULL DEFAULT 0,
    line_vat     NUMERIC(14,2) NOT NULL DEFAULT 0,
    line_ttc     NUMERIC(14,2) NOT NULL DEFAULT 0,
    account_code VARCHAR(20) NOT NULL DEFAULT '706000',
    sort_order   INTEGER NOT NULL DEFAULT 0
);
CREATE INDEX IF NOT EXISTS idx_invoice_lines_invoice ON invoice_lines(invoice_id);

CREATE TABLE IF NOT EXISTS payments (
    id              BIGSERIAL PRIMARY KEY,
    invoice_id      BIGINT NOT NULL REFERENCES invoices(id) ON DELETE CASCADE,
    bank_account_id BIGINT REFERENCES bank_accounts(id) ON DELETE SET NULL,
    paid_on         DATE NOT NULL,
    amount          NUMERIC(14,2) NOT NULL CHECK (amount > 0),
    method          VARCHAR(40) NOT NULL DEFAULT 'virement',
    reference       VARCHAR(120) NOT NULL DEFAULT '',
    recorded_by     BIGINT REFERENCES users(id) ON DELETE SET NULL,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_payments_invoice ON payments(invoice_id);
