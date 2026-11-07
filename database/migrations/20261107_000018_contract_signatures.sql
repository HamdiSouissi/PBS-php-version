-- Signature électronique simple : version figée et traces des consentements.
CREATE TABLE contract_signatures (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    contract_id BIGINT UNSIGNED NOT NULL,
    document_id BIGINT UNSIGNED NOT NULL,
    snapshot_name VARCHAR(100) NOT NULL,
    document_sha256 CHAR(64) NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    client_name VARCHAR(180) NOT NULL,
    client_email VARCHAR(254) NOT NULL,
    provider_user_id BIGINT UNSIGNED NOT NULL,
    provider_name VARCHAR(180) NOT NULL,
    provider_email VARCHAR(254) NOT NULL,
    provider_consent VARCHAR(500) NOT NULL,
    client_consent VARCHAR(500) NULL,
    provider_signed_at DATETIME NOT NULL,
    provider_ip VARCHAR(64) NOT NULL,
    provider_agent VARCHAR(500) NOT NULL,
    expires_at DATETIME NOT NULL,
    client_signed_at DATETIME NULL,
    client_ip VARCHAR(64) NULL,
    client_agent VARCHAR(500) NULL,
    revoked_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT signature_contract_fk FOREIGN KEY (contract_id) REFERENCES client_contracts(id),
    CONSTRAINT signature_document_fk FOREIGN KEY (document_id) REFERENCES documents(id),
    CONSTRAINT signature_user_fk FOREIGN KEY (provider_user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX idx_signature_contract ON contract_signatures(contract_id);
