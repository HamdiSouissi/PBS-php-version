-- =============================================================================
-- Migration 001 - Noyau : utilisateurs, RBAC, audit, organisations, paramètres
-- PostgreSQL 14+
-- =============================================================================

CREATE TABLE IF NOT EXISTS users (
    id                    BIGSERIAL PRIMARY KEY,
    email                 VARCHAR(180) NOT NULL UNIQUE,
    password_hash         VARCHAR(255) NOT NULL,
    first_name            VARCHAR(100) NOT NULL DEFAULT '',
    last_name             VARCHAR(100) NOT NULL DEFAULT '',
    is_active             BOOLEAN NOT NULL DEFAULT TRUE,
    totp_secret           VARCHAR(255),
    totp_enrolled         BOOLEAN NOT NULL DEFAULT FALSE,
    totp_confirmed_at     TIMESTAMPTZ,
    failed_attempts       INTEGER NOT NULL DEFAULT 0,
    locked_until          TIMESTAMPTZ,
    last_login_at         TIMESTAMPTZ,
    must_change_password  BOOLEAN NOT NULL DEFAULT FALSE,
    created_at            TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at            TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS roles (
    id          BIGSERIAL PRIMARY KEY,
    code        VARCHAR(60) NOT NULL UNIQUE,
    label       VARCHAR(120) NOT NULL,
    description TEXT NOT NULL DEFAULT ''
);

CREATE TABLE IF NOT EXISTS permissions (
    id      BIGSERIAL PRIMARY KEY,
    code    VARCHAR(120) NOT NULL UNIQUE,
    label   VARCHAR(180) NOT NULL,
    module  VARCHAR(60) NOT NULL
);

CREATE TABLE IF NOT EXISTS role_user (
    role_id BIGINT NOT NULL REFERENCES roles(id) ON DELETE CASCADE,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    PRIMARY KEY (role_id, user_id)
);

CREATE TABLE IF NOT EXISTS role_permission (
    role_id       BIGINT NOT NULL REFERENCES roles(id) ON DELETE CASCADE,
    permission_id BIGINT NOT NULL REFERENCES permissions(id) ON DELETE CASCADE,
    PRIMARY KEY (role_id, permission_id)
);

CREATE TABLE IF NOT EXISTS password_resets (
    id         BIGSERIAL PRIMARY KEY,
    user_id    BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token_hash VARCHAR(255) NOT NULL,
    expires_at TIMESTAMPTZ NOT NULL,
    used_at    TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_password_resets_user ON password_resets(user_id);

CREATE TABLE IF NOT EXISTS remember_tokens (
    id         BIGSERIAL PRIMARY KEY,
    user_id    BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token_hash VARCHAR(255) NOT NULL,
    expires_at TIMESTAMPTZ NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_remember_tokens_user ON remember_tokens(user_id);

CREATE TABLE IF NOT EXISTS auth_login_logs (
    id          BIGSERIAL PRIMARY KEY,
    user_id     BIGINT REFERENCES users(id) ON DELETE SET NULL,
    session_id  VARCHAR(128) NOT NULL DEFAULT '',
    ip_address  VARCHAR(45) NOT NULL DEFAULT '',
    user_agent  VARCHAR(255) NOT NULL DEFAULT '',
    remember_me BOOLEAN NOT NULL DEFAULT FALSE,
    logout_at   TIMESTAMPTZ,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_login_logs_user ON auth_login_logs(user_id, created_at);

CREATE TABLE IF NOT EXISTS audit_logs (
    id          BIGSERIAL PRIMARY KEY,
    user_id     BIGINT REFERENCES users(id) ON DELETE SET NULL,
    action      VARCHAR(100) NOT NULL,
    entity_type VARCHAR(80) NOT NULL,
    entity_id   VARCHAR(64),
    changes     JSONB NOT NULL DEFAULT '{}'::jsonb,
    ip_address  VARCHAR(45) NOT NULL DEFAULT '',
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_audit_entity ON audit_logs(entity_type, entity_id);
CREATE INDEX IF NOT EXISTS idx_audit_user ON audit_logs(user_id, created_at);

CREATE TABLE IF NOT EXISTS settings (
    id           BIGSERIAL PRIMARY KEY,
    group_name   VARCHAR(60) NOT NULL,
    key_name     VARCHAR(120) NOT NULL,
    value        TEXT NOT NULL DEFAULT '',
    is_encrypted BOOLEAN NOT NULL DEFAULT FALSE,
    updated_at   TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (group_name, key_name)
);

CREATE TABLE IF NOT EXISTS companies (
    id                     BIGSERIAL PRIMARY KEY,
    legal_name             VARCHAR(180) NOT NULL,
    commercial_name        VARCHAR(180) NOT NULL DEFAULT '',
    siren                  VARCHAR(9),
    siret                  VARCHAR(14),
    vat_number             VARCHAR(20),
    legal_form             VARCHAR(40) NOT NULL DEFAULT 'SAS',
    address                VARCHAR(255) NOT NULL DEFAULT '',
    postal_code            VARCHAR(20) NOT NULL DEFAULT '',
    city                   VARCHAR(120) NOT NULL DEFAULT '',
    country                VARCHAR(60) NOT NULL DEFAULT 'France',
    phone                  VARCHAR(40) NOT NULL DEFAULT '',
    email                  VARCHAR(180) NOT NULL DEFAULT '',
    iban                   VARCHAR(34) NOT NULL DEFAULT '',
    bic                    VARCHAR(11) NOT NULL DEFAULT '',
    urssaf_number          VARCHAR(20) NOT NULL DEFAULT '',
    collective_agreement   VARCHAR(120) NOT NULL DEFAULT '',
    logo_path              VARCHAR(255),
    is_default             BOOLEAN NOT NULL DEFAULT FALSE,
    created_at             TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at             TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS establishments (
    id           BIGSERIAL PRIMARY KEY,
    company_id   BIGINT NOT NULL REFERENCES companies(id) ON DELETE CASCADE,
    name         VARCHAR(180) NOT NULL,
    siret        VARCHAR(14),
    address      VARCHAR(255) NOT NULL DEFAULT '',
    postal_code  VARCHAR(20) NOT NULL DEFAULT '',
    city         VARCHAR(120) NOT NULL DEFAULT '',
    head_office  BOOLEAN NOT NULL DEFAULT FALSE,
    created_at   TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_establishments_company ON establishments(company_id);

CREATE TABLE IF NOT EXISTS departments (
    id         BIGSERIAL PRIMARY KEY,
    company_id BIGINT NOT NULL REFERENCES companies(id) ON DELETE CASCADE,
    code       VARCHAR(30) NOT NULL,
    name       VARCHAR(120) NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (company_id, code)
);

CREATE TABLE IF NOT EXISTS notifications (
    id         BIGSERIAL PRIMARY KEY,
    user_id    BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    type       VARCHAR(60) NOT NULL,
    title      VARCHAR(180) NOT NULL,
    body       TEXT NOT NULL DEFAULT '',
    read_at    TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_notifications_user ON notifications(user_id, read_at);
