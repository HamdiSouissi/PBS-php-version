-- Les brouillons supprimés restent consultables dans l'archive administrateur.
ALTER TABLE expense_reports ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL;
ALTER TABLE expense_reports ADD COLUMN deleted_by BIGINT UNSIGNED NULL DEFAULT NULL;
CREATE INDEX idx_expense_reports_deleted_at ON expense_reports(deleted_at);
ALTER TABLE expense_reports ADD CONSTRAINT expense_reports_deleted_by_fk FOREIGN KEY (deleted_by) REFERENCES users(id) ON DELETE SET NULL;
