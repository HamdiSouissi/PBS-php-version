-- Code d'activité à renseigner dans l'identité légale de l'entreprise.
ALTER TABLE companies ADD COLUMN naf_code VARCHAR(10) NOT NULL DEFAULT '';
