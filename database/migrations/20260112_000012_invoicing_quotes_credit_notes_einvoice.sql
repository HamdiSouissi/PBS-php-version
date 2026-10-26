-- =============================================================================
-- Migration 012 - Devis, avoirs, facturation récurrente, numérotation, e-facturation
-- =============================================================================

-- Permission e-facturation (les autres permissions facturation viennent du RbacSeeder)
INSERT INTO permissions (code, label, module)
VALUES ('invoicing.einvoice', 'Générer et exporter les factures électroniques (UBL/CII)', 'invoicing')
ON CONFLICT (code) DO NOTHING;

INSERT INTO role_permission (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.code IN ('comptable') AND p.code = 'invoicing.einvoice'
ON CONFLICT DO NOTHING;

-- Séquences de numérotation des documents commerciaux (par type et par année)
CREATE TABLE document_sequences (
    id          BIGSERIAL PRIMARY KEY,
    kind        VARCHAR(20)  NOT NULL,
    period      VARCHAR(10)  NOT NULL,
    last_value  INTEGER      NOT NULL DEFAULT 0,
    updated_at  TIMESTAMPTZ  NOT NULL DEFAULT now(),
    CONSTRAINT document_sequences_unique UNIQUE (kind, period)
);

-- Devis
CREATE TABLE quotes (
    id               BIGSERIAL PRIMARY KEY,
    company_id       BIGINT NOT NULL REFERENCES companies(id) ON DELETE RESTRICT,
    third_party_id   BIGINT NOT NULL REFERENCES third_parties(id) ON DELETE RESTRICT,
    quote_number     VARCHAR(60) NOT NULL UNIQUE,
    status           VARCHAR(20) NOT NULL DEFAULT 'draft'
        CHECK (status IN ('draft','sent','accepted','rejected','invoiced','expired')),
    issue_date       DATE NOT NULL,
    valid_until      DATE,
    currency         VARCHAR(3) NOT NULL DEFAULT 'EUR',
    total_ht         NUMERIC(14,2) NOT NULL DEFAULT 0,
    total_vat        NUMERIC(14,2) NOT NULL DEFAULT 0,
    total_ttc        NUMERIC(14,2) NOT NULL DEFAULT 0,
    notes            TEXT NOT NULL DEFAULT '',
    converted_invoice_id BIGINT REFERENCES invoices(id) ON DELETE SET NULL,
    created_by       BIGINT REFERENCES users(id) ON DELETE SET NULL,
    created_at       TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at       TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX idx_quotes_party ON quotes(third_party_id);
CREATE INDEX idx_quotes_status ON quotes(status);

CREATE TABLE quote_lines (
    id           BIGSERIAL PRIMARY KEY,
    quote_id     BIGINT NOT NULL REFERENCES quotes(id) ON DELETE CASCADE,
    label        VARCHAR(255) NOT NULL,
    quantity     NUMERIC(12,3) NOT NULL DEFAULT 1,
    unit_price   NUMERIC(12,4) NOT NULL DEFAULT 0,
    vat_rate     NUMERIC(6,3) NOT NULL DEFAULT 20.000,
    discount_pct NUMERIC(6,3) NOT NULL DEFAULT 0,
    line_ht      NUMERIC(14,2) NOT NULL DEFAULT 0,
    line_vat     NUMERIC(14,2) NOT NULL DEFAULT 0,
    line_ttc     NUMERIC(14,2) NOT NULL DEFAULT 0,
    sort_order   INTEGER NOT NULL DEFAULT 0
);

-- Avoirs
CREATE TABLE credit_notes (
    id               BIGSERIAL PRIMARY KEY,
    company_id       BIGINT NOT NULL REFERENCES companies(id) ON DELETE RESTRICT,
    third_party_id   BIGINT NOT NULL REFERENCES third_parties(id) ON DELETE RESTRICT,
    invoice_id       BIGINT REFERENCES invoices(id) ON DELETE SET NULL,
    credit_number    VARCHAR(60) NOT NULL UNIQUE,
    status           VARCHAR(20) NOT NULL DEFAULT 'draft'
        CHECK (status IN ('draft','issued','cancelled')),
    issue_date       DATE NOT NULL,
    currency         VARCHAR(3) NOT NULL DEFAULT 'EUR',
    total_ht         NUMERIC(14,2) NOT NULL DEFAULT 0,
    total_vat        NUMERIC(14,2) NOT NULL DEFAULT 0,
    total_ttc        NUMERIC(14,2) NOT NULL DEFAULT 0,
    reason           TEXT NOT NULL DEFAULT '',
    created_by       BIGINT REFERENCES users(id) ON DELETE SET NULL,
    created_at       TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at       TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX idx_credit_notes_invoice ON credit_notes(invoice_id);

CREATE TABLE credit_note_lines (
    id           BIGSERIAL PRIMARY KEY,
    credit_note_id BIGINT NOT NULL REFERENCES credit_notes(id) ON DELETE CASCADE,
    label        VARCHAR(255) NOT NULL,
    quantity     NUMERIC(12,3) NOT NULL DEFAULT 1,
    unit_price   NUMERIC(12,4) NOT NULL DEFAULT 0,
    vat_rate     NUMERIC(6,3) NOT NULL DEFAULT 20.000,
    line_ht      NUMERIC(14,2) NOT NULL DEFAULT 0,
    line_vat     NUMERIC(14,2) NOT NULL DEFAULT 0,
    line_ttc     NUMERIC(14,2) NOT NULL DEFAULT 0,
    sort_order   INTEGER NOT NULL DEFAULT 0
);

-- Modèles de facturation récurrente
CREATE TABLE recurring_invoice_templates (
    id              BIGSERIAL PRIMARY KEY,
    company_id      BIGINT NOT NULL REFERENCES companies(id) ON DELETE RESTRICT,
    third_party_id  BIGINT NOT NULL REFERENCES third_parties(id) ON DELETE RESTRICT,
    label           VARCHAR(180) NOT NULL,
    frequency       VARCHAR(10) NOT NULL
        CHECK (frequency IN ('monthly','quarterly','half_yearly','yearly')),
    next_run_on     DATE NOT NULL,
    lines_payload   JSONB NOT NULL DEFAULT '[]'::jsonb,
    is_active       BOOLEAN NOT NULL DEFAULT true,
    last_run_on     DATE,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX idx_recurring_next_run ON recurring_invoice_templates(next_run_on) WHERE is_active;

-- Boîte de sortie e-facturation (statut réel : génération locale UBL/CII,
-- la télétransmission PPF/PDP/Chorus Pro nécessite un raccordement externe)
CREATE TABLE e_invoice_outbox (
    id             BIGSERIAL PRIMARY KEY,
    invoice_id     BIGINT NOT NULL REFERENCES invoices(id) ON DELETE CASCADE,
    format         VARCHAR(10) NOT NULL CHECK (format IN ('UBL','CII')),
    platform       VARCHAR(30) NOT NULL DEFAULT 'PPF',
    status         VARCHAR(30) NOT NULL DEFAULT 'generated'
        CHECK (status IN ('generated','exported','transmission_pending','acknowledged','rejected')),
    file_path      VARCHAR(500) NOT NULL,
    checksum_sha256 VARCHAR(64) NOT NULL DEFAULT '',
    external_reference VARCHAR(120) NOT NULL DEFAULT '',
    error_message  TEXT NOT NULL DEFAULT '',
    created_at     TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at     TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX idx_e_invoice_outbox_status ON e_invoice_outbox(status);

-- Vue : échéancier factures (factures émises non soldées)
CREATE OR REPLACE VIEW v_invoice_aging AS
SELECT i.id, i.invoice_number, i.third_party_id, tp.name AS third_party_name,
       i.issue_date, i.due_date, i.total_ttc, i.amount_paid, i.balance,
       CASE
           WHEN i.balance <= 0 THEN 0
           WHEN CURRENT_DATE - i.due_date <= 0 THEN 0
           WHEN CURRENT_DATE - i.due_date <= 30 THEN 1
           WHEN CURRENT_DATE - i.due_date <= 60 THEN 2
           WHEN CURRENT_DATE - i.due_date <= 90 THEN 3
           ELSE 4
       END AS aging_bucket
FROM invoices i
JOIN third_parties tp ON tp.id = i.third_party_id
WHERE i.type = 'sale' AND i.status NOT IN ('draft','cancelled');
