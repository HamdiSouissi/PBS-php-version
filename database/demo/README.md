# Jeu de données de démonstration

Script : [`demo_data.sql`](./demo_data.sql) — SQL de démonstration en DML pur
(aucun DDL), valide sur **MySQL 8.0+ / MariaDB 10.2+** (la fonction fenêtre
`ROW_NUMBER()` l'exige) et sur **SQLite** (utilisé par la suite de tests).
Toutes les tables métier reçoivent des données fictives cohérentes (société
Prime Vision Solutions, 8 salariés, 10 tiers, factures, bulletins de paie
détaillés dont les totaux sont calculés à partir des règles de cotisations
ensemencées, écritures comptables équilibrées, abonnements, notes de frais,
contrats, dividendes, conformité…).

## Prérequis

Le script suppose une base **fraîchement migrée et ensemencée** :

```bash
php bin/console migrate:run
php bin/console db:seed
```

Les seeders créent le référentiel indispensable (société par défaut, plan
comptable général, journaux, types de congés, règles URSSAF/Agirc-Arrco,
rôles et permissions, compte administrateur). Le script de démonstration se
branche sur ces données via des clés naturelles (codes de rôle, de journal,
de compte, d'exercice) et ne dépend d'aucun identifiant figé.

> **Le script n'est pas ré-exécutable :** chaque ligne utilise des identifiants
> explicites. Si un premier chargement échoue (ou est interrompu), les
> premières tables sont déjà peuplées et une seconde exécution renverrait des
> erreurs de clé dupliquée. Dans ce cas, réinitialisez la base comme indiqué
> plus bas avant de relancer.

## Installation — Docker

```bash
# 1. Démarrer MySQL et l'application
docker compose up -d

# 2. Migrations et données de référence
docker compose exec app php bin/console migrate:run
docker compose exec app php bin/console db:seed

# 3. Charger le jeu de démonstration
docker compose exec -T app mysql \
  --default-character-set=utf8mb4 \
  -h"$DB_HOST" -u"$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" \
  < database/demo/demo_data.sql
```

## Installation — Laragon / Windows

1. Créer une base `pbs` dans phpMyAdmin (ou HeidiSQL), charset `utf8mb4`.
2. Renseigner `.env` (copie de `.env.example`) avec les identifiants MySQL
   locaux, puis :

```bat
php bin/console migrate:run
php bin/console db:seed
```

3. Importer `database/demo/demo_data.sql` via phpMyAdmin (onglet **Importer**)
   ou en ligne de commande (le flag charset garantit la bonne conservation des
   accents, du symbole € et des tirets cadratins) :

```bat
mysql --default-character-set=utf8mb4 -u root -p pbs < database\demo\demo_data.sql
```

## Réinitialiser la démo

Le script est conçu pour une base neuve. **C'est la procédure à suivre si un
chargement précédent a échoué**, par exemple avec une erreur de syntaxe : le
chargement partiel reste inscrit dans la base et doit être supprimé.

```bash
mysql -u root -p -e "DROP DATABASE IF EXISTS pbs; CREATE DATABASE pbs CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
php bin/console migrate:run
php bin/console db:seed
mysql --default-character-set=utf8mb4 -u root -p pbs < database/demo/demo_data.sql
```

Sous Windows, remplacer la dernière ligne par :

```bat
mysql --default-character-set=utf8mb4 -u root -p pbs < database\demo\demo_data.sql
```

## Contrôles intégrés au jeu de données

- Aucune violation de clé étrangère (vérifié sur base SQLite vierge,
  `PRAGMA foreign_key_check`).
- Écritures comptables strictement équilibrées débit/crédit.
- Totaux HT/TVA/TTC des factures, devis et avoirs égaux à la somme de leurs
  lignes ; soldes et encours cohérents avec les paiements.
- Totaux des notes de frais égaux à la somme de leurs lignes.
- Bulletins verrouillés accompagnés de leurs 14 lignes de cotisations ; les
  totaux d'en-tête (cotisations salarié/patronal, net imposable, net à payer)
  sont recalculés à partir de ces lignes, exactement comme le fait
  `PayrollService`, et le virement de salaires, l'écriture comptable de paie,
  le budget associé et le prélèvement URSSAF dérivent tous de ces montants par
  sous-requêtes — aucune chaîne de chiffres ne peut diverger.
- Mots de passe hachés en Argon2id (aucun mot de passe en clair).

### Note d'arrondi sur SQLite

Sur SQLite (uniquement utilisé pour les tests), la fonction `ROUND()` peut
arrondir un demi-cent exact dans le sens contraire de MySQL/MariaDB et de PHP
(1 cas connu, 1 centime sur une ligne CRDS). La base reste parfaitement
cohérente avec elle-même sur chaque moteur ; seules les valeurs lues côte à
côte entre SQLite et MySQL diffèrent de ce centime. Sur MySQL 8/MariaDB, les
montants sont identiques à ceux que calcule l'application.

## Contenu par module

| Module | Données injectées |
| --- | --- |
| Authentification / RBAC | 6 comptes de démo + administrateur d'usine, attributions de rôles (dont un multi-rôle), journal de connexion, jetons |
| Organisation | 5 départements, 8 salariés, 9 contrats de travail (CDI, CDD, alternance, contrat clos) |
| RH / congés | soldes annuels, 6 demandes dans tous les statuts |
| Paie | 2 périodes, 8 bulletins (verrouillés payés et brouillons), 70 lignes de cotisations, 2 préparations DSN |
| Tiers | 6 clients, 4 fournisseurs, 1 tiers mixte |
| Facturation | 8 factures (brouillon à payée/impayée), 4 devis, 1 avoir, 5 paiements, 2 fichiers e-facturation, 2 modèles récurrents |
| Comptabilité | exercice 2025 clos, 5 écritures et 12 lignes, 2 déclarations TVA, 3 immobilisations et amortissements, budgets, prévisions de trésorerie |
| Trésorerie | 3 comptes bancaires, 12 mouvements, rapprochements |
| Contrats / abonnements | 4 contrats clients, 4 offres, 4 abonnements, campagne de facturation, snapshots MRR/ARR |
| Frais | 5 notes dans tout le workflow, 10 lignes (7 catégories) |
| Dividendes | 3 associés, 3 distributions (proposée, décidée, payée) |
| Conformité | obligations légales sur tous les organismes et statuts |
| Documents | métadonnées documentaires (les PDF sont régénérables depuis l'application), versions, documents RH |
| IA | fournisseurs désactivés + conversation d'exemple |
| Système | notifications, piste d'audit, paramètres |

> Les lignes du centre documentaire décrivent des fichiers (chemins, tailles,
> sommes de contrôle) ; les PDF correspondants se régénèrent en un clic depuis
> chaque fiche (bouton « Générer le PDF » / « Régénérer »).
