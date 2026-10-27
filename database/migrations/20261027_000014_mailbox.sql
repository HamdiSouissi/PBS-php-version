-- =============================================================================
-- Migration 014 - Boîtes mail d'entreprise (IMAP/SMTP, hébergeur type IONOS)
-- MySQL 8.0.16+ / MariaDB 10.11+ (InnoDB, utf8mb4)
--
-- Les secrets IMAP/SMTP sont chiffrés au repos (libsodium, préfixe enc::)
-- par la couche applicative ; un utilisateur ne possède qu'une seule boîte.
-- =============================================================================

CREATE TABLE IF NOT EXISTS mail_accounts (
    id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id          BIGINT UNSIGNED NOT NULL,
    email            VARCHAR(255) NOT NULL,
    display_name     VARCHAR(150) NOT NULL DEFAULT '',
    imap_host        VARCHAR(255) NOT NULL,
    imap_port        INT NOT NULL DEFAULT 993,
    imap_encryption  VARCHAR(10) NOT NULL DEFAULT 'ssl'
        CHECK (imap_encryption IN ('ssl','tls','none')),
    imap_username    VARCHAR(255) NOT NULL,
    imap_secret      TEXT NOT NULL,
    smtp_host        VARCHAR(255) NOT NULL,
    smtp_port        INT NOT NULL DEFAULT 465,
    smtp_encryption  VARCHAR(10) NOT NULL DEFAULT 'ssl'
        CHECK (smtp_encryption IN ('ssl','tls','none')),
    smtp_username    VARCHAR(255) NOT NULL,
    smtp_secret      TEXT NOT NULL,
    is_active        TINYINT(1) NOT NULL DEFAULT 1,
    last_sync_at     DATETIME NULL,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_mail_accounts_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT uq_mail_accounts_user UNIQUE (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX idx_mail_accounts_email ON mail_accounts(email);
CREATE INDEX idx_mail_accounts_active ON mail_accounts(is_active);
