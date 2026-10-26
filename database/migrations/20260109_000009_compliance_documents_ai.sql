-- =============================================================================
-- Migration 009 - Conformité, centre documentaire, assistant IA
-- =============================================================================

CREATE TABLE IF NOT EXISTS compliance_obligations (
    id           BIGSERIAL PRIMARY KEY,
    code         VARCHAR(60) NOT NULL UNIQUE,
    title        VARCHAR(255) NOT NULL,
    authority    VARCHAR(60) NOT NULL
                     CHECK (authority IN ('URSSAF','DGFIP','INSEE','greffe','medecine_travail','interne')),
    frequency    VARCHAR(20) NOT NULL
                     CHECK (frequency IN ('monthly','quarterly','yearly','event','continuous')),
    period_label VARCHAR(60) NOT NULL DEFAULT '',
    due_on       DATE,
    status       VARCHAR(20) NOT NULL DEFAULT 'pending'
                     CHECK (status IN ('pending','done','overdue','not_applicable')),
    completed_at TIMESTAMPTZ,
    notes        TEXT NOT NULL DEFAULT '',
    created_at   TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at   TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_obligations_status ON compliance_obligations(status, due_on);

CREATE TABLE IF NOT EXISTS documents (
    id            BIGSERIAL PRIMARY KEY,
    name          VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL DEFAULT '',
    disk          VARCHAR(30) NOT NULL DEFAULT 'documents',
    path          VARCHAR(255) NOT NULL,
    mime          VARCHAR(100) NOT NULL DEFAULT '',
    size          BIGINT NOT NULL DEFAULT 0,
    category      VARCHAR(60) NOT NULL DEFAULT 'autre',
    entity_type   VARCHAR(60) NOT NULL DEFAULT '',
    entity_id     BIGINT,
    visibility    VARCHAR(20) NOT NULL DEFAULT 'private'
                      CHECK (visibility IN ('private','company','portal')),
    checksum      VARCHAR(64) NOT NULL DEFAULT '',
    uploaded_by   BIGINT REFERENCES users(id) ON DELETE SET NULL,
    created_at    TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_documents_entity ON documents(entity_type, entity_id);
CREATE INDEX IF NOT EXISTS idx_documents_category ON documents(category);

CREATE TABLE IF NOT EXISTS document_versions (
    id          BIGSERIAL PRIMARY KEY,
    document_id BIGINT NOT NULL REFERENCES documents(id) ON DELETE CASCADE,
    version     INTEGER NOT NULL,
    path        VARCHAR(255) NOT NULL,
    size        BIGINT NOT NULL DEFAULT 0,
    created_by  BIGINT REFERENCES users(id) ON DELETE SET NULL,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (document_id, version)
);

CREATE TABLE IF NOT EXISTS ai_provider_settings (
    id                 BIGSERIAL PRIMARY KEY,
    provider           VARCHAR(30) NOT NULL UNIQUE
                           CHECK (provider IN ('ollama','codecraft')),
    is_enabled         BOOLEAN NOT NULL DEFAULT FALSE,
    url                VARCHAR(255) NOT NULL DEFAULT '',
    model              VARCHAR(120) NOT NULL DEFAULT '',
    api_key_encrypted  TEXT NOT NULL DEFAULT '',
    temperature        NUMERIC(4,2) NOT NULL DEFAULT 0.70,
    timeout            INTEGER NOT NULL DEFAULT 30,
    system_prompt      TEXT NOT NULL DEFAULT '',
    extra              JSONB NOT NULL DEFAULT '{}'::jsonb,
    updated_by         BIGINT REFERENCES users(id) ON DELETE SET NULL,
    updated_at         TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS ai_conversations (
    id         BIGSERIAL PRIMARY KEY,
    user_id    BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    title      VARCHAR(180) NOT NULL DEFAULT 'Nouvelle conversation',
    provider   VARCHAR(30) NOT NULL DEFAULT 'ollama',
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS ai_messages (
    id              BIGSERIAL PRIMARY KEY,
    conversation_id BIGINT NOT NULL REFERENCES ai_conversations(id) ON DELETE CASCADE,
    role            VARCHAR(20) NOT NULL CHECK (role IN ('user','assistant','system')),
    content         TEXT NOT NULL,
    tokens          INTEGER NOT NULL DEFAULT 0,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_ai_messages_conversation ON ai_messages(conversation_id);
