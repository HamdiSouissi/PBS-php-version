# Prime Business Suite (PBS)

ERP intégré modulaire pour **Prime Vision Solutions**, développé en **PHP 8.4
natif orienté objet, sans framework backend**, avec MySQL 8 / MariaDB exclusif
via PDO (aucun ORM). Interface web HTML5/CSS3/JavaScript ES2024 avec Bootstrap 5,
et API REST JSON authentifiée par JWT.

## Démarrage en 3 commandes (Docker)

```bash
cp .env.example .env          # ajuster DB_PASSWORD et DB_ROOT_PASSWORD
docker compose up -d --build  # PHP-FPM 8.4 + Nginx + MySQL 8.4 (+ Redis optionnel)
# http://localhost:8080 — migrations et seeders exécutés automatiquement
```

Premier compte : `admin@prime-vision-solutions.fr` / `PBS@2026!admin`
(à changer immédiatement).

Sonde de santé : `GET /health`.

## Modules

| Module | État |
|--------|------|
| Authentication | Sessions, JWT, remember me, reset mot de passe, TOTP 2FA, RBAC, CSRF, rate limiting, journal de connexion |
| Organization | Société, établissements, départements, journal d'audit |
| Employees | Fiches, contrats (unicité du contrat actif), portail salarié |
| HR | Congés, workflow de validation, soldes, jours ouvrés |
| Invoicing | Factures, devis, avoirs, facturation récurrente, TVA, e-facturation UBL/CII, API REST |
| Payroll, Accounting, Treasury, Contracts, Subscriptions, Expenses, Dividends, Compliance, Documents, AI | Socle SQL posé (tables, vues, seeders) ; développements en cours |

## Documentation

Tous les guides sont en français dans [`docs/`](docs/README.md) :

démarrage rapide, installation Windows et Linux, configuration, Docker
production, base de données, authentification/RBAC, architecture, tests,
API REST (spécification OpenAPI dans [`docs/openapi.yaml`](docs/openapi.yaml)),
sécurité/conformité et exploitation.

## Tests

```bash
php bin/console test:run unit
```

Suite actuelle : **35 tests, 35 réussis** (sécurité JWT/TOTP, validation,
cycle RH et contrats, TVA et cycle complet de facturation, génération
UBL/CII). La suite par défaut s'exécute sur SQLite (schéma miroir des
migrations MySQL, aucun serveur requis) ; elle peut aussi cibler une base
MySQL dédiée `pbs_test`, tronquée et reseedée entre chaque test.

## Limites assumées

- **E-facturation** : génération locale de fichiers UBL 2.1 / CII réels, sans
  télétransmission PPF/PDP/Chorus Pro (aucun accusé fabriqué).
- **DSN** : structures de préparation présentes, génération normative et dépôt
  non implémentés.
- **Paie / comptabilité** : règles versionnées mais validation obligatoire par
  un spécialiste paie et un expert-comptable avant usage réel.
- **Rollback SQL** : commande disponible, scripts `*.down.sql` en cours de
  rédaction ; le retour arrière s'appuie sur les sauvegardes `backup:run`.

## Sécurité

Aucun secret dans le dépôt (`.env`, clés, bulletins, données bancaires exclus
par `.gitignore`). Requêtes préparées, CSRF, en-têtes de sécurité, limitation
de débit, RBAC granulaire et journal d'audit. Voir
[`docs/11-securite-conformite.md`](docs/11-securite-conformite.md).
