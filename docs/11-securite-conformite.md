# 11 — Sécurité et conformité

## Protections intégrées

| Risque | Mesure dans PBS |
|--------|-----------------|
| Injection SQL | Requêtes PDO préparées paramétrées partout ; aucun identifiant concaténé |
| CSRF | Jeton synchronisateur par session, middleware `csrf` sur toutes les écritures web |
| XSS | Échappement systématique des sorties (`e()`), en-têtes de sécurité |
| Fixation de session | Régénération d'identifiant à la connexion, mode strict |
| Vol de cookie | `HttpOnly`, `SameSite=Lax`, mode strict ; `Secure` en HTTPS |
| Force brute | Rate limiting IP (60/min) et plafond dédié de 5 tentatives de connexion |
| Élévation de privilèges | RBAC middleware par permission, contrôle serveur sur chaque route |
| Fuite de secrets | `.env` et clés hors Git ; clés IA jamais transmises au navigateur |
| Téléversements | Taille et types contrôlés (`config/security.php`) |
| En-têtes | `X-Frame-Options: DENY`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy`, CSP |

## Gestion des secrets

- `APP_KEY` : chiffrement applicatif (base64, 32 octets), généré par
  `php bin/console app:key-generate` ;
- `JWT_SECRET` : signature HMAC des jetons d'API ;
- le fichier `.env` est ignoré par Git (vérifier `.gitignore`) ;
- les journaux ne contiennent ni mots de passe, ni jetons, ni données bancaires
  complètes ;
- en production, préférer l'injection d'environnement par l'orchestrateur aux
  fichiers `.env` sur disque.

## Traçabilité

- `audit_logs` : actions sensibles (factures, avoirs, contrats, congés,
  sauvegardes, e-facturation) avec utilisateur, entité et différences ;
- `auth_login_logs` : connexions, durées de session, jetons remember-me ;
- interface dédiée : `Paramètres → Journal d'audit` (permission `audit.read`).

## Données personnelles et RGPD

Les modules RH et paie manipulent des données à risque (état civil, NIR,
rémunérations). Règles appliquées :

- accès réservé par rôles (`rh`, `paie`, `admin`) ;
- NIR et données sensibles non exposés par l'API publique ;
- documents générés (bulletins, contrats) stockés hors dépôt Git, dans le
  volume `storage` sauvegardé et à accès restreint ;
- droit à l'effacement : traité par procédure administrée (les obligations
  comptables et sociales imposent des durées de rétention) ;
- les sauvegardes contiennent des données personnelles : chiffrer le stockage
  de sauvegarde et restreindre leur accès.

## Limites réglementaires assumées

### Facturation électronique (réforme 2026-2027)

PBS **génère** localement des documents UBL 2.1 (EN 16931) et CII UN/CEFACT
valides, avec empreinte SHA-256 et boîte d'envoi tracée (`e_invoice_outbox`).
Il **ne télétransmet pas** au PPF, à une PDP ou à Chorus Pro : aucun accusé de
réception n'est fabriqué. Le statut maximal en autonomie est `exported`
(dépôt manuel par un opérateur habilité). La preuve de transmission et le
statut `acknowledged` restent externes.

### DSN

Les tables de préparation (`dsn_preparations`) existent mais la génération
normative et le dépôt Net-Entreprises ne sont pas implémentés dans la version
courante. Aucun écran ne prétend le contraire.

### Paie

Le moteur calcule à partir de règles versionnées, mais les bulletins doivent
être contrôlés par un **spécialiste paie** et la conformité URSSAF/Agirc-Arrco
validée par un expert avant première paie réelle. Les taux sont figés dans des
seeders datés et doivent être révisés à chaque changement législatif.

### Comptabilité

Le plan comptable et les écritures suivent le PCG, mais la clôture et les
déclarations (TVA, liasse) nécessitent la relecture d'un **expert-comptable**.

## Liste de vérification mise en production

- [ ] `APP_ENV=production`, `APP_DEBUG=false`
- [ ] `APP_KEY` et `JWT_SECRET` régénérés, forts, hors Git
- [ ] Mot de passe administrateur initial changé
- [ ] HTTPS terminé en frontal, cookies `Secure`
- [ ] `DB_PASSWORD` fort, utilisateur MySQL applicatif sans privilège SUPER
- [ ] Rate limiting activé, journalisation centralisée
- [ ] Sauvegardes planifiées et **test de restauration** réalisé
- [ ] Volumes `mysqldata` et `storage` inclus dans la politique de sauvegarde
- [ ] Accès restreint aux fichiers `storage/` (permissions 770, propriétaire applicatif)
- [ ] Revue des permissions des rôles après création des utilisateurs réels
