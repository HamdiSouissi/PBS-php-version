-- =============================================================================
-- Migration 001 - Noyau : utilisateurs, RBAC, audit, organisations, paramètres
-- MySQL 8.0.16+ / MariaDB 10.11+ (InnoDB, utf8mb4)
-- =============================================================================

CREATE TABLE IF NOT EXISTS users (
    id                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    email                 VARCHAR(180) NOT NULL UNIQUE,
    password_hash         VARCHAR(255) NOT NULL,
    first_name            VARCHAR(100) NOT NULL DEFAULT '',
    last_name             VARCHAR(100) NOT NULL DEFAULT '',
    is_active             TINYINT(1) NOT NULL DEFAULT 1,
    totp_secret           VARCHAR(255),
    totp_enrolled         TINYINT(1) NOT NULL DEFAULT 0,
    totp_confirmed_at     DATETIME,
    failed_attempts       INTEGER NOT NULL DEFAULT 0,
    locked_until          DATETIME,
    last_login_at         DATETIME,
    must_change_password  TINYINT(1) NOT NULL DEFAULT 0,
    created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS roles (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    code        VARCHAR(60) NOT NULL UNIQUE,
    label       VARCHAR(120) NOT NULL,
    description VARCHAR(500) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS permissions (
    id      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    code    VARCHAR(120) NOT NULL UNIQUE,
    label   VARCHAR(180) NOT NULL,
    module  VARCHAR(60) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS role_user (
    role_id BIGINT UNSIGNED NOT NULL REFERENCES roles(id) ON DELETE CASCADE,
    user_id BIGINT UNSIGNED NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    PRIMARY KEY (role_id, user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS role_permission (
    role_id       BIGINT UNSIGNED NOT NULL REFERENCES roles(id) ON DELETE CASCADE,
    permission_id BIGINT UNSIGNED NOT NULL REFERENCES permissions(id) ON DELETE CASCADE,
    PRIMARY KEY (role_id, permission_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_resets (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id    BIGINT UNSIGNED NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token_hash VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at    DATETIME,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX idx_password_resets_user ON password_resets(user_id);

CREATE TABLE IF NOT EXISTS remember_tokens (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id    BIGINT UNSIGNED NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token_hash VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX idx_remember_tokens_user ON remember_tokens(user_id);

CREATE TABLE IF NOT EXISTS auth_login_logs (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id     BIGINT UNSIGNED REFERENCES users(id) ON DELETE SET NULL,
    session_id  VARCHAR(128) NOT NULL DEFAULT '',
    ip_address  VARCHAR(45) NOT NULL DEFAULT '',
    user_agent  VARCHAR(255) NOT NULL DEFAULT '',
    remember_me TINYINT(1) NOT NULL DEFAULT 0,
    logout_at   DATETIME,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX idx_login_logs_user ON auth_login_logs(user_id, created_at);

CREATE TABLE IF NOT EXISTS audit_logs (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id     BIGINT UNSIGNED REFERENCES users(id) ON DELETE SET NULL,
    action      VARCHAR(100) NOT NULL,
    entity_type VARCHAR(80) NOT NULL,
    entity_id   VARCHAR(64),
    changes     JSON NOT NULL,
    ip_address  VARCHAR(45) NOT NULL DEFAULT '',
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX idx_audit_entity ON audit_logs(entity_type, entity_id);
CREATE INDEX idx_audit_user ON audit_logs(user_id, created_at);

CREATE TABLE IF NOT EXISTS settings (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    group_name   VARCHAR(60) NOT NULL,
    key_name     VARCHAR(120) NOT NULL,
    value        TEXT NOT NULL,
    is_encrypted TINYINT(1) NOT NULL DEFAULT 0,
    updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (group_name, key_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS companies (
    id                     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
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
    is_default             TINYINT(1) NOT NULL DEFAULT 0,
    created_at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS establishments (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    company_id   BIGINT UNSIGNED NOT NULL REFERENCES companies(id) ON DELETE CASCADE,
    name         VARCHAR(180) NOT NULL,
    siret        VARCHAR(14),
    address      VARCHAR(255) NOT NULL DEFAULT '',
    postal_code  VARCHAR(20) NOT NULL DEFAULT '',
    city         VARCHAR(120) NOT NULL DEFAULT '',
    head_office  TINYINT(1) NOT NULL DEFAULT 0,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX idx_establishments_company ON establishments(company_id);

CREATE TABLE IF NOT EXISTS departments (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    company_id BIGINT UNSIGNED NOT NULL REFERENCES companies(id) ON DELETE CASCADE,
    code       VARCHAR(30) NOT NULL,
    name       VARCHAR(120) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (company_id, code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notifications (
    id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id    BIGINT UNSIGNED NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    type       VARCHAR(60) NOT NULL,
    title      VARCHAR(180) NOT NULL,
    body       TEXT NOT NULL,
    read_at    DATETIME,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX idx_notifications_user ON notifications(user_id, read_at);
