-- =============================================================================
-- Migration 011 - Correctif : libellé de genre ('non_renseigne' = 13 caractères)
-- MySQL/MariaDB : MODIFY COLUMN élargit le type. La contrainte nommée
-- employees_gender_check (créée en 002) reste attachée à la colonne et
-- continue de s'appliquer, sa liste de valeurs étant indépendante de la taille.
-- =============================================================================

ALTER TABLE employees
    MODIFY COLUMN gender VARCHAR(20) NOT NULL DEFAULT 'non_renseigne';
