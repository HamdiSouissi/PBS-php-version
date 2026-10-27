-- =============================================================================
-- Migration 009 - Conformité, centre documentaire, assistant IA
-- MySQL 8.0.16+ / MariaDB 10.11+ (InnoDB, utf8mb4)
-- =============================================================================

CREATE TABLE IF NOT EXISTS compliance_obligations (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    code         VARCHAR(60) NOT NULL UNIQUE,
    title        VARCHAR(255) NOT NULL,
    authority    VARCHAR(60) NOT NULL,
    frequency    VARCHAR(20) NOT NULL,
    period_label VARCHAR(60) NOT NULL DEFAULT '',
    due_on       DATE,
    status       VARCHAR(20) NOT NULL DEFAULT 'pending',
    completed_at DATETIME,
    notes        VARCHAR(1000) NOT NULL DEFAULT '',
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT obligations_authority_check CHECK (authority IN ('URSSAF','DGFIP','INSEE','greffe','medecine_travail','interne')),
    CONSTRAINT obligations_frequency_check CHECK (frequency IN ('monthly','quarterly','yearly','event','continuous')),
    CONSTRAINT obligations_status_check CHECK (status IN ('pending','done','overdue','not_applicable'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX idx_obligations_status ON compliance_obligations(status, due_on);

CREATE TABLE IF NOT EXISTS documents (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL DEFAULT '',
    disk          VARCHAR(30) NOT NULL DEFAULT 'documents',
    path          VARCHAR(255) NOT NULL,
    mime          VARCHAR(100) NOT NULL DEFAULT '',
    size          BIGINT NOT NULL DEFAULT 0,
    category      VARCHAR(60) NOT NULL DEFAULT 'autre',
    entity_type   VARCHAR(60) NOT NULL DEFAULT '',
    entity_id     BIGINT UNSIGNED,
    visibility    VARCHAR(20) NOT NULL DEFAULT 'private',
    checksum      VARCHAR(64) NOT NULL DEFAULT '',
    uploaded_by   BIGINT UNSIGNED REFERENCES users(id) ON DELETE SET NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT documents_visibility_check CHECK (visibility IN ('private','company','portal'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX idx_documents_entity ON documents(entity_type, entity_id);
CREATE INDEX idx_documents_category ON documents(category);

CREATE TABLE IF NOT EXISTS document_versions (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    document_id BIGINT UNSIGNED NOT NULL REFERENCES documents(id) ON DELETE CASCADE,
    version     INTEGER NOT NULL,
    path        VARCHAR(255) NOT NULL,
    size        BIGINT NOT NULL DEFAULT 0,
    created_by  BIGINT UNSIGNED REFERENCES users(id) ON DELETE SET NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (document_id, version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_provider_settings (
    id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    provider          VARCHAR(30) NOT NULL UNIQUE,
    is_enabled        TINYINT(1) NOT NULL DEFAULT 0,
    url               VARCHAR(255) NOT NULL DEFAULT '',
    model             VARCHAR(120) NOT NULL DEFAULT '',
    api_key_encrypted VARCHAR(1000) NOT NULL DEFAULT '',
    temperature       NUMERIC(4,2) NOT NULL DEFAULT 0.70,
    timeout           INTEGER NOT NULL DEFAULT 30,
    system_prompt     VARCHAR(2000) NOT NULL DEFAULT '',
    extra             JSON NOT NULL,
    updated_by        BIGINT UNSIGNED REFERENCES users(id) ON DELETE SET NULL,
    updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT ai_provider_check CHECK (provider IN ('ollama','codecraft'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_conversations (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id    BIGINT UNSIGNED NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    title      VARCHAR(180) NOT NULL DEFAULT 'Nouvelle conversation',
    provider   VARCHAR(30) NOT NULL DEFAULT 'ollama',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ai_messages (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    conversation_id BIGINT UNSIGNED NOT NULL REFERENCES ai_conversations(id) ON DELETE CASCADE,
    role            VARCHAR(30) NOT NULL,
    content         TEXT NOT NULL,
    tokens          INTEGER NOT NULL DEFAULT 0,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT ai_messages_role_check CHECK (role IN ('user','assistant','system'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX idx_ai_messages_conversation ON ai_messages(conversation_id);
