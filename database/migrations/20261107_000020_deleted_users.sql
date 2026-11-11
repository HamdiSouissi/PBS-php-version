-- Suppression logique : les historiques métier et les signatures conservent leur référence.
ALTER TABLE users ADD COLUMN deleted_at DATETIME NULL DEFAULT NULL;
CREATE INDEX idx_users_deleted_at ON users(deleted_at);
