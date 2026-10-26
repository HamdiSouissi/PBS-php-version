# Documentation Prime Business Suite (PBS)

ERP modulaire en PHP 8.4 natif orienté objet, sans framework backend, pour
Prime Vision Solutions. PostgreSQL exclusif via PDO (aucun ORM).

## Guides

| # | Guide | Public |
|---|-------|--------|
| 01 | [Démarrage rapide (Docker)](01-demarrage-rapide.md) | Tous |
| 02 | [Installation sous Windows](02-installation-windows.md) | Développeurs Windows |
| 03 | [Installation sous Linux](03-installation-linux.md) | Développeurs Linux |
| 04 | [Configuration (.env)](04-configuration.md) | Tous |
| 05 | [Docker avancé et production](05-docker-production.md) | DevOps |
| 06 | [Base de données, migrations et sauvegardes](06-base-de-donnees.md) | DevOps / DBA |
| 07 | [Authentification et RBAC](07-authentification-rbac.md) | Développeurs |
| 08 | [Architecture et modules](08-architecture-modules.md) | Développeurs |
| 09 | [Tests](09-tests.md) | Développeurs / QA |
| 10 | [API REST / JWT](10-api-rest.md) | Intégrateurs |
| 11 | [Sécurité et conformité](11-securite-conformite.md) | RSSI / DPO |
| 12 | [Exploitation et maintenance](12-exploitation-maintenance.md) | DevOps |

## Limites connues et déclarations de conformité

PBS est un produit en construction. Les limites suivantes sont **assumées et
documentées**, jamais masquées dans l'interface :

- **Facturation électronique** : PBS génère localement des fichiers UBL 2.1
  (EN 16931) et CII réels, avec empreinte SHA-256. Il n'effectue **aucune
  télétransmission** vers le Portail Public de Facturation (PPF), une PDP ou
  Chorus Pro : aucun accusé de réception n'est fabriqué. Le dépôt et la preuve
  de transmission restent externes.
- **DSN (Déclaration Sociale Nominative)** : les structures de préparation
  existent mais la génération normative et le dépôt Net-Entreprises ne sont pas
  implémentés dans la version courante.
- **Paie** : le moteur de règles est versionné, mais les bulletins produits
  doivent être validés par un spécialiste paie avant tout usage réel.
- **Rollback de migration** : la commande `migrate:rollback` existe mais les
  scripts `*.down.sql` ne sont pas encore fournis (la commande signale
  explicitement l'absence du script au lieu d'échouer silencieusement).
