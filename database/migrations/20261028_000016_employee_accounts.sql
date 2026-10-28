-- =============================================================================
-- Migration 016 - RH : rattachement salarié ↔ compte utilisateur et
-- documents ↔ fiche salarié.
--   * employees.user_id : compte de connexion créé automatiquement à
--     l'embauche (rôle "employe", mot de passe temporaire à changer).
--   * documents.employee_id : permet au salarié de ne consulter que ses
--     propres documents (bulletins, contrats, fiches) dans le portail.
-- Les deux colonnes sont nullable : le rattachement est rempli par le code
-- applicatif, et la suppression d'un compte ou d'une fiche ne doit pas
-- supprimer les données métier (ON DELETE SET NULL).
-- MySQL 8.0.16+ / MariaDB 10.11+ (InnoDB, utf8mb4)
-- =============================================================================

ALTER TABLE employees
    ADD COLUMN user_id BIGINT UNSIGNED NULL AFTER id,
    ADD CONSTRAINT fk_employees_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL;
CREATE INDEX idx_employees_user ON employees(user_id);

ALTER TABLE documents
    ADD COLUMN employee_id BIGINT UNSIGNED NULL AFTER entity_id,
    ADD CONSTRAINT fk_documents_employee
        FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE SET NULL;
CREATE INDEX idx_documents_employee ON documents(employee_id);
