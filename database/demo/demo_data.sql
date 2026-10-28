-- =============================================================================
-- Prime Business Suite — Jeu de données de démonstration complet
-- -----------------------------------------------------------------------------
-- Prérequis : base fraîchement migrée et ensemencée :
--     php bin/console migrate:run
--     php bin/console db:seed
-- (les seeders créent la société par défaut, le PCG, les journaux, les types
--  de congés, les règles URSSAF, les rôles/permissions et l'administrateur)
--
-- Cible : MySQL 8 / MariaDB 10.11 (sql_mode strict recommandé).
-- Le script n'utilise que du SQL standard : il est également rejouable sur
-- SQLite pour les besoins de test. Les références aux données seedées
-- (rôles, journaux, comptes, exercices...) passent par des sous-requêtes sur
-- clés naturelles, le script ne dépend donc d'aucun identifiant figé.
--
-- Mot de passe de TOUS les comptes de démonstration : PBS@2026!demo
-- (le compte admin d'usine conserve PBS@2026!admin)
--
-- Données volontairement fictives — toute coïncidence avec une personne ou
-- une entreprise réelle serait fortuite.
-- =============================================================================

-- -----------------------------------------------------------------------------
-- 1. Utilisateurs et attribution des rôles
-- -----------------------------------------------------------------------------
INSERT INTO users
    (id, email, password_hash, first_name, last_name, is_active, totp_enrolled,
     failed_attempts, must_change_password, last_login_at, created_at, updated_at)
VALUES
    (2, 'claire.fontaine@demo.fr', '$argon2id$v=19$m=65536,t=4,p=2$E46vz723eWTbMi39HO/pmw$jDXQGqSlse06Hpkm8u/y5nzDQbm9jkg6FODZx99VWu8', 'Claire', 'Fontaine', 1, 0, 0, 0, '2026-10-27 08:14:00', '2026-09-01 09:00:00', '2026-10-27 08:14:00'),
    (3, 'marc.leroy@demo.fr',      '$argon2id$v=19$m=65536,t=4,p=2$E46vz723eWTbMi39HO/pmw$jDXQGqSlse06Hpkm8u/y5nzDQbm9jkg6FODZx99VWu8', 'Marc', 'Leroy', 1, 0, 0, 0, '2026-10-27 08:21:00', '2026-09-01 09:00:00', '2026-10-27 08:21:00'),
    (4, 'karim.benali@demo.fr',    '$argon2id$v=19$m=65536,t=4,p=2$E46vz723eWTbMi39HO/pmw$jDXQGqSlse06Hpkm8u/y5nzDQbm9jkg6FODZx99VWu8', 'Karim', 'Benali', 1, 0, 0, 0, '2026-10-27 08:32:00', '2026-09-01 09:00:00', '2026-10-27 08:32:00'),
    (5, 'sophie.martin@demo.fr',   '$argon2id$v=19$m=65536,t=4,p=2$E46vz723eWTbMi39HO/pmw$jDXQGqSlse06Hpkm8u/y5nzDQbm9jkg6FODZx99VWu8', 'Sophie', 'Martin', 1, 0, 0, 0, '2026-10-26 17:45:00', '2026-09-15 10:00:00', '2026-10-26 17:45:00'),
    (6, 'nathan.girard@demo.fr',   '$argon2id$v=19$m=65536,t=4,p=2$E46vz723eWTbMi39HO/pmw$jDXQGqSlse06Hpkm8u/y5nzDQbm9jkg6FODZx99VWu8', 'Nathan', 'Girard', 1, 0, 0, 0, '2026-10-25 11:02:00', '2026-09-20 10:00:00', '2026-10-25 11:02:00'),
    (7, 'ambre.dupuis@demo.fr',    '$argon2id$v=19$m=65536,t=4,p=2$E46vz723eWTbMi39HO/pmw$jDXQGqSlse06Hpkm8u/y5nzDQbm9jkg6FODZx99VWu8', 'Ambre', 'Dupuis', 1, 0, 0, 0, NULL, '2026-10-10 14:00:00', '2026-10-10 14:00:00');

-- L'administrateur d'usine doit être immédiatement utilisable dans la démo :
-- pas de changement de mot de passe forcé, pas de compteur de tentatives.
UPDATE users SET must_change_password = 0, failed_attempts = 0 WHERE id = 1;

INSERT INTO role_user (role_id, user_id)
SELECT r.id, u.user_id
FROM roles r
JOIN (
    SELECT 'rh' AS role_code, 2 AS user_id
    UNION ALL SELECT 'paie', 3
    UNION ALL SELECT 'comptable', 4
    UNION ALL SELECT 'employe', 5
    UNION ALL SELECT 'comptable', 6
    UNION ALL SELECT 'rh', 7
    UNION ALL SELECT 'paie', 7
) u ON u.role_code = r.code;

-- -----------------------------------------------------------------------------
-- 2. Tiers : clients, fournisseurs et tiers mixtes
-- -----------------------------------------------------------------------------
INSERT INTO third_parties
    (id, type, code, name, legal_form, siren, siret, vat_number, address, postal_code,
     city, country, email, phone, iban, bic, payment_terms_days, payment_method, notes, is_active)
VALUES
    (1, 'client',   'CLI-0001', 'Atlantique Digital SARL',  'SARL', '812345678', '81234567800015', 'FR08812345678', '14 quai de la Fosse',       '44000', 'Nantes',       'France', 'contact@atlantique-digital.fr',  '+33240000001', 'FR7630004000031234567890145', 'BNPAFRPPXXX', 30, 'virement', 'Client historique — règlement rapide.', 1),
    (2, 'client',   'CLI-0002', 'Méditerranée Conseil',     'SAS',  '823456789', '82345678900022', 'FR11823456789', '8 promenade des Anglais',   '06000', 'Nice',         'France', 'factures@mediterranee-conseil.fr','+33493000002', 'FR763000400004234567890156', 'BNPAFRPPXXX', 30, 'virement', 'Suivi commercial par Ambre.', 1),
    (3, 'client',   'CLI-0003', 'Nord Industrie SA',        'SA',   '834567890', '83456789000039', 'FR22834567890', '210 avenue de la République','59800', 'Lille',        'France', 'compta@nord-industrie.fr',       '+33320000003', 'FR76300040000534567890167', 'BNPAFRPPXXX', 60, 'virement', 'Grand compte — échéances à 60 jours.', 1),
    (4, 'client',   'CLI-0004', 'Boulangerie Lambert',      'EI',   '845678901', '84567890100046', 'FR33845678901', '2 rue des Boulangers',      '69001', 'Lyon',         'France', 'lambert.boulangerie@mail.fr',    '+33478000004', 'FR7630004000064567890178', 'CEPAFRPPXXX', 15, 'prelevement', 'TPE, prélèvement SEPA.', 1),
    (5, 'client',   'CLI-0005', 'Cabinet Vétérinaire Argos','SELARL','856789012','85678901200053', 'FR44856789012', '47 boulevard de la Liberté','35000', 'Rennes',       'France', 'accueil@argos-vet.fr',           '+33299000005', 'FR763000400007567890189', 'CCBPFRPPXXX', 30, 'cheque', '', 1),
    (6, 'both',     'CLI-0006', 'Ouest Énergies SARL',      'SARL', '867890123', '86789012300060', 'FR55867890123', '5 rue de Bretagne',         '44100', 'Nantes',       'France', 'achats@ouest-energies.fr',       '+33240000006', 'FR76300040000867890190', 'CRGEFR21XXX', 30, 'virement', 'Client et fournisseur (refacturation).', 1),
    (7, 'supplier', 'FRN-0001', 'Hexa Cloud SAS',           'SAS',  '712345678', '71234567800017', 'FR77712345678', '99 rue de la Tech',         '75009', 'Paris',        'France', 'factures@hexacloud.fr',          '+33155000007', 'FR7618206004011234567890123', 'AGRIFRPPXXX', 30, 'virement', 'Hébergement et infogérance.', 1),
    (8, 'supplier', 'FRN-0002', 'Papèterie Provençale',     'SARL', '723456789', '72345678900024', 'FR88723456789', '12 cours Lieutaud',        '13006', 'Marseille',    'France', 'commandes@papeterie-provencale.fr','+33491000008','FR761131500001234567890124','CEPAFRPPXXX', 30, 'carte', 'Fournitures de bureau.', 1),
    (9, 'supplier', 'FRN-0003', 'Régie Immobilière du Port','SA',   '734567890', '73456789000031', 'FR99734567890', '3 esplanade du Port',      '44000', 'Nantes',       'France', 'quittances@regie-port.fr',       '+33240000009', 'FR76155890000134567890125', 'CMBRFR2BXXX', 0,  'prelevement', 'Bailleur des locaux.', 1),
    (10,'supplier', 'FRN-0004', 'Mobilier Bureau Plus',     'SAS',  '745678901', '74567890100048', 'FR10745678901', '28 avenue des Ternes',     '75017', 'Paris',        'France', 'pro@mobilier-bureau-plus.fr',    '+33155000010', 'FR7610278000014567890126', 'BSABFRPPXXX', 45, 'virement', 'Mobilier de bureau.', 1);

-- -----------------------------------------------------------------------------
-- 3. Organisation : départements supplémentaires, salariés et contrats
-- -----------------------------------------------------------------------------
INSERT INTO departments (id, company_id, code, name)
VALUES
    (4, (SELECT id FROM companies WHERE is_default = 1 LIMIT 1), 'FIN', 'Finance'),
    (5, (SELECT id FROM companies WHERE is_default = 1 LIMIT 1), 'RH',  'Ressources humaines');

INSERT INTO employees
    (id, company_id, establishment_id, department_id, manager_id, employee_number,
     last_name, first_name, birth_date, birth_place, gender, social_security_number,
     nationality, personal_email, professional_email, phone, address, postal_code, city,
     iban, bic, job_title, qualification, coefficient, status, hire_date,
     is_portal_enabled, portal_token, created_at, updated_at)
VALUES
    (1, (SELECT id FROM companies WHERE is_default = 1 LIMIT 1), 1, 2, NULL, 'EMP-001',
     'Dupont', 'Jean', '1988-04-12', 'Nantes', 'm', '188044412345678', 'Française',
     'jean.dupont@mailperso.fr', 'jean.dupont@primevisionsolutions.fr', '+33612000001',
     '8 rue des Olivettes', '44000', 'Nantes',
     'FR7630001007941111000000185', 'BDFEFRPP', 'Développeur full-stack', 'Cadre', '320',
     'active', '2025-03-01', 1, 'prt-emp-001', '2025-02-20 10:00:00', '2026-09-01 09:00:00'),
    (2, (SELECT id FROM companies WHERE is_default = 1 LIMIT 1), 1, 5, NULL, 'EMP-002',
     'Ben Salah', 'Amine', '1985-11-03', 'Tunis', 'm', '185114456789012', 'Tunisienne',
     'amine.bensalah@mailperso.fr', 'amine.bensalah@primevisionsolutions.fr', '+33612000002',
     '14 rue de Strasbourg', '44000', 'Nantes',
     'FR7630001007942222000000285', 'BDFEFRPP', 'Responsable paie', 'Cadre', '400',
     'active', '2025-03-01', 1, 'prt-emp-002', '2025-02-20 10:05:00', '2026-09-01 09:00:00'),
    (3, (SELECT id FROM companies WHERE is_default = 1 LIMIT 1), 1, 3, NULL, 'EMP-003',
     'Martin', 'Sophie', '1992-07-22', 'Rennes', 'f', '292073578901234', 'Française',
     'sophie.martin@demo.fr', 'sophie.martin@primevisionsolutions.fr', '+33612000003',
     '21 allée du Tertre', '44000', 'Nantes',
     'FR7630001007943333000000385', 'BDFEFRPP', 'Commerciale sédentaire', 'Maîtrise', '280',
     'active', '2025-04-15', 1, 'prt-emp-003', '2025-04-01 10:00:00', '2026-09-01 09:00:00'),
    (4, (SELECT id FROM companies WHERE is_default = 1 LIMIT 1), 1, 4, NULL, 'EMP-004',
     'Girard', 'Nathan', '1990-01-18', 'Bordeaux', 'm', '190013312345612', 'Française',
     'nathan.girard@demo.fr', 'nathan.girard@primevisionsolutions.fr', '+33612000004',
     '6 place du Commerce', '44000', 'Nantes',
     'FR7630001007944444000000485', 'BDFEFRPP', 'Comptable général', 'Maîtrise', '300',
     'active', '2025-03-01', 1, 'prt-emp-004', '2025-02-20 10:10:00', '2026-09-01 09:00:00'),
    (5, (SELECT id FROM companies WHERE is_default = 1 LIMIT 1), 1, 5, 2, 'EMP-005',
     'Fontaine', 'Claire', '1986-09-30', 'Lyon', 'f', '286096912345623', 'Française',
     'claire.fontaine@demo.fr', 'claire.fontaine@primevisionsolutions.fr', '+33612000005',
     '11 quai Turenne', '44000', 'Nantes',
     'FR7630001007945555000000585', 'BDFEFRPP', 'Responsable RH', 'Cadre', '380',
     'active', '2025-03-01', 1, 'prt-emp-005', '2025-02-20 10:15:00', '2026-09-01 09:00:00'),
    (6, (SELECT id FROM companies WHERE is_default = 1 LIMIT 1), 1, 2, 1, 'EMP-006',
     'Lefevre', 'Thomas', '1997-02-05', 'Angers', 'm', '197024912345634', 'Française',
     'thomas.lefevre@mailperso.fr', 'thomas.lefevre@primevisionsolutions.fr', '+33612000006',
     '18 rue d''Anjou', '44000', 'Nantes',
     'FR7630001007946666000000685', 'BDFEFRPP', 'Développeur junior', 'ETAM', '250',
     'on_leave', '2025-09-08', 1, 'prt-emp-006', '2025-08-25 10:00:00', '2026-10-10 14:00:00'),
    (7, (SELECT id FROM companies WHERE is_default = 1 LIMIT 1), 1, 3, 3, 'EMP-007',
     'Mercier', 'Julie', '1994-12-14', 'Nantes', 'f', '294124412345645', 'Française',
     'julie.mercier@mailperso.fr', 'julie.mercier@primevisionsolutions.fr', '+33612000007',
     '3 rue de la Paix', '44000', 'Nantes',
     'FR7630001007947777000000785', 'BDFEFRPP', 'Assistante commerciale', 'ETAM', '240',
     'active', '2026-01-12', 1, 'prt-emp-007', '2026-01-05 10:00:00', '2026-09-01 09:00:00'),
    (8, (SELECT id FROM companies WHERE is_default = 1 LIMIT 1), 1, 2, 1, 'EMP-008',
     'Nguyen', 'Bao', '2001-06-09', 'Hanoï', 'm', '201064412345656', 'Vietnamienne',
     'bao.nguyen@mailperso.fr', 'bao.nguyen@primevisionsolutions.fr', '+33612000008',
     '27 boulevard Gabriel Lauriol', '44000', 'Nantes',
     'FR7630001007948888000000885', 'BDFEFRPP', 'Développeur alternant', 'Alternant', '120',
     'active', '2026-09-01', 1, 'prt-emp-008', '2026-08-20 10:00:00', '2026-09-01 09:00:00');

-- -----------------------------------------------------------------------------
-- 3b. Rattachement obligatoire salarié ↔ compte utilisateur (migration 016)
-- Les comptes existent déjà pour les salariés dont l'e-mail perso correspond
-- à un utilisateur de démo (Sophie, Nathan, Claire). Les autres reçoivent un
-- compte avec le mot de passe de démonstration (PBS@2026!demo), sans forçage.
-- -----------------------------------------------------------------------------

-- Comptes des salariés sans utilisateur existant (ids 8 à 12, après les 7 comptes initiaux).
INSERT INTO users
    (id, email, password_hash, first_name, last_name, is_active, totp_enrolled,
     failed_attempts, must_change_password, created_at, updated_at)
VALUES
    (8,  'jean.dupont@primevisionsolutions.fr',   '$argon2id$v=19$m=65536,t=4,p=2$E46vz723eWTbMi39HO/pmw$jDXQGqSlse06Hpkm8u/y5nzDQbm9jkg6FODZx99VWu8', 'Jean',  'Dupont',     1, 0, 0, 0, '2026-09-01 09:00:00', '2026-09-01 09:00:00'),
    (9,  'amine.bensalah@primevisionsolutions.fr','$argon2id$v=19$m=65536,t=4,p=2$E46vz723eWTbMi39HO/pmw$jDXQGqSlse06Hpkm8u/y5nzDQbm9jkg6FODZx99VWu8', 'Amine', 'Ben Salah',  1, 0, 0, 0, '2026-09-01 09:00:00', '2026-09-01 09:00:00'),
    (10, 'thomas.lefevre@primevisionsolutions.fr','$argon2id$v=19$m=65536,t=4,p=2$E46vz723eWTbMi39HO/pmw$jDXQGqSlse06Hpkm8u/y5nzDQbm9jkg6FODZx99VWu8', 'Thomas','Lefevre',    1, 0, 0, 0, '2026-09-01 09:00:00', '2026-09-01 09:00:00'),
    (11, 'julie.mercier@primevisionsolutions.fr', '$argon2id$v=19$m=65536,t=4,p=2$E46vz723eWTbMi39HO/pmw$jDXQGqSlse06Hpkm8u/y5nzDQbm9jkg6FODZx99VWu8', 'Julie', 'Mercier',    1, 0, 0, 0, '2026-09-01 09:00:00', '2026-09-01 09:00:00'),
    (12, 'bao.nguyen@primevisionsolutions.fr',    '$argon2id$v=19$m=65536,t=4,p=2$E46vz723eWTbMi39HO/pmw$jDXQGqSlse06Hpkm8u/y5nzDQbm9jkg6FODZx99VWu8', 'Bao',   'Nguyen',     1, 0, 0, 0, '2026-09-01 09:00:00', '2026-09-01 09:00:00');

-- Rattachement des fiches aux comptes par correspondance d'e-mail.
UPDATE employees
SET user_id = (
    SELECT u.id FROM users u
    WHERE u.email = employees.professional_email OR u.email = employees.personal_email
)
WHERE user_id IS NULL
  AND EXISTS (
      SELECT 1 FROM users u
      WHERE u.email = employees.professional_email OR u.email = employees.personal_email
  );

-- Le rôle « employe » est attribué à tout salarié rattaché qui ne l'a pas déjà
-- (les responsables conservent leurs rôles existants : RH, paie, comptable…).
INSERT INTO role_user (role_id, user_id)
SELECT (SELECT id FROM roles WHERE code = 'employe'), e.user_id
FROM employees e
WHERE e.user_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM role_user ru
      JOIN roles r ON r.id = ru.role_id
      WHERE ru.user_id = e.user_id AND r.code = 'employe'
  );

INSERT INTO employment_contracts
    (id, employee_id, type, start_date, end_date, trial_period_end, weekly_hours, is_part_time,
     part_time_pct, position, classification, coefficient, hourly_rate, monthly_gross,
     qualification, status, signed_at, created_by, created_at, updated_at)
VALUES
    (1, 1, 'CDI',   '2025-03-01', NULL,         '2025-05-31', 35,    0, 100,  'Développeur full-stack',  'Cadre',    '320', 0,      3200.00, 'Cadre',    'active', '2025-02-25', 1, '2025-02-20 10:00:00', '2026-09-01 09:00:00'),
    (2, 2, 'CDI',   '2025-03-01', NULL,         '2025-05-31', 35,    0, 100,  'Responsable paie',        'Cadre',    '400', 0,      3600.00, 'Cadre',    'active', '2025-02-25', 1, '2025-02-20 10:05:00', '2026-09-01 09:00:00'),
    (3, 3, 'CDI',   '2025-04-15', NULL,         '2025-06-15', 35,    0, 100,  'Commerciale sédentaire',  'Maîtrise', '280', 0,      2900.00, 'Maîtrise', 'active', '2025-04-10', 1, '2025-04-01 10:00:00', '2026-09-01 09:00:00'),
    (4, 4, 'CDI',   '2025-03-01', NULL,         '2025-05-31', 35,    0, 100,  'Comptable général',        'Maîtrise', '300', 0,      3100.00, 'Maîtrise', 'active', '2025-02-25', 1, '2025-02-20 10:10:00', '2026-09-01 09:00:00'),
    (5, 5, 'CDI',   '2025-03-01', NULL,         '2025-05-31', 35,    0, 100,  'Responsable RH',           'Cadre',    '380', 0,      3500.00, 'Cadre',    'active', '2025-02-25', 1, '2025-02-20 10:15:00', '2026-09-01 09:00:00'),
    (6, 6, 'CDI',   '2025-09-08', NULL,         '2025-11-08', 35,    0, 100,  'Développeur junior',       'ETAM',     '250', 0,      2550.00, 'ETAM',     'active', '2025-09-02', 1, '2025-08-25 10:00:00', '2026-09-01 09:00:00'),
    (7, 7, 'CDD',   '2026-01-12', '2026-12-31', '2026-02-12', 35,    0, 100,  'Assistante commerciale',   'ETAM',     '240', 0,      2450.00, 'ETAM',     'active', '2026-01-10', 1, '2026-01-05 10:00:00', '2026-09-01 09:00:00'),
    (8, 8, 'alternance', '2026-09-01', '2027-08-31', '2026-11-30', 35, 0, 100, 'Développeur alternant', 'Alternant','120', 13.20, 2000.00, 'Alternant','active', '2026-08-28', 1, '2026-08-20 10:00:00', '2026-09-01 09:00:00'),
    (9, 3, 'CDI',   '2025-04-15', '2026-06-30', NULL,          28,    1, 80,   'Commerciale sédentaire (80 %)', 'Maîtrise', '280', 0, 2320.00, 'Maîtrise', 'ended', '2025-04-10', 1, '2025-04-01 10:00:00', '2026-06-30 17:00:00');

-- -----------------------------------------------------------------------------
-- 4. Congés : soldes annuels et demandes (les types sont créés par le seeder)
-- -----------------------------------------------------------------------------
INSERT INTO leave_balances (employee_id, leave_type_id, period_year, initial_days, acquired_days, taken_days, remaining_days)
SELECT e.id, lt.id, 2026,
    CASE lt.code WHEN 'CP' THEN 25 WHEN 'RTT' THEN 10 ELSE 0 END,
    CASE lt.code WHEN 'CP' THEN 25 WHEN 'RTT' THEN 10 WHEN 'MAL' THEN 90 ELSE 0 END,
    0,
    CASE lt.code WHEN 'CP' THEN 25 WHEN 'RTT' THEN 10 WHEN 'MAL' THEN 90 ELSE 0 END
FROM employees e
CROSS JOIN leave_types lt
WHERE lt.code IN ('CP', 'RTT', 'MAL', 'SS', 'FAM');

-- Quelques soldes entamés.
UPDATE leave_balances SET taken_days = 12, remaining_days = initial_days + acquired_days - 12
WHERE employee_id = 1 AND leave_type_id = (SELECT id FROM leave_types WHERE code = 'CP') AND period_year = 2026;
UPDATE leave_balances SET taken_days = 4, remaining_days = initial_days + acquired_days - 4
WHERE employee_id = 4 AND leave_type_id = (SELECT id FROM leave_types WHERE code = 'RTT') AND period_year = 2026;

INSERT INTO leave_requests
    (id, employee_id, leave_type_id, starts_on, ends_on, days_count, reason, status,
     reviewed_by, reviewed_at, review_note, created_by, created_at, updated_at)
VALUES
    (1, 1, (SELECT id FROM leave_types WHERE code = 'CP'), '2026-08-03', '2026-08-14', 10,
     'Congés d''été', 'approved', 5, '2026-06-20 15:30:00', 'Bonnes vacances !', 1, '2026-06-18 09:12:00', '2026-06-20 15:30:00'),
    (2, 1, (SELECT id FROM leave_types WHERE code = 'CP'), '2026-12-21', '2026-12-23', 3,
     'Fermeture de fin d''année', 'submitted', NULL, NULL, '', 1, '2026-10-20 11:00:00', '2026-10-20 11:00:00'),
    (3, 4, (SELECT id FROM leave_types WHERE code = 'RTT'), '2026-10-16', '2026-10-16', 1,
     'RTT pont', 'approved', 5, '2026-10-01 08:45:00', 'Accordé.', 4, '2026-09-28 17:20:00', '2026-10-01 08:45:00'),
    (4, 6, (SELECT id FROM leave_types WHERE code = 'MAL'), '2026-10-05', '2026-10-09', 5,
     'Arrêt maladie — arrêt de travail fourni', 'approved', 5, '2026-10-04 18:10:00', 'Justificatif reçu.', 6, '2026-10-04 17:55:00', '2026-10-04 18:10:00'),
    (5, 7, (SELECT id FROM leave_types WHERE code = 'FAM'), '2026-11-06', '2026-11-06', 1,
     'Mariage d''un proche', 'draft', NULL, NULL, '', 7, '2026-10-25 10:30:00', '2026-10-25 10:30:00'),
    (6, 3, (SELECT id FROM leave_types WHERE code = 'SS'), '2026-09-14', '2026-09-18', 5,
     'Congé sans solde refusé (chevauchement salon)', 'rejected', 5, '2026-09-05 14:00:00',
     'Période incompatible avec le salon client.', 3, '2026-09-02 09:40:00', '2026-09-05 14:00:00');

-- -----------------------------------------------------------------------------
-- 5. Paie : périodes, bulletins verrouillés / brouillons, lignes et DSN
-- -----------------------------------------------------------------------------
INSERT INTO payroll_periods (id, company_id, year, month, starts_on, ends_on, status, is_locked, closed_at)
VALUES
    (1, (SELECT id FROM companies WHERE is_default = 1 LIMIT 1), 2026, 9,  '2026-09-01', '2026-09-30', 'closed', 1, '2026-10-07 18:00:00'),
    (2, (SELECT id FROM companies WHERE is_default = 1 LIMIT 1), 2026, 10, '2026-10-01', '2026-10-31', 'open',   0, NULL);

INSERT INTO payslips
    (id, period_id, employee_id, contract_id, status, hours_worked, gross_salary, total_employee,
     total_employer, net_social, net_taxable, net_payable, general_reduction, is_paid,
     payment_date, generated_by, validated_by, validated_at, created_at, updated_at)
VALUES
    (1, 1, 1, 1, 'locked',    151.67, 3200.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 1, '2026-10-05', 2, 2, '2026-10-06 10:00:00', '2026-10-06 10:00:00', '2026-10-07 18:00:00'),
    (2, 1, 2, 2, 'locked',    151.67, 3600.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 1, '2026-10-05', 2, 2, '2026-10-06 10:05:00', '2026-10-06 10:05:00', '2026-10-07 18:00:00'),
    (3, 1, 3, 3, 'locked',    151.67, 2900.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 1, '2026-10-05', 2, 2, '2026-10-06 10:10:00', '2026-10-06 10:10:00', '2026-10-07 18:00:00'),
    (4, 1, 4, 4, 'locked',    151.67, 3100.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 1, '2026-10-05', 2, 2, '2026-10-06 10:15:00', '2026-10-06 10:15:00', '2026-10-07 18:00:00'),
    (5, 1, 5, 5, 'locked',    151.67, 3500.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 1, '2026-10-05', 2, 2, '2026-10-06 10:20:00', '2026-10-06 10:20:00', '2026-10-07 18:00:00'),
    (6, 2, 1, 1, 'draft',     151.67, 3200.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 0, NULL, 2, NULL, NULL, '2026-10-25 09:00:00', '2026-10-25 09:00:00'),
    (7, 2, 2, 2, 'draft',     151.67, 3600.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 0, NULL, 2, NULL, NULL, '2026-10-25 09:05:00', '2026-10-25 09:05:00'),
    (8, 2, 8, 8, 'draft',     151.67, 2000.00, 0.00, 0.00, 0.00, 0.00, 0.00, 0, 0, NULL, 2, NULL, NULL, '2026-10-25 09:10:00', '2026-10-25 09:10:00');

-- Lignes détaillées pour les bulletins verrouillés (reprises des règles seedées).
INSERT INTO payslip_lines (payslip_id, rule_code, label, base_amount, rate_employee, rate_employer, amount_employee, amount_employer, sort_order)
SELECT p.id, r.code, r.label,
    CASE r.base_type
        WHEN 'csg' THEN ROUND(p.gross_salary * 0.9825, 2)
        WHEN 'plafond' THEN (CASE WHEN r.ceiling_month IS NULL OR p.gross_salary <= r.ceiling_month THEN p.gross_salary ELSE r.ceiling_month END)
        WHEN 'brut_et_plafond' THEN (CASE WHEN r.ceiling_month IS NULL OR p.gross_salary <= r.ceiling_month THEN p.gross_salary ELSE r.ceiling_month END)
        ELSE p.gross_salary
    END AS base_amount,
    r.rate_employee, r.rate_employer,
    ROUND(
        CASE r.base_type
            WHEN 'csg' THEN ROUND(p.gross_salary * 0.9825, 2)
            WHEN 'plafond' THEN (CASE WHEN r.ceiling_month IS NULL OR p.gross_salary <= r.ceiling_month THEN p.gross_salary ELSE r.ceiling_month END)
            WHEN 'brut_et_plafond' THEN (CASE WHEN r.ceiling_month IS NULL OR p.gross_salary <= r.ceiling_month THEN p.gross_salary ELSE r.ceiling_month END)
            ELSE p.gross_salary
        END * r.rate_employee / 100, 2) AS amount_employee,
    ROUND(
        CASE r.base_type
            WHEN 'csg' THEN ROUND(p.gross_salary * 0.9825, 2)
            WHEN 'plafond' THEN (CASE WHEN r.ceiling_month IS NULL OR p.gross_salary <= r.ceiling_month THEN p.gross_salary ELSE r.ceiling_month END)
            WHEN 'brut_et_plafond' THEN (CASE WHEN r.ceiling_month IS NULL OR p.gross_salary <= r.ceiling_month THEN p.gross_salary ELSE r.ceiling_month END)
            ELSE p.gross_salary
        END * r.rate_employer / 100, 2) AS amount_employer,
    (ROW_NUMBER() OVER (PARTITION BY p.id ORDER BY r.id) - 1) AS sort_order
FROM payslips p
JOIN payroll_rule_versions r
  ON r.effective_from <= '2026-09-30' AND (r.effective_to IS NULL OR r.effective_to >= '2026-09-01')
WHERE p.period_id = 1;

-- Totaux des bulletins verrouillés calculés à partir de leurs lignes, comme le
-- fait PayrollService::buildLines (sommes des cotisations, net imposable sur
-- cotisations déductibles, net social = net à payer = brut - total salarié).
UPDATE payslips
SET total_employee = (
        SELECT ROUND(COALESCE(SUM(l.amount_employee), 0), 2)
        FROM payslip_lines l WHERE l.payslip_id = payslips.id
    ),
    total_employer = (
        SELECT ROUND(COALESCE(SUM(l.amount_employer), 0), 2)
        FROM payslip_lines l WHERE l.payslip_id = payslips.id
    ),
    net_taxable = (
        SELECT ROUND(payslips.gross_salary - COALESCE(SUM(
                   CASE WHEN r.is_deductible = 1 THEN l.amount_employee ELSE 0 END), 0), 2)
        FROM payslip_lines l
        JOIN payroll_rule_versions r
          ON r.code = l.rule_code
         AND r.effective_from <= '2026-09-30'
         AND (r.effective_to IS NULL OR r.effective_to >= '2026-09-01')
        WHERE l.payslip_id = payslips.id
    ),
    net_social = gross_salary - (
        SELECT ROUND(COALESCE(SUM(l.amount_employee), 0), 2)
        FROM payslip_lines l WHERE l.payslip_id = payslips.id
    ),
    net_payable = gross_salary - (
        SELECT ROUND(COALESCE(SUM(l.amount_employee), 0), 2)
        FROM payslip_lines l WHERE l.payslip_id = payslips.id
    )
WHERE period_id = 1;

INSERT INTO dsn_preparations (id, company_id, period_id, status, file_path, declared_at, notes, created_by)
VALUES
    (1, (SELECT id FROM companies WHERE is_default = 1 LIMIT 1), 1, 'exported',
     'storage/exports/dsn/D202609-PVS.xml', '2026-10-05 11:00:00',
     'DSN mensuelle septembre 2026 — contrôlée et transmise.', 2),
    (2, (SELECT id FROM companies WHERE is_default = 1 LIMIT 1), 2, 'prepared',
     NULL, NULL, 'Préparation octobre 2026 en cours, en attente de clôture de paie.', 2);

-- -----------------------------------------------------------------------------
-- 6. Trésorerie : comptes supplémentaires et mouvements bancaires
-- -----------------------------------------------------------------------------
INSERT INTO bank_accounts (id, company_id, label, bank_name, iban, bic, currency, initial_balance, current_balance, is_active)
VALUES
    (2, (SELECT id FROM companies WHERE is_default = 1 LIMIT 1), 'Compte courant', 'Crédit Agricole', 'FR7614706000427412345678907', 'AGRIFRPP847', 'EUR', 65000.00, 68450.75, 1),
    (3, (SELECT id FROM companies WHERE is_default = 1 LIMIT 1), 'Livret professionnel', 'Caisse d''Épargne', 'FR7611425002003456789012345', 'CEPAFRPP444', 'EUR', 15000.00, 15200.00, 1);

INSERT INTO bank_transactions
    (id, bank_account_id, transaction_date, value_date, label, amount, direction, reference,
     is_reconciled, reconciled_at, source_type, category)
VALUES
    (1, 1, '2026-10-03', '2026-10-03', 'Règlement facture F-2026-0004 — Nord Industrie SA', 4800.00, 'credit', 'VIR-2026-10-001', 1, '2026-10-05 10:00:00', 'manual', 'client'),
    (2, 1, '2026-10-04', '2026-10-04', 'Cotisations URSSAF septembre',
     -- Hors retraite complémentaire Agirc-Arrco, encaissée directement par la caisse de retraite.
     (SELECT ROUND(SUM(l.amount_employee + l.amount_employer), 2)
      FROM payslip_lines l
      JOIN payroll_rule_versions r
        ON r.code = l.rule_code
       AND r.effective_from <= '2026-09-30'
       AND (r.effective_to IS NULL OR r.effective_to >= '2026-09-01')
      WHERE r.category <> 'retraite_complementaire'),
     'debit', 'URSSAF-09-2026', 1, '2026-10-06 09:30:00', 'manual', 'cotisations'),
    (3, 1, '2026-10-07', '2026-10-07', 'Salaire net septembre — virements salariés', (SELECT ROUND(SUM(net_payable), 2) FROM payslips WHERE period_id = 1), 'debit', 'PAIE-09-2026', 1, '2026-10-07 18:00:00', 'manual', 'salaires'),
    (4, 2, '2026-10-10', '2026-10-10', 'Règlement facture F-2026-0005 — Méditerranée Conseil', 2880.00, 'credit', 'VIR-2026-10-005', 1, '2026-10-12 11:00:00', 'manual', 'client'),
    (5, 2, '2026-10-12', '2026-10-12', 'Facture Hexa Cloud — infogérance octobre', 1440.00, 'debit', 'HC-2026-10', 1, '2026-10-14 08:45:00', 'manual', 'fournisseur'),
    (6, 1, '2026-10-15', '2026-10-15', 'Loyer bureaux — Régie Immobilière du Port', 3200.00, 'debit', 'LOY-10-2026', 1, '2026-10-16 10:00:00', 'manual', 'loyer'),
    (7, 2, '2026-10-18', '2026-10-18', 'Acompte Boulangerie Lambert', 720.00, 'credit', 'AC-2026-10-018', 0, NULL, 'manual', 'client'),
    (8, 1, '2026-10-20', '2026-10-20', 'Achat fournitures — Papèterie Provençale', 312.50, 'debit', 'CB-2026-10-020', 0, NULL, 'manual', 'fournitures'),
    (9, 3, '2026-10-22', '2026-10-22', 'Intérêts trimestriels livret', 200.00, 'credit', 'INT-LIV-T4', 0, NULL, 'manual', 'produits_financiers'),
    (10, 2, '2026-10-24', '2026-10-24', 'Règlement facture F-2026-0007 — Atlantique Digital', 5040.00, 'credit', 'VIR-2026-10-010', 0, NULL, 'manual', 'client'),
    (11, 1, '2026-10-26', '2026-10-26', 'Note de frais remboursée — Sophie Martin', 118.20, 'debit', 'NF-2026-004', 0, NULL, 'manual', 'notes_frais'),
    (12, 1, '2026-10-27', '2026-10-27', 'Prélèvement électricité — Ouest Énergies', 486.30, 'debit', 'OE-10-2026', 0, NULL, 'manual', 'energie');

-- -----------------------------------------------------------------------------
-- 7. Comptabilité : exercice précédent, écritures équilibrées, TVA, immobilisations, budgets
-- -----------------------------------------------------------------------------
INSERT INTO fiscal_years (id, label, starts_on, ends_on, status, closed_at)
VALUES (2, 'Exercice 2025', '2025-01-01', '2025-12-31', 'closed', '2026-02-15 17:00:00');

INSERT INTO journal_entries
    (id, journal_id, fiscal_year_id, entry_date, reference, label, status, source_type, created_by, posted_at)
VALUES
    (1, (SELECT id FROM journals WHERE code = 'VT'), (SELECT id FROM fiscal_years WHERE label = 'Exercice 2026'), '2026-10-10', 'VT-10-001', 'Facture F-2026-0005 Méditerranée Conseil', 'posted', 'manual', 4, '2026-10-10 09:00:00'),
    (2, (SELECT id FROM journals WHERE code = 'BQ'), (SELECT id FROM fiscal_years WHERE label = 'Exercice 2026'), '2026-10-12', 'BQ-10-002', 'Encaissement Méditerranée Conseil', 'posted', 'manual', 4, '2026-10-12 11:05:00'),
    (3, (SELECT id FROM journals WHERE code = 'AC'), (SELECT id FROM fiscal_years WHERE label = 'Exercice 2026'), '2026-10-14', 'AC-10-003', 'Facture Hexa Cloud octobre', 'posted', 'manual', 4, '2026-10-14 08:50:00'),
    (4, (SELECT id FROM journals WHERE code = 'OD'), (SELECT id FROM fiscal_years WHERE label = 'Exercice 2026'), '2026-10-31', 'OD-10-004', 'Dotations aux amortissements octobre (provisoire)', 'draft', 'manual', 4, NULL),
    (5, (SELECT id FROM journals WHERE code = 'AN'), (SELECT id FROM fiscal_years WHERE label = 'Exercice 2025'), '2025-01-01', 'AN-2025', 'À-nouveaux 2025', 'posted', 'manual', 4, '2025-01-02 10:00:00');

INSERT INTO journal_entry_lines (entry_id, account_code, label, debit, credit, third_party)
VALUES
    (1, '411000', 'Clients — Méditerranée Conseil', 2880.00, 0.00, 'Méditerranée Conseil'),
    (1, '706000', 'Prestations de services', 0.00, 2400.00, 'Méditerranée Conseil'),
    (1, '445710', 'TVA collectée 20 %', 0.00, 480.00, 'Méditerranée Conseil'),
    (2, '512000', 'Banque — encaissement', 2880.00, 0.00, 'Méditerranée Conseil'),
    (2, '411000', 'Clients — Méditerranée Conseil', 0.00, 2880.00, 'Méditerranée Conseil'),
    (3, '615600', 'Locations infogérance', 1200.00, 0.00, 'Hexa Cloud SAS'),
    (3, '445660', 'TVA déductible 20 %', 240.00, 0.00, 'Hexa Cloud SAS'),
    (3, '401000', 'Fournisseurs — Hexa Cloud', 0.00, 1440.00, 'Hexa Cloud SAS'),
    (4, '641100', 'Salaires (provisoire)', (SELECT ROUND(SUM(net_payable), 2) FROM payslips WHERE period_id = 1), 0.00, ''),
    (4, '421000', 'Personnel — rémunérations dues', 0.00, (SELECT ROUND(SUM(net_payable), 2) FROM payslips WHERE period_id = 1), ''),
    (5, '512000', 'Banque — report à nouveau', 42000.00, 0.00, ''),
    (5, '101000', 'Capital social', 0.00, 42000.00, '');

INSERT INTO vat_declarations
    (id, period_label, starts_on, ends_on, collected_vat, deductible_vat, due_vat, status, file_path, submitted_at, created_by)
VALUES
    (1, 'Septembre 2026', '2026-09-01', '2026-09-30', 8420.00, 3915.50, 4504.50, 'paid',
     'storage/exports/tva/CA3-202609.pdf', '2026-10-18 10:30:00', 4),
    (2, 'Octobre 2026', '2026-10-01', '2026-10-31', 6180.00, 2740.20, 3439.80, 'draft', NULL, NULL, 4);

INSERT INTO fixed_assets
    (id, code, label, account_code, acquisition_date, acquisition_value, useful_life_years,
     residual_value, depreciation_mode, status, journal_entry_id)
VALUES
    (1, 'IMM-001', 'Ordinateurs développeurs (lot de 4)', '218300', '2025-04-12', 5600.00, 3, 0, 'linear', 'active', NULL),
    (2, 'IMM-002', 'Mobilier de bureau open-space', '218400', '2025-03-20', 3800.00, 5, 0, 'linear', 'active', NULL),
    (3, 'IMM-003', 'Serveur NAS de sauvegarde', '218300', '2026-02-05', 2400.00, 4, 0, 'linear', 'active', NULL);

-- Amortissements linéaires : annuité = valeur / durée, sur l'exercice courant.
INSERT INTO asset_depreciation_schedules (asset_id, fiscal_year_id, annuity, cumulative, net_book_value)
SELECT a.id, fy.id,
    ROUND(a.acquisition_value / a.useful_life_years, 2),
    ROUND(a.acquisition_value / a.useful_life_years, 2),
    ROUND(a.acquisition_value - (a.acquisition_value / a.useful_life_years), 2)
FROM fixed_assets a
JOIN fiscal_years fy ON fy.label = 'Exercice 2026';

INSERT INTO budgets (fiscal_year_id, account_code, period_year, period_month, category, planned_amount, actual_amount)
SELECT fy.id, b.account_code, 2026, b.period_month, b.category, b.planned, b.actual
FROM fiscal_years fy
JOIN (
    SELECT '615600' AS account_code, 10 AS period_month, 'Informatique' AS category, 1500.00 AS planned, 1440.00 AS actual
    UNION ALL SELECT '613200', 10, 'Immobilier', 3200.00, 3200.00
    UNION ALL SELECT '641100', 10, 'Masse salariale', 13500.00, (SELECT ROUND(SUM(net_payable), 2) FROM payslips WHERE period_id = 1)
    UNION ALL SELECT '606400', 10, 'Fournitures', 350.00, 312.50
) b ON fy.label = 'Exercice 2026';

INSERT INTO cash_forecasts (forecast_date, label, amount_in, amount_out, source)
VALUES
    ('2026-10-30', 'Échéance facture F-2026-0006 — Cabinet Argos', 1800.00, 0.00, 'manual'),
    ('2026-10-31', 'Paie octobre (estimation)', 0.00, 13200.00, 'manual'),
    ('2026-11-05', 'Prélèvement URSSAF octobre', 0.00, 12800.00, 'manual'),
    ('2026-11-10', 'Règlement Nord Industrie SA (60 jours)', 9600.00, 0.00, 'manual');

-- -----------------------------------------------------------------------------
-- 8. Facturation : factures, lignes, paiements, devis, avoirs, facturation récurrente, e-facturation
-- -----------------------------------------------------------------------------
INSERT INTO invoices
    (id, company_id, third_party_id, invoice_number, type, status, issue_date, due_date, currency,
     total_ht, total_vat, total_ttc, amount_paid, balance, electronic_status, electronic_reference, notes, created_by)
VALUES
    (1, (SELECT id FROM companies WHERE is_default = 1), 1, 'F-2026-0001', 'sale', 'paid',     '2026-07-08', '2026-08-07', 'EUR', 4200.00, 840.00, 5040.00, 5040.00, 0.00,    'acknowledged', 'PPF-2026-000911', 'Prestation juillet — soldée.', 4),
    (2, (SELECT id FROM companies WHERE is_default = 1), 2, 'F-2026-0002', 'sale', 'paid',     '2026-08-10', '2026-09-09', 'EUR', 2400.00, 480.00, 2880.00, 2880.00, 0.00,    'acknowledged', 'PPF-2026-001044', '', 4),
    (3, (SELECT id FROM companies WHERE is_default = 1), 3, 'F-2026-0003', 'sale', 'partial',  '2026-09-05', '2026-11-04', 'EUR', 8000.00, 1600.00, 9600.00, 3200.00, 6400.00, 'submitted', 'PPF-2026-001201', 'Acompte de 3 200 € encaissé.', 4),
    (4, (SELECT id FROM companies WHERE is_default = 1), 1, 'F-2026-0004', 'sale', 'paid',     '2026-09-28', '2026-10-28', 'EUR', 4000.00, 800.00, 4800.00, 4800.00, 0.00,    'generated', '', 'Règlement anticipé.', 4),
    (5, (SELECT id FROM companies WHERE is_default = 1), 2, 'F-2026-0005', 'sale', 'paid',     '2026-10-10', '2026-11-09', 'EUR', 2400.00, 480.00, 2880.00, 2880.00, 0.00,    'none', '', '', 4),
    (6, (SELECT id FROM companies WHERE is_default = 1), 5, 'F-2026-0006', 'sale', 'issued',   '2026-10-15', '2026-11-14', 'EUR', 1500.00, 300.00, 1800.00, 0.00, 1800.00, 'none', '', 'Échéance 30 jours.', 4),
    (7, (SELECT id FROM companies WHERE is_default = 1), 4, 'F-2026-0007', 'sale', 'overdue',  '2026-09-12', '2026-09-27', 'EUR', 600.00, 120.00, 720.00, 0.00, 720.00, 'rejected', 'PPF-2026-001332', 'Relance envoyée le 20/10.', 4),
    (8, (SELECT id FROM companies WHERE is_default = 1), 3, 'F-2026-0008', 'sale', 'draft',    '2026-10-27', '2026-11-26', 'EUR', 3000.00, 600.00, 3600.00, 0.00, 3600.00, 'none', '', 'En attente d''émission.', 4);

INSERT INTO invoice_lines (invoice_id, label, quantity, unit_price, vat_rate, discount_pct, line_ht, line_vat, line_ttc, account_code, sort_order)
VALUES
    (1, 'Développement spécifique (5 j)', 5, 800.00, 20.0, 0, 4000.00, 800.00, 4800.00, '706000', 0),
    (1, 'Support technique mensuel', 1, 200.00, 20.0, 0, 200.00, 40.00, 240.00, '706000', 1),
    (2, 'Audit organisation commerciale', 3, 800.00, 20.0, 0, 2400.00, 480.00, 2880.00, '706000', 0),
    (3, 'Licence logicielle (annuelle)', 10, 800.00, 20.0, 0, 8000.00, 1600.00, 9600.00, '706000', 0),
    (4, 'Maintenance évolutive (5 j)', 5, 800.00, 20.0, 0, 4000.00, 800.00, 4800.00, '706000', 0),
    (5, 'Conseil stratégie digitale', 3, 800.00, 20.0, 0, 2400.00, 480.00, 2880.00, '706000', 0),
    (6, 'Paramétrage poste vétérinaire', 6, 250.00, 20.0, 0, 1500.00, 300.00, 1800.00, '706000', 0),
    (7, 'Formation utilisateurs (2 sessions)', 2, 300.00, 20.0, 0, 600.00, 120.00, 720.00, '706000', 0),
    (8, 'Forfait intégration ERP', 1, 3000.00, 20.0, 0, 3000.00, 600.00, 3600.00, '706000', 0);

INSERT INTO payments (id, invoice_id, bank_account_id, paid_on, amount, method, reference, recorded_by)
VALUES
    (1, 1, 2, '2026-08-05', 5040.00, 'virement', 'VIR-2026-08-001', 4),
    (2, 2, 2, '2026-09-08', 2880.00, 'virement', 'VIR-2026-09-007', 4),
    (3, 3, 1, '2026-10-02', 3200.00, 'virement', 'VIR-2026-10-000', 4),
    (4, 4, 1, '2026-10-03', 4800.00, 'virement', 'VIR-2026-10-001', 4),
    (5, 5, 2, '2026-10-12', 2880.00, 'virement', 'VIR-2026-10-005', 4);

INSERT INTO quotes
    (id, company_id, third_party_id, quote_number, status, issue_date, valid_until, currency,
     total_ht, total_vat, total_ttc, notes, converted_invoice_id, created_by)
VALUES
    (1, (SELECT id FROM companies WHERE is_default = 1), 1, 'D-2026-0001', 'invoiced', '2026-06-20', '2026-07-20', 'EUR', 4200.00, 840.00, 5040.00, 'Transformé en facture F-2026-0001.', 1, 4),
    (2, (SELECT id FROM companies WHERE is_default = 1), 3, 'D-2026-0002', 'accepted', '2026-10-18', '2026-11-17', 'EUR', 9600.00, 1920.00, 11520.00, 'Devis accepté, facturation à planifier.', NULL, 4),
    (3, (SELECT id FROM companies WHERE is_default = 1), 5, 'D-2026-0003', 'sent',     '2026-10-24', '2026-11-23', 'EUR', 1500.00, 300.00, 1800.00, 'En attente de retour client.', NULL, 4),
    (4, (SELECT id FROM companies WHERE is_default = 1), 4, 'D-2026-0004', 'expired',  '2026-08-01', '2026-08-31', 'EUR', 900.00, 180.00, 1080.00, 'Validité dépassée, à relancer.', NULL, 4);

INSERT INTO quote_lines (quote_id, label, quantity, unit_price, vat_rate, discount_pct, line_ht, line_vat, line_ttc, sort_order)
VALUES
    (1, 'Développement spécifique (5 j)', 5, 800.00, 20.0, 0, 4000.00, 800.00, 4800.00, 0),
    (1, 'Support technique mensuel', 1, 200.00, 20.0, 0, 200.00, 40.00, 240.00, 1),
    (2, 'Licence logicielle (10 postes)', 10, 800.00, 20.0, 0, 8000.00, 1600.00, 9600.00, 0),
    (2, 'Formation administrateur', 2, 800.00, 20.0, 0, 1600.00, 320.00, 1920.00, 1),
    (3, 'Paramétrage poste vétérinaire', 6, 250.00, 20.0, 0, 1500.00, 300.00, 1800.00, 0),
    (4, 'Formation utilisateurs (3 sessions)', 3, 300.00, 20.0, 0, 900.00, 180.00, 1080.00, 0);

INSERT INTO credit_notes
    (id, company_id, third_party_id, invoice_id, credit_number, status, issue_date, currency,
     total_ht, total_vat, total_ttc, reason, created_by)
VALUES
    (1, (SELECT id FROM companies WHERE is_default = 1), 4, 7, 'A-2026-0001', 'issued', '2026-10-10', 'EUR',
     150.00, 30.00, 180.00, 'Avoir commercial — geste suite au retard de livraison.', 4);

INSERT INTO credit_note_lines (credit_note_id, label, quantity, unit_price, vat_rate, line_ht, line_vat, line_ttc, sort_order)
VALUES (1, 'Remise commerciale exceptionnelle', 1, 150.00, 20.0, 150.00, 30.00, 180.00, 0);

INSERT INTO e_invoice_outbox (id, invoice_id, format, platform, status, file_path, checksum_sha256, external_reference, error_message)
VALUES
    (1, 1, 'UBL', 'PPF', 'acknowledged', 'storage/einvoicing/F-2026-0001.xml', 'a1b2c3d4e5f60718293a4b5c6d7e8f90a1b2c3d4e5f60718293a4b5c6d7e8f90', 'PPF-2026-000911', ''),
    (2, 7, 'CII', 'PPF', 'rejected', 'storage/einvoicing/F-2026-0007.xml', 'b2c3d4e5f60718293a4b5c6d7e8f90a1b2c3d4e5f60718293a4b5c6d7e8f90a1', 'PPF-2026-001332', 'Contrôle de cohérence TVA : taux non applicable au client.');

INSERT INTO recurring_invoice_templates
    (id, company_id, third_party_id, label, frequency, next_run_on, lines_payload, is_active, last_run_on)
VALUES
    (1, (SELECT id FROM companies WHERE is_default = 1), 1, 'Maintenance mensuelle Atlantique', 'monthly', '2026-11-01',
     '[{"label":"Maintenance applicative mensuelle","quantity":1,"unit_price":800,"vat_rate":20}]', 1, '2026-10-01'),
    (2, (SELECT id FROM companies WHERE is_default = 1), 3, 'Licence trimestrielle Nord Industrie', 'quarterly', '2027-01-05',
     '[{"label":"Licence 10 postes (trimestre)","quantity":1,"unit_price":2400,"vat_rate":20}]', 1, '2026-10-05');

INSERT INTO document_sequences (kind, period, last_value)
VALUES
    ('invoice', '2026', 8),
    ('quote', '2026', 4),
    ('credit_note', '2026', 1);

-- -----------------------------------------------------------------------------
-- 9. Contrats clients
-- -----------------------------------------------------------------------------
INSERT INTO client_contracts
    (id, third_party_id, contract_number, title, starts_on, ends_on, renewal_mode, notice_days,
     amount_ht, billing_cycle, status, signed_on, notes, created_by)
VALUES
    (1, 1, 'CTR-2026-001', 'Forfait maintenance applicative annuel', '2026-01-01', '2026-12-31', 'tacit', 30,
     9600.00, 'yearly', 'active', '2025-12-15', 'Reconduction tacite au 1er janvier.', 1),
    (2, 3, 'CTR-2026-002', 'Licence ERP site de Lille', '2026-03-01', '2027-02-28', 'express', 60,
     28800.00, 'quarterly', 'active', '2026-02-20', 'Renouvellement exprès par avenant.', 1),
    (3, 5, 'CTR-2026-003', 'Hébergement données santé (sauvegarde)', '2026-06-01', NULL, 'tacit', 30,
     1800.00, 'monthly', 'active', '2026-05-24', 'Durée indéterminée.', 1),
    (4, 4, 'CTR-2026-004', 'Prestation ponctuelle migration caisse', '2026-09-15', '2026-10-31', 'none', 0,
     2400.00, 'one_shot', 'terminated', '2026-09-10', 'Soldé au 31/10/2026.', 1);

-- -----------------------------------------------------------------------------
-- 10. Abonnements : offres, souscriptions, campagne de facturation, MRR
-- -----------------------------------------------------------------------------
INSERT INTO subscription_plans (id, code, label, description, amount_ht, vat_rate, billing_cycle, is_active)
VALUES
    (1, 'STARTER',  'Offre Starter',  '1 poste, support ouvrés',          99.00,  20.0, 'monthly', 1),
    (2, 'BUSINESS', 'Offre Business', '5 postes, support étendu',        299.00, 20.0, 'monthly', 1),
    (3, 'GROUPE',   'Offre Groupe',  'Postes illimités, SLA 4 h',        899.00, 20.0, 'monthly', 1),
    (4, 'ANNUEL-20', 'Offre annuelle remisée', 'Engagement 12 mois',     2870.40, 20.0, 'yearly', 0);

INSERT INTO subscriptions
    (id, third_party_id, plan_id, client_contract_id, starts_on, ends_on, status, quantity,
     unit_amount_ht, vat_rate, auto_invoice, next_invoice_date, last_invoice_date)
VALUES
    (1, 1, 2, 1, '2026-01-01', NULL, 'active', 1, 299.00, 20.0, 1, '2026-11-01', '2026-10-01'),
    (2, 3, 3, 2, '2026-03-01', '2027-02-28', 'active', 2, 899.00, 20.0, 1, '2026-12-01', '2026-09-01'),
    (3, 5, 1, 3, '2026-06-01', NULL, 'paused', 1, 99.00, 20.0, 1, '2026-11-15', '2026-09-15'),
    (4, 2, 2, NULL, '2025-11-01', '2026-08-31', 'cancelled', 1, 299.00, 20.0, 1, '2026-08-01', '2026-08-01');

INSERT INTO subscription_invoice_runs (id, run_date, generated_count, total_ttc, details, created_by)
VALUES
    (1, '2026-10-01', 2, 2992.80,
     '[{"subscription_id":1,"invoice_id":5,"total_ttc":358.80},{"subscription_id":2,"invoice_id":6,"total_ttc":2157.60}]', 4);

INSERT INTO revenue_snapshots (snapshot_on, mrr, arr, active_subscriptions, churned)
VALUES
    ('2026-08-31', 2395.00, 28740.00, 3, 0),
    ('2026-09-30', 2195.00, 26340.00, 3, 1),
    ('2026-10-27', 2395.00, 28740.00, 2, 1);

-- -----------------------------------------------------------------------------
-- 11. Associés et dividendes
-- -----------------------------------------------------------------------------
INSERT INTO shareholders
    (id, company_id, third_party_id, entity_name, first_name, last_name, type, shares_count,
     share_pct, entry_date, exit_date)
VALUES
    (1, (SELECT id FROM companies WHERE is_default = 1), NULL, 'Prime Vision Holding', '', '', 'company',
     600, 60.00, '2025-01-10', NULL),
    (2, (SELECT id FROM companies WHERE is_default = 1), NULL, '', 'Amine', 'Ben Salah', 'individual',
     250, 25.00, '2025-01-10', NULL),
    (3, (SELECT id FROM companies WHERE is_default = 1), NULL, '', 'Claire', 'Fontaine', 'individual',
     150, 15.00, '2025-01-10', NULL);

INSERT INTO dividends
    (id, company_id, shareholder_id, fiscal_year_id, decided_on, gross_amount, flat_tax_rate, social_rate,
     tax_option, social_deductions, net_amount, status, paid_on, notes)
SELECT 1, c.id, 1, fy.id, '2026-05-15', 12000.00, 30.0, 17.2, 'pfuo', 2064.00, 9936.00, 'paid', '2026-06-10',
       'Acompte sur dividendes 2025.'
FROM companies c, fiscal_years fy
WHERE c.is_default = 1 AND fy.label = 'Exercice 2025'
UNION ALL
SELECT 2, c.id, 2, fy.id, '2026-05-15', 5000.00, 30.0, 17.2, 'pfuo', 860.00, 4140.00, 'decided', NULL,
       'Décidé en AG, mise en paiement à programmer.'
FROM companies c, fiscal_years fy
WHERE c.is_default = 1 AND fy.label = 'Exercice 2025'
UNION ALL
SELECT 3, c.id, 3, fy.id, '2026-10-27', 3000.00, 30.0, 17.2, 'pfuo', 516.00, 2484.00, 'proposed', NULL,
       'Projet de distribution exceptionnelle.'
FROM companies c, fiscal_years fy
WHERE c.is_default = 1 AND fy.label = 'Exercice 2026';

-- -----------------------------------------------------------------------------
-- 12. Notes de frais : rapports, lignes multi-catégories, workflow complet
-- -----------------------------------------------------------------------------
INSERT INTO expense_reports
    (id, employee_id, title, status, total_amount, submitted_at, approved_by, approved_at, paid_on, notes, created_at, updated_at)
VALUES
    (1, 3, 'Frais commerciaux — septembre 2026', 'paid', 214.30, '2026-10-02 09:00:00', 5, '2026-10-03 14:00:00', '2026-10-10', 'Remboursé sur paie d''octobre.', '2026-10-01 18:00:00', '2026-10-10 10:00:00'),
    (2, 1, 'Déplacement client Nantes — Rennes', 'approved', 156.80, '2026-10-21 16:30:00', 5, '2026-10-23 11:15:00', NULL, 'En attente de remboursement.', '2026-10-21 16:00:00', '2026-10-23 11:15:00'),
    (3, 6, 'Frais de formation', 'rejected', 89.00, '2026-10-18 10:00:00', NULL, NULL, NULL, 'Justificatif non conforme, à refaire.', '2026-10-17 15:00:00', '2026-10-19 09:00:00'),
    (4, 4, 'Achats fournitures comptables', 'submitted', 64.50, '2026-10-26 08:45:00', NULL, NULL, NULL, '', '2026-10-25 17:30:00', '2026-10-26 08:45:00'),
    (5, 7, 'Repas salon des entrepreneurs', 'draft', 42.00, NULL, NULL, NULL, NULL, 'Brouillon, ticket scanné à joindre.', '2026-10-27 09:20:00', '2026-10-27 09:20:00');

INSERT INTO expense_lines
    (report_id, spent_on, category, label, amount, vat_amount, distance_km, km_rate, receipt_path, ocr_data)
VALUES
    (1, '2026-09-08', 'meal', 'Déjeuner client Nord Industrie', 62.50, 6.25, 0, 0, 'storage/uploads/expenses/nf1-r1.pdf', '{}'),
    (1, '2026-09-15', 'transport', 'Train Nantes — Paris AR', 110.00, 10.00, 0, 0, 'storage/uploads/expenses/nf1-r2.pdf', '{}'),
    (1, '2026-09-22', 'supplies', 'Coffret cadeau client', 41.80, 4.18, 0, 0, 'storage/uploads/expenses/nf1-r3.pdf', '{}'),
    (2, '2026-10-19', 'kilometric', 'Aller-retour Nantes — Rennes (214 km)', 117.70, 0.00, 214, 0.55, '', '{"source":"bareme_2026"}'),
    (2, '2026-10-19', 'meal', 'Déjeuner sur place', 24.00, 2.40, 0, 0, 'storage/uploads/expenses/nf2-r1.pdf', '{}'),
    (2, '2026-10-20', 'fuel', 'Plein carburant', 15.10, 2.52, 0, 0, 'storage/uploads/expenses/nf2-r2.pdf', '{}'),
    (3, '2026-10-14', 'supplies', 'Ouvrage de formation (facture non libellée)', 89.00, 4.68, 0, 0, 'storage/uploads/expenses/nf3-r1.pdf', '{}'),
    (4, '2026-10-23', 'supplies', 'Classeurs et papier', 38.50, 7.70, 0, 0, 'storage/uploads/expenses/nf4-r1.pdf', '{}'),
    (4, '2026-10-24', 'supplies', 'Cartouches d''encre', 26.00, 5.20, 0, 0, 'storage/uploads/expenses/nf4-r2.pdf', '{}'),
    (5, '2026-10-26', 'meal', 'Deux repas salon', 42.00, 4.20, 0, 0, '', '{}');

-- -----------------------------------------------------------------------------
-- 13. Conformité : obligations légales et leurs traitements
-- -----------------------------------------------------------------------------
INSERT INTO compliance_obligations
    (code, title, authority, frequency, period_label, due_on, status, completed_at, notes)
VALUES
    ('DSN-2026-10', 'DSN mensuelle octobre 2026', 'URSSAF', 'monthly', 'Octobre 2026', '2026-11-05', 'pending', NULL, 'Dépôt via net-entreprises après clôture de paie.'),
    ('TVA-2026-09', 'Déclaration et paiement TVA septembre', 'DGFIP', 'monthly', 'Septembre 2026', '2026-10-18', 'done', '2026-10-18 10:30:00', 'CA3 télédeclarée, télépaiement validé.'),
    ('TVA-2026-10', 'Déclaration et paiement TVA octobre', 'DGFIP', 'monthly', 'Octobre 2026', '2026-11-18', 'pending', NULL, ''),
    ('URSSAF-2026-10', 'Paiement des cotisations URSSAF octobre', 'URSSAF', 'monthly', 'Octobre 2026', '2026-11-05', 'pending', NULL, 'Montant estimé 12 800 €.'),
    ('AG-2026', 'Assemblée générale ordinaire annuelle', 'greffe', 'yearly', 'Exercice 2025', '2026-06-30', 'done', '2026-06-20 15:00:00', 'Approbation des comptes et affectation du résultat.'),
    ('LIASSE-2025', 'Liasse fiscale et déclaration de résultat', 'DGFIP', 'yearly', 'Exercice 2025', '2026-05-03', 'done', '2026-04-28 16:45:00', 'Déposée par l''expert-comptable.'),
    ('MEDTRAV-2026', 'Visite médicale périodique collective', 'medecine_travail', 'event', 'Année 2026', '2026-12-01', 'pending', NULL, 'Planifier avec le service de santé au travail.'),
    ('RGPD-AUDIT', 'Revue annuelle registre des traitements RGPD', 'interne', 'continuous', 'Année 2026', '2026-12-15', 'overdue', NULL, 'Point de contrôle interne en retard.');

-- -----------------------------------------------------------------------------
-- 14. Centre documentaire (métadonnées ; les PDF sont régénérables via l'UI)
-- -----------------------------------------------------------------------------
INSERT INTO documents
    (id, name, original_name, disk, path, mime, size, category, entity_type, entity_id,
     visibility, checksum, uploaded_by)
VALUES
    (1, 'Facture F-2026-0001', 'facture-F-2026-0001.pdf', 'exports', 'facture/2026/facture-1-20261001090000.pdf', 'application/pdf', 4120, 'facture', 'invoice', 1, 'company', 'sha256-demo-0001', 4),
    (2, 'Facture F-2026-0004', 'facture-F-2026-0004.pdf', 'exports', 'facture/2026/facture-4-20261003101500.pdf', 'application/pdf', 4088, 'facture', 'invoice', 4, 'company', 'sha256-demo-0002', 4),
    (3, 'Contrat client CTR-2026-002', 'contrat-client-2.pdf', 'exports', 'contrat_client/2026/contrat-client-2-20260220110000.pdf', 'application/pdf', 5310, 'contrat_client', 'client_contract', 2, 'company', 'sha256-demo-0003', 1),
    (4, 'Bulletin de paie 09/2026 — Jean Dupont', 'bulletin-paie-1.pdf', 'exports', 'bulletin_paie/2026/bulletin-paie-1-20261006100000.pdf', 'application/pdf', 6408, 'bulletin_paie', 'payslip', 1, 'private', 'sha256-demo-0004', 2),
    (5, 'Fiche salarié — Amine Ben Salah', 'fiche-salarie-2.pdf', 'exports', 'fiche_employe/2026/fiche-employe-2-20261001093000.pdf', 'application/pdf', 3844, 'fiche_employe', 'employee', 2, 'private', 'sha256-demo-0005', 2),
    (6, 'Contrat de travail — Claire Fontaine', 'contrat-travail-5.pdf', 'exports', 'contrat_travail/2026/contrat-travail-5-20250225101500.pdf', 'application/pdf', 7102, 'contrat_travail', 'employment_contract', 5, 'private', 'sha256-demo-0006', 5),
    (7, 'Note de frais n°1 — Sophie Martin', 'note-frais-1.pdf', 'exports', 'note_frais/2026/note-frais-1-20261003140000.pdf', 'application/pdf', 3350, 'note_frais', 'expense_report', 1, 'company', 'sha256-demo-0007', 5),
    (8, 'Avoir A-2026-0001', 'avoir-A-2026-0001.pdf', 'exports', 'facture/2026/avoir-1-20261010120000.pdf', 'application/pdf', 2980, 'avoir', 'credit_note', 1, 'company', 'sha256-demo-0008', 4);

INSERT INTO document_versions (document_id, version, path, size, created_by)
VALUES
    (3, 1, 'contrat_client/2026/contrat-client-2-v1.pdf', 5280, 1),
    (3, 2, 'contrat_client/2026/contrat-client-2-v2.pdf', 5310, 1);

INSERT INTO employee_documents (employee_id, name, category, path, mime, size, uploaded_by)
VALUES
    (1, 'Pièce d''identité — Jean Dupont', 'identite', 'storage/uploads/rh/emp1-cni.pdf', 'application/pdf', 245000, 5),
    (2, 'Attestation Sécurité sociale', 'admin', 'storage/uploads/rh/emp2-attestation.pdf', 'application/pdf', 98000, 5),
    (6, 'Arrêt de travail octobre 2026', 'arret_maladie', 'storage/uploads/rh/emp6-arret.pdf', 'application/pdf', 176400, 5);

-- -----------------------------------------------------------------------------
-- 15. IA optionnelle : fournisseurs désactivés, conversation d'exemple
-- -----------------------------------------------------------------------------
UPDATE ai_provider_settings
SET url = 'http://127.0.0.1:11434', model = 'llama3.1:8b', temperature = 0.70, timeout = 30,
    system_prompt = 'Tu es un assistant de gestion d''entreprise française.', extra = '{}'
WHERE provider = 'ollama';
UPDATE ai_provider_settings
SET temperature = 0.20, timeout = 45, extra = '{}'
WHERE provider = 'codecraft';

INSERT INTO ai_conversations (id, user_id, title, provider)
VALUES (1, 1, 'Synthèse des échéances d''octobre', 'ollama');

INSERT INTO ai_messages (conversation_id, role, content, tokens)
VALUES
    (1, 'user', 'Quelles sont les échéances fiscales et sociales d''octobre 2026 ?', 24),
    (1, 'assistant', 'Les principales échéances sont la DSN et le paiement URSSAF le 5 novembre, ainsi que la TVA CA3 le 18 novembre. La note de frais n°4 est encore en attente de validation.', 61);

-- -----------------------------------------------------------------------------
-- 16. Notifications, journal d'audit, connexions, sécurité, paramètres
-- -----------------------------------------------------------------------------
INSERT INTO notifications (user_id, type, title, body, read_at, created_at)
VALUES
    (4, 'invoice.overdue', 'Facture en retard', 'La facture F-2026-0007 (Boulangerie Lambert) dépasse son échéance.', NULL, '2026-10-20 08:00:00'),
    (2, 'payroll.open', 'Période de paie ouverte', 'La période octobre 2026 est ouverte, les bulletins brouillons sont à valider.', NULL, '2026-10-25 07:30:00'),
    (5, 'leave.submitted', 'Demande de congés à traiter', 'Jean Dupont a soumis une demande de congés du 21 au 23 décembre.', NULL, '2026-10-20 11:05:00'),
    (1, 'compliance.overdue', 'Échéance de conformité en retard', 'La revue annuelle RGPD dépasse sa date prévue.', NULL, '2026-10-26 09:00:00');

INSERT INTO audit_logs (user_id, action, entity_type, entity_id, changes, ip_address, created_at)
VALUES
    (1, 'auth.login', 'User', 1, '{}', '127.0.0.1', '2026-10-27 08:05:12'),
    (4, 'invoice.issue', 'Invoice', 6, '{"status":"issued"}', '127.0.0.1', '2026-10-15 14:22:40'),
    (4, 'payment.create', 'Payment', 5, '{"amount":2880.00}', '127.0.0.1', '2026-10-12 11:06:03'),
    (2, 'payroll.payslip.validate', 'Payslip', 1, '{"status":"validated"}', '127.0.0.1', '2026-10-06 10:00:21'),
    (5, 'leave.approve', 'LeaveRequest', 3, '{"status":"approved"}', '127.0.0.1', '2026-10-01 08:45:07'),
    (1, 'role.update', 'Role', 2, '{"label":"Responsable RH"}', '127.0.0.1', '2026-09-30 16:10:55'),
    (5, 'expense.approve', 'ExpenseReport', 2, '{"status":"approved"}', '127.0.0.1', '2026-10-23 11:15:30'),
    (4, 'accounting.entry.create', 'JournalEntry', 3, '{"reference":"AC-10-003"}', '127.0.0.1', '2026-10-14 08:50:11');

INSERT INTO auth_login_logs (user_id, session_id, ip_address, user_agent, remember_me, logout_at, created_at)
VALUES
    (1, 'sess-demo-0001', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/130', 0, '2026-10-27 12:00:00', '2026-10-27 08:05:12'),
    (4, 'sess-demo-0002', '127.0.0.1', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14) Safari/17', 1, NULL, '2026-10-27 08:32:02'),
    (2, 'sess-demo-0003', '127.0.0.1', 'Mozilla/5.0 (X11; Linux x86_64) Firefox/131', 0, '2026-10-26 17:30:41', '2026-10-26 14:10:00'),
    (5, 'sess-demo-0004', '127.0.0.1', 'Mozilla/5.0 (Windows NT 10.0) Edge/130', 0, '2026-10-25 12:02:18', '2026-10-25 09:40:55');

INSERT INTO settings (group_name, key_name, value, is_encrypted)
VALUES
    ('company', 'currency', 'EUR', 0),
    ('company', 'invoice_prefix', 'F', 0),
    ('payroll', 'legal_monthly_hours', '151.67', 0),
    ('payroll', 'csg_base_ratio', '0.9825', 0),
    ('security', 'session_lifetime_minutes', '120', 0),
    ('security', 'rate_limit_per_minute', '60', 0);

-- Jeton de réinitialisation expiré (démonstration du flux mot de passe oublié).
INSERT INTO password_resets (user_id, token_hash, expires_at, used_at)
VALUES (6, 'reset-demo-hash-expire-2026-10-20', '2026-10-20 12:00:00', '2026-10-19 17:42:00');

INSERT INTO remember_tokens (user_id, token_hash, expires_at)
VALUES (4, 'remember-demo-hash-karim-2026-11', '2026-11-26 08:32:02');

-- Fin du jeu de démonstration.
