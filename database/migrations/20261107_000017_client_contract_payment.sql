-- Les contrats historiques conservent leurs conditions initiales (NULL).
ALTER TABLE client_contracts ADD COLUMN services_description TEXT NULL;
ALTER TABLE client_contracts ADD COLUMN payment_terms VARCHAR(20) NULL;
