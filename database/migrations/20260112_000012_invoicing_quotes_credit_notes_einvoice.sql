-- =============================================================================
-- Migration 012 - Devis, avoirs, facturation récurrente, numérotation, e-facturation
-- MySQL 8.0.16+ / MariaDB 10.11+ (InnoDB, utf8mb4)
-- =============================================================================

-- Permission e-facturation (les autres permissions facturation viennent du RbacSeeder)
INSERT IGNORE INTO permissions (code, label, module)
VALUES ('invoicing.einvoice', 'Générer et exporter les factures électroniques (UBL/CII)', 'invoicing');

INSERT IGNORE INTO role_permission (role_id, permission_id)
SELECT r.id, p.id FROM roles r, permissions p
WHERE r.code IN ('comptable') AND p.code = 'invoicing.einvoice';

-- Séquences de numérotation des documents commerciaux (par type et par année)
CREATE TABLE IF NOT EXISTS document_sequences (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    kind        VARCHAR(20)  NOT NULL,
    period      VARCHAR(10)  NOT NULL,
    `last_value` INTEGER     NOT NULL DEFAULT 0,
    updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT document_sequences_unique UNIQUE (kind, period)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Devis
CREATE TABLE IF NOT EXISTS quotes (
    id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    company_id           BIGINT UNSIGNED NOT NULL REFERENCES companies(id) ON DELETE RESTRICT,
    third_party_id       BIGINT UNSIGNED NOT NULL REFERENCES third_parties(id) ON DELETE RESTRICT,
    quote_number         VARCHAR(60) NOT NULL UNIQUE,
    status               VARCHAR(20) NOT NULL DEFAULT 'draft',
    issue_date           DATE NOT NULL,
    valid_until          DATE,
    currency             VARCHAR(3) NOT NULL DEFAULT 'EUR',
    total_ht             NUMERIC(14,2) NOT NULL DEFAULT 0,
    total_vat            NUMERIC(14,2) NOT NULL DEFAULT 0,
    total_ttc            NUMERIC(14,2) NOT NULL DEFAULT 0,
    notes                VARCHAR(1000) NOT NULL DEFAULT '',
    converted_invoice_id BIGINT UNSIGNED REFERENCES invoices(id) ON DELETE SET NULL,
    created_by           BIGINT UNSIGNED REFERENCES users(id) ON DELETE SET NULL,
    created_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT quotes_status_check CHECK (status IN ('draft','sent','accepted','rejected','invoiced','expired'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX idx_quotes_party ON quotes(third_party_id);
CREATE INDEX idx_quotes_status ON quotes(status);

CREATE TABLE IF NOT EXISTS quote_lines (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    quote_id     BIGINT UNSIGNED NOT NULL REFERENCES quotes(id) ON DELETE CASCADE,
    label        VARCHAR(255) NOT NULL,
    quantity     NUMERIC(12,3) NOT NULL DEFAULT 1,
    unit_price   NUMERIC(12,4) NOT NULL DEFAULT 0,
    vat_rate     NUMERIC(6,3) NOT NULL DEFAULT 20.000,
    discount_pct NUMERIC(6,3) NOT NULL DEFAULT 0,
    line_ht      NUMERIC(14,2) NOT NULL DEFAULT 0,
    line_vat     NUMERIC(14,2) NOT NULL DEFAULT 0,
    line_ttc     NUMERIC(14,2) NOT NULL DEFAULT 0,
    sort_order   INTEGER NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Avoirs
CREATE TABLE IF NOT EXISTS credit_notes (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    company_id     BIGINT UNSIGNED NOT NULL REFERENCES companies(id) ON DELETE RESTRICT,
    third_party_id BIGINT UNSIGNED NOT NULL REFERENCES third_parties(id) ON DELETE RESTRICT,
    invoice_id     BIGINT UNSIGNED REFERENCES invoices(id) ON DELETE SET NULL,
    credit_number  VARCHAR(60) NOT NULL UNIQUE,
    status         VARCHAR(20) NOT NULL DEFAULT 'draft',
    issue_date     DATE NOT NULL,
    currency       VARCHAR(3) NOT NULL DEFAULT 'EUR',
    total_ht       NUMERIC(14,2) NOT NULL DEFAULT 0,
    total_vat      NUMERIC(14,2) NOT NULL DEFAULT 0,
    total_ttc      NUMERIC(14,2) NOT NULL DEFAULT 0,
    reason         VARCHAR(1000) NOT NULL DEFAULT '',
    created_by     BIGINT UNSIGNED REFERENCES users(id) ON DELETE SET NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT credit_notes_status_check CHECK (status IN ('draft','issued','cancelled'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX idx_credit_notes_invoice ON credit_notes(invoice_id);

CREATE TABLE IF NOT EXISTS credit_note_lines (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    credit_note_id BIGINT UNSIGNED NOT NULL REFERENCES credit_notes(id) ON DELETE CASCADE,
    label          VARCHAR(255) NOT NULL,
    quantity       NUMERIC(12,3) NOT NULL DEFAULT 1,
    unit_price     NUMERIC(12,4) NOT NULL DEFAULT 0,
    vat_rate       NUMERIC(6,3) NOT NULL DEFAULT 20.000,
    line_ht        NUMERIC(14,2) NOT NULL DEFAULT 0,
    line_vat       NUMERIC(14,2) NOT NULL DEFAULT 0,
    line_ttc       NUMERIC(14,2) NOT NULL DEFAULT 0,
    sort_order     INTEGER NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Modèles de facturation récurrente
CREATE TABLE IF NOT EXISTS recurring_invoice_templates (
    id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    company_id     BIGINT UNSIGNED NOT NULL REFERENCES companies(id) ON DELETE RESTRICT,
    third_party_id BIGINT UNSIGNED NOT NULL REFERENCES third_parties(id) ON DELETE RESTRICT,
    label          VARCHAR(180) NOT NULL,
    frequency      VARCHAR(10) NOT NULL,
    next_run_on    DATE NOT NULL,
    lines_payload  JSON NOT NULL,
    is_active      TINYINT(1) NOT NULL DEFAULT 1,
    last_run_on    DATE,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT recurring_frequency_check CHECK (frequency IN ('monthly','quarterly','half_yearly','yearly'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- MySQL/MariaDB ne gèrent pas les index partiels (WHERE) : index simple,
-- le filtre is_active reste appliqué dans la requête du planificateur.
CREATE INDEX idx_recurring_next_run ON recurring_invoice_templates(next_run_on);

-- Boîte de sortie e-facturation (statut réel : génération locale UBL/CII,
-- la télétransmission PPF/PDP/Chorus Pro nécessite un raccordement externe)
CREATE TABLE IF NOT EXISTS e_invoice_outbox (
    id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    invoice_id         BIGINT UNSIGNED NOT NULL REFERENCES invoices(id) ON DELETE CASCADE,
    format             VARCHAR(10) NOT NULL,
    platform           VARCHAR(30) NOT NULL DEFAULT 'PPF',
    status             VARCHAR(30) NOT NULL DEFAULT 'generated',
    file_path          VARCHAR(500) NOT NULL,
    checksum_sha256    VARCHAR(64) NOT NULL DEFAULT '',
    external_reference VARCHAR(120) NOT NULL DEFAULT '',
    error_message      VARCHAR(1000) NOT NULL DEFAULT '',
    created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT e_invoice_format_check CHECK (format IN ('UBL','CII')),
    CONSTRAINT e_invoice_status_check CHECK (status IN ('generated','exported','transmission_pending','acknowledged','rejected'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX idx_e_invoice_outbox_status ON e_invoice_outbox(status);

-- Vue : échéancier factures (factures émises non soldées)
CREATE OR REPLACE VIEW v_invoice_aging AS
SELECT i.id, i.invoice_number, i.third_party_id, tp.name AS third_party_name,
       i.issue_date, i.due_date, i.total_ttc, i.amount_paid, i.balance,
       CASE
           WHEN i.balance <= 0 THEN 0
           WHEN DATEDIFF(CURRENT_DATE, i.due_date) <= 0 THEN 0
           WHEN DATEDIFF(CURRENT_DATE, i.due_date) <= 30 THEN 1
           WHEN DATEDIFF(CURRENT_DATE, i.due_date) <= 60 THEN 2
           WHEN DATEDIFF(CURRENT_DATE, i.due_date) <= 90 THEN 3
           ELSE 4
       END AS aging_bucket
FROM invoices i
JOIN third_parties tp ON tp.id = i.third_party_id
WHERE i.type = 'sale' AND i.status NOT IN ('draft','cancelled');