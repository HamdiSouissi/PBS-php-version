-- =============================================================================
-- Migration 011 - Correctif : libellé de genre ('non_renseigne' = 13 caractères)
-- =============================================================================

ALTER TABLE employees ALTER COLUMN gender TYPE VARCHAR(20);

ALTER TABLE employees
    DROP CONSTRAINT IF EXISTS employees_gender_check;

ALTER TABLE employees
    ADD CONSTRAINT employees_gender_check
    CHECK (gender IN ('m','f','non_renseigne'));
