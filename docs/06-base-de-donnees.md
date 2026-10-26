# 06 — Base de données, migrations et sauvegardes

## Choix technologique

PostgreSQL est la **seule** base prise en charge en production. Tous les accès
passent par PDO avec requêtes préparées paramétrées (`App\Core\Database\Connection`) ;
aucun ORM n'est utilisé. Le SQL reste explicite, dans les repositories et les
fichiers de migration.

## Migrations SQL versionnées

Les scripts sont dans `database/migrations/`, nommés
`AAAAMMJJ_NNNNNN_description.sql`, et joués dans l'ordre lexical. La table de
piste `schema_migrations` garantit qu'un script n'est exécuté qu'une fois.

```bash
php bin/console migrate:status   # état des migrations
php bin/console migrate:run      # appliquer les migrations en attente
```

Les migrations sont aussi exécutées automatiquement par l'entrypoint Docker
au démarrage du conteneur applicatif.

Conventions :

- une migration = un changement cohérent et idempotent quand c'est possible
  (`CREATE TABLE IF NOT EXISTS`, `ON CONFLICT DO NOTHING`) ;
- les corrections de schéma passent par une **nouvelle** migration, jamais en
  modifiant une migration déjà jouée (voir la migration 011, correctif de
  longueur de colonne) ;
- les vues SQL sont versionnées dans la migration 010.

### Rollback — limite actuelle

La commande existe :

```bash
php bin/console migrate:rollback
```

Mais elle nécessite un script `*.down.sql` associé à chaque migration. Ces
scripts ne sont pas encore fournis : la commande échoue alors explicitement
avec `Aucun script down pour <migration>` plutôt que de produire un résultat
trompeur. La fourniture des 12 scripts `down` est une dette identifiée ;
en attendant, le retour arrière s'appuie sur les sauvegardes décrites ci-dessous.

## Seeders (données de référence)

```bash
php bin/console db:seed
```

Orchestrés par `DatabaseSeeder`, tous idempotents :

| Seeder | Contenu |
|--------|---------|
| `RbacSeeder` | 5 rôles, 45 permissions, utilisateur administrateur initial |
| `CompanySeeder` | Société par défaut |
| `ChartOfAccountsSeeder` | Plan comptable général |
| `PayrollRulesSeeder` | Règles URSSAF / Agirc-Arrco versionnées |
| `ReferenceSeeder` | Types de congés et références transverses |

## Bases de l'environnement de développement

| Base | Rôle |
|------|------|
| `pbs` | Base applicative locale |
| `pbs_test` | Base dédiée aux tests d'intégration (tronquée avant chaque test) |

Le nom de la base de test est surchargeable via la variable d'environnement
`PBS_TEST_DB` (défaut : `pbs_test`).

## Sauvegardes

Le service `Modules\Compliance\Services\BackupService` produit :

1. un dump SQL cohérent via `pg_dump`, compressé en gzip depuis PHP
   (`storage/backups/pbs-AAAAMMJJ-HHMMSS.sql.gz`) ;
2. une archive ZIP des documents générés (`uploads`, `documents`,
   `einvoicing`, `exports`) quand ils existent ;
3. une purge automatique selon `BACKUP_RETENTION_DAYS` (défaut 30 jours).

```bash
php bin/console backup:run
php bin/console backup:restore --file=pbs-20261027-003757.sql.gz
```

Prérequis : les binaires `pg_dump` et `psql` dans le PATH (paquet
`postgresql-client`, déjà présent dans l'image Docker). La restauration
s'exécute avec `ON_ERROR_STOP=1` et n'accepte que des fichiers résidant dans
le dossier de sauvegarde configuré (protection contre les chemins arbitraires).

### Sauvegarde manuelle d'urgence

```bash
pg_dump -h "$DB_HOST" -U "$DB_USERNAME" -d "$DB_DATABASE" --clean --if-exists \
  | gzip > /tmp/pbs-manuel.sql.gz

# Restauration
gunzip -c /tmp/pbs-manuel.sql.gz | psql -h "$DB_HOST" -U "$DB_USERNAME" -d "$DB_DATABASE"
```

### Recommandations d'exploitation

- sauvegarde quotidienne planifiée (cron, voir guide 03) + test mensuel de
  restauration sur une base jetable ;
- déporter les archives hors du serveur (stockage objet, NAS chiffré) ;
- figer une sauvegarde avant chaque migration de production ;
- les volumes Docker `pgdata` et `storage` doivent être inclus dans la politique
  de sauvegarde de l'hôte.
