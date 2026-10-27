# Accès de démonstration — PBS (Prime Business Suite)

Tous les comptes ci-dessous sont créés par le script
[`database/demo/demo_data.sql`](../../database/demo/demo_data.sql), après
`migrate:run` + `db:seed`. Les mots de passe sont hachés en Argon2id dans la
base ; aucun mot de passe n'est stocké en clair.

**Mot de passe commun à tous les comptes de démonstration :**

```
PBS@2026!demo
```

## Comptes par rôle

| # | Rôle | Nom | E-mail de connexion | Périmètre |
| --- | --- | --- | --- | --- |
| 1 | Administrateur | (compte d'usine) | `admin@prime-vision-solutions.fr` | Tous les modules, gestion des rôles, audit, sauvegardes |
| 2 | Responsable RH | Claire Fontaine | `claire.fontaine@demo.fr` | Organisation, utilisateurs, salariés, congés, documents RH |
| 3 | Gestionnaire de paie | Marc Leroy | `marc.leroy@demo.fr` | Paie (calcul, clôture, DSN), salariés en lecture, congés |
| 4 | Comptable | Karim Benali | `karim.benali@demo.fr` | Comptabilité, trésorerie, facturation, tiers, frais, contrats, abonnements, dividendes, conformité |
| 5 | Salariée | Sophie Martin | `sophie.martin@demo.fr` | Portail self-service : mes congés, mes bulletins, mes notes de frais, mes documents |
| 6 | Comptable | Nathan Girard | `nathan.girard@demo.fr` | Même périmètre que Karim (permet de tester les droits identiques sur un second compte) |
| 7 | Multi-rôles RH + Paie | Ambre Dupuis | `ambre.dupuis@demo.fr` | Cumule les permissions des deux rôles (l'union des droits fait foi) |

> Le compte administrateur d'usine existe après `db:seed`, même sans charger le
> SQL de démonstration. Son mot de passe est défini à l'installation
> (documentation : `PBS@2026!admin` dans l'environnement de démo).

## Ce que chaque rôle voit et peut faire

### 1. Administrateur — `admin@prime-vision-solutions.fr`

- Toutes les sections du menu, y compris **Paramètres** :
  gestion des rôles et de la matrice de permissions, paramètres IA,
  piste d'audit, sauvegardes.
- Peut créer un rôle, modifier ses permissions ou le supprimer. La suppression
  d'un rôle encore attribué à un utilisateur est refusée (erreur 422
  « Impossible de supprimer un rôle attribué à N utilisateur(s)… »).
- Permet de tester le parcours complet : créer un rôle `commercial`, l'attribuer
  à un utilisateur, vérifier l'apparition immédiate des nouveaux onglets.

### 2. Responsable RH — `claire.fontaine@demo.fr`

- Menu visible : Tableau de bord, Entreprise, Utilisateurs, Employés,
  Congés, Documents, Mon espace.
- Crée et suit les salariés, les contrats de travail et les départements.
- Valide ou rejette les demandes de congés (6 demandes de démo dans tous les
  statuts), consulte les soldes.
- Accès aux documents RH déposés pour les salariés.
- **Sans accès** : paie, comptabilité, facturation, trésorerie — les onglets
  sont absents du menu et les URL renvoient une erreur 403.

### 3. Gestionnaire de paie — `marc.leroy@demo.fr`

- Menu visible : Tableau de bord, Paie, Employés (lecture), Congés,
  Documents, Mon espace.
- Génère les bulletins sur une période, vérifie les cotisations, clôture et
  verrouille la paie, prépare et dépose la DSN.
- La démo contient 2 périodes : septembre 2026 (verrouillée, 5 bulletins
  payés avec 14 lignes de cotisations chacun) et octobre 2026 (brouillon,
  3 bulletins non calculés) — idéal pour tester le calcul puis la clôture.
- **Sans accès** : écritures comptables, factures, banque, frais.

### 4. Comptable — `karim.benali@demo.fr` / `nathan.girard@demo.fr`

- Menu visible : Tableau de bord, Tiers, Facturation, Contrats, Abonnements,
  Trésorerie, Comptabilité, Notes de frais, Dividendes, Conformité, Documents,
  Mon espace.
- Facturation : 8 factures (brouillon à payée/impayée), 4 devis, 1 avoir,
  lettrage des 5 paiements, e-facturation, modèles récurrents.
- Comptabilité : 5 écritures équilibrées sur l'exercice 2025 clos, TVA,
  immobilisations et amortissements, budgets, prévisions de trésorerie.
- Trésorerie : 3 comptes, 12 mouvements, rapprochements.
- Valide et paie les notes de frais (5 notes dans tout le workflow).
- Suit la conformité (DSN, TVA, URSSAF, AG, liasse, RGPD, médecine du travail).
- **Sans accès** : administration des utilisateurs et des rôles, paie
  (la consultation des bulletins relève du portail salarié).

### 5. Salariée — `sophie.martin@demo.fr`

- Menu réduit au strict self-service : Tableau de bord, **Mes congés**,
  **Mes bulletins**, **Mes notes de frais**, **Mes documents**, Mon profil.
- Pose une demande de congé, suit son historique et ses soldes.
- Télécharge ses bulletins (bulletins verrouillés de septembre 2026).
- Crée une note de frais (frais kilométriques inclus), la soumet et suit son
  remboursement.
- Toute tentative d'accès direct à une URL d'administration
  (ex. `/users`, `/accounting/entries`) renvoie **403** : les onglets ne sont
  pas seulement cachés, les routes sont protégées côté serveur.

### 6. Multi-rôles — `ambre.dupuis@demo.fr` (RH + Paie)

- Démontre la combinaison de rôles : le menu et les permissions correspondent à
  l'union des droits RH et paie, sans duplication d'onglets.
- Permet de vérifier qu'un utilisateur peut recevoir plusieurs rôles depuis
  l'écran de gestion des utilisateurs.

## Procédure de connexion

1. Ouvrir l'application (en local : `https://localhost` avec FrankenPHP/Caddy,
   ou l'URL fournie par le `docker compose up`).
2. Saisir l'e-mail et le mot de passe du compte choisi.
3. Le tableau de bord s'affiche avec le menu filtré selon les permissions du
   rôle. Se déconnecter depuis le menu profil (en haut à droite) pour changer
   de compte.

## Créer un nouveau rôle pendant la démo

1. Se connecter en administrateur, ouvrir **Paramètres → Rôles**.
2. Renseigner le libellé (ex. `Commercial`) et cocher les permissions
   souhaitées (ex. `clients.*`, `invoicing.read`).
3. Enregistrer, puis ouvrir **Utilisateurs**, éditer un compte et lui attribuer
   ce rôle : les nouveaux onglets apparaissent à sa prochaine connexion.
4. Un rôle attribué ne peut pas être supprimé tant que l'attribution existe.

## Vérification rapide des cloisonnements

Matrice relevée sur l'application réelle (requêtes HTTP avec session
authentifiée, le 2026-10-27). 200 = page autorisée, 403 = refusé par la
permission serveur, 302 = redirection légitime (voir note).

| Chemin | admin | claire (RH) | marc (paie) | karim (comptable) | sophie (salariée) | ambre (RH+paie) |
| --- | --- | --- | --- | --- | --- | --- |
| `/settings/users` | 200 | 200 | 403 | 403 | 403 | 200 |
| `/settings/roles` | 200 | 200 | 403 | 403 | 403 | 200 |
| `/payroll` | 200 | 403 | 200 | 403 | 403 | 200 |
| `/hr/leaves` | 200 | 200 | 200 | 403 | 403 | 200 |
| `/accounting` | 200 | 403 | 403 | 200 | 403 | 403 |
| `/invoicing/invoices` | 200 | 403 | 403 | 200 | 403 | 403 |
| `/expenses` | 200 | 403 | 403 | 200 | 403 | 403 |
| `/treasury` | 200 | 403 | 403 | 200 | 403 | 403 |
| `/clients` | 200 | 403 | 403 | 200 | 403 | 403 |
| `/compliance` | 200 | 403 | 403 | 200 | 403 | 403 |
| `/portal` | 302 | 200 | 302 | 302 | 200 | 302 |

Note sur `/portal` : le portail salarié exige une fiche salarié dont l'e-mail
correspond au compte. C'est le cas de Claire, Sophie et Nathan (200). Les
comptes Marc, Karim, Ambre et l'administrateur n'ont pas de fiche salarié
rattachée dans le jeu de démo : l'application les redirige vers le tableau de
bord avec un avertissement explicite, au lieu d'une erreur. Pour rattacher un
compte, il suffit de renseigner le même e-mail sur la fiche salarié
(menu Employés).

Côté menu, le filtrage est identique : par exemple le tableau de bord de Sophie
ne contient que les liens `/dashboard`, `/portal`, `/documents` et `/profile`,
tandis que celui de Karim expose les sections métier (comptabilité,
facturation, trésorerie, frais, clients, contrats, abonnements, dividendes,
conformité) et jamais la gestion des utilisateurs.
