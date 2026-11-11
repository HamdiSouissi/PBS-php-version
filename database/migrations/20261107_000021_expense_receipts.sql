-- Justificatifs PDF conservés en stockage privé, rattachés à une note de frais.
CREATE TABLE expense_receipts (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    report_id BIGINT UNSIGNED NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    storage_name CHAR(36) NOT NULL UNIQUE,
    file_size BIGINT UNSIGNED NOT NULL,
    uploaded_by BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT expense_receipt_report_fk FOREIGN KEY (report_id) REFERENCES expense_reports(id) ON DELETE CASCADE,
    CONSTRAINT expense_receipt_user_fk FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX idx_expense_receipts_report ON expense_receipts(report_id);
