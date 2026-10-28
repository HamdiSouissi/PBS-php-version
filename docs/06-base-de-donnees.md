# 06 — Base de données, migrations et sauvegardes

## Choix technologique

MySQL 8.0.16+ (ou MariaDB 10.11+) est la **seule** base prise en charge en
production. Tous les accès passent par PDO avec requêtes préparées
paramétrées (`App\Core\Database\Connection`, driver `pdo_mysql` basé sur
mysqlnd) ; aucun ORM n'est utilisé. Le SQL reste explicite, dans les
repositories et les fichiers de migration.

Le driver SQLite n'existe que pour faire tourner la suite de tests sans
serveur (cf. guide 09) : un schéma miroir des migrations MySQL est maintenu
dans `tests/fixtures/schema_sqlite.sql`.

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
  (`CREATE TABLE IF NOT EXISTS`, `INSERT IGNORE`) ;
- syntaxe cible : InnoDB, `utf8mb4` / `utf8mb4_unicode_ci`, clés auto-incrémentées
  `BIGINT UNSIGNED AUTO_INCREMENT`, booléens `TINYINT(1)`, JSON natif ;
- les corrections de schéma passent par une **nouvelle** migration, jamais en
  modifiant une migration déjà jouée (voir la migration 011, correctif de
  longueur de colonne) ;
- les vues SQL sont versionnées dans les migrations 010 et 012.

> **Limite MySQL connue** : le DDL provoque un commit implicite. Si une
> migration échoue en cours, les objets déjà créés peuvent subsister : il faut
> corriger manuellement avant de relancer `migrate:run`.

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

Orchestrés par `DatabaseSeeder`, tous idempotents (upserts `INSERT IGNORE` /
`ON DUPLICATE KEY UPDATE`, générés par la méthode `Connection::upsert`) :

| Seeder | Contenu |
|--------|---------|
| `RbacSeeder` | 5 rôles, 46 permissions, utilisateur administrateur initial |
| `CompanySeeder` | Société par défaut |
| `ChartOfAccountsSeeder` | Plan comptable général |
| `PayrollRulesSeeder` | Règles URSSAF / Agirc-Arrco versionnées |
| `ReferenceSeeder` | Types de congés, réglage d'acquisition des congés et références transverses |

### Liaison salariés / comptes / documents (migration 016)

La migration `20261028_000016_employee_accounts.sql` ajoute :

- `employees.user_id` : compte de connexion rattaché à la fiche salarié
  (clé étrangère `users.id`, `ON DELETE SET NULL`) ;
- `documents.employee_id` : propriétaire salarié d'un document du centre
  documentaire (clé étrangère `employees.id`, `ON DELETE SET NULL`), permettant
  au portail salarié de ne présenter que ses bulletins, contrats et fiches.

Les PDF générés (bulletins de paie, contrats, fiches salarié) alimentent
`documents` avec `employee_id` renseigné et une visibilité `portal`.

## Bases de l'environnement de développement

| Base | Rôle |
|------|------|
| `pbs` | Base applicative locale |
| `pbs_test` | Base dédiée aux tests d'intégration sur MySQL (tronquée avant chaque test) |

Le nom de la base de test est surchargeable via la variable d'environnement
`PBS_TEST_DB` (défaut : `pbs_test`).

Création initiale (SQL) :

```sql
CREATE DATABASE pbs CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'pbs'@'%' IDENTIFIED BY 'changez_moi';
GRANT ALL PRIVILEGES ON pbs.* TO 'pbs'@'%';
CREATE DATABASE pbs_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON pbs_test.* TO 'pbs'@'%';
FLUSH PRIVILEGES;
```

## Sauvegardes

Le service `Modules\Compliance\Services\BackupService` produit :

1. un dump SQL cohérent via `mysqldump --single-transaction` (instantané InnoDB
   sans verrou long), compressé en gzip depuis PHP
   (`storage/backups/pbs-AAAAMMJJ-HHMMSS.sql.gz`) ;
2. une archive ZIP des documents générés (`uploads`, `documents`,
   `einvoicing`, `exports`) quand ils existent ;
3. une purge automatique selon `BACKUP_RETENTION_DAYS` (défaut 30 jours).

```bash
php bin/console backup:run
php bin/console backup:restore --file=pbs-20261027-003757.sql.gz
php bin/console backup:list
```

Prérequis : les binaires `mysqldump` et `mysql` dans le PATH (paquet
`default-mysql-client`, déjà présent dans l'image Docker ; fournis par
Laragon ou le client MySQL/MariaDB en installation native). Le mot de passe
est transmis par la variable `MYSQL_PWD`, jamais en argument de ligne de
commande. La restauration interrompt toute erreur (pas de `--force`) et
n'accepte que des fichiers résidant dans le dossier de sauvegarde configuré
(protection contre les chemins arbitraires).

### Sauvegarde manuelle d'urgence

```bash
mysqldump -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" \
  --single-transaction --routines --triggers --events "$DB_DATABASE" \
  | gzip > /tmp/pbs-manuel.sql.gz

# Restauration
gunzip -c /tmp/pbs-manuel.sql.gz | mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" "$DB_DATABASE"
```

### Recommandations d'exploitation

- sauvegarde quotidienne planifiée (cron, voir guide 03/12) + test mensuel de
  restauration sur une base jetable ;
- déporter les archives hors du serveur (stockage objet, NAS chiffré) ;
- figer une sauvegarde avant chaque migration de production ;
- les volumes Docker `mysqldata` et `storage` doivent être inclus dans la
  politique de sauvegarde de l'hôte.
