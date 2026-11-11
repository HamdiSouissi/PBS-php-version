# 07 — Authentification, sécurité des sessions et RBAC

## Mécanismes disponibles

| Mécanisme | Usage | Implémentation |
|-----------|-------|----------------|
| Session navigateur + cookie | Interface web | `SessionManager`, cookie HttpOnly/SameSite |
| CSRF par jeton synchronisateur | Formulaires web | `App\Core\Security\Csrf`, middleware `csrf` |
| JWT Bearer | API REST `/api/v1` | `Jwt` (HS256), jetons accès + rafraîchissement |
| « Se souvenir de moi » | Connexion persistante | jetons hashés en base, 30 jours |
| Réinitialisation mot de passe | E-mail + jeton à durée limitée | `PasswordResetService` |
| TOTP 2FA | Validation en deux étapes | `App\Core\Security\Totp` (RFC 6238, Base32 RFC 4648) |
| Rate limiting | Bruteforce et abus | `RateLimitMiddleware`, 5 tentatives de connexion |
| Journal de connexion | Traçabilité | table `auth_login_logs` |

## Flux de connexion web

1. GET `/login` → formulaire avec champ `_csrf` ;
2. POST identifiants → contrôle de taux, vérification du mot de passe
   (`password_hash`/`password_verify`, algo PHP par défaut) ;
3. si 2FA activée, seconde étape TOTP ;
4. session ouverte, empreinte dans `auth_login_logs` ;
5. régénération de l'identifiant de session pour prévenir la fixation.

## API JWT

```bash
# Obtention (jetons d'accès 1 h et de rafraîchissement 14 j)
curl -X POST http://localhost:8080/api/v1/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"email":"admin@prime-vision-solutions.fr","password":"********"}'

# Utilisation
curl http://localhost:8080/api/v1/invoices \
  -H "Authorization: Bearer $JETON"

# Rafraîchissement
curl -X POST http://localhost:8080/api/v1/auth/refresh \
  -H 'Content-Type: application/json' -d '{"refresh_token":"..."}'
```

Le middleware `JwtAuthMiddleware` hydrate l'utilisateur **avec ses rôles et
permissions** via `UserRepository::findWithRights()` ; aucune route protégée ne
repose sur un utilisateur sans droits chargés.

## RBAC granulaire

### Rôles livrés

| Rôle | Périmètre |
|------|-----------|
| `admin` | Accès complet (permission wildcard `*`) |
| `rh` | Organisation, employés, congés, paramètres RH, documents, portail |
| `paie` | Paie, bulletins, DSN, employés, congés (sans les paramètres RH) |
| `comptable` | Comptabilité, trésorerie, facturation, frais, conformité |
| `employe` | Portail salarié en self-service |

### Permissions

46 permissions codées par module (ex. `invoicing.write`, `hr.leaves.approve`,
`hr.settings.manage`, `employees.delete`, `invoicing.einvoice`, `audit.read`,
`mail.read`, `mail.configure`). La liaison se fait via les tables `roles`,
`permissions`, `role_permission`, `role_user`.

La permission `hr.settings.manage` (rythme d'acquisition des congés payés)
est réservée au super-administrateur (wildcard `*`) et au rôle `rh` : elle est
explicitement retirée du rôle `paie`, bien que celui-ci hérite du module RH.

### Comptes salariés et mot de passe forcé

La fiche salarié est créée sans compte. Un administrateur crée ensuite un compte
avec un secret aléatoire et envoie une invitation, ou confirme explicitement le
rattachement d'un compte existant à l'adresse professionnelle. L'ancien
provisionnement automatique est désactivé. Les comptes historiques utilisant
le mot de passe public `change_me@` doivent recevoir une réinitialisation sécurisée.
La sortie désactive immédiatement le compte lié et invalide ses sessions ; les
comptes partagés ou administrateurs exigent une vérification manuelle préalable.

Le module Mail illustre la séparation lecture/administration : tous les rôles
disposent de `mail.read` (chacun n'accède qu'à la boîte affectée à son compte),
tandis que `mail.configure` n'est accordé qu'à l'admin via le wildcard `*`.
Les mots de passe IMAP/SMTP sont chiffrés au repos (préfixe `enc::`) et ne
sortent jamais de la couche de service.

Protection des routes (web et API) :

```php
$router->post('/invoices/{id}/issue', [InvoiceController::class, 'issue'])
       ->middleware('permission:invoicing.validate');
```

Côté vue, les entrées de menu non autorisées ne sont pas rendues
(`ViewRenderer::navItem`). Côté serveur, le middleware `RequirePermission`
reste la barrière obligatoire — le masquage côté UI n'est qu'un confort.

## Données sensibles et mots de passe

- les mots de passe ne sont jamais stockés en clair, jamais journalisés ;
- les jetons « remember me » et les jetons de portail salarié sont stockés en
  SHA-256, le clair n'est retourné qu'une seule fois à la génération ;
- `APP_KEY` (chiffrement applicatif) et `JWT_SECRET` vivent dans `.env`, jamais
  dans le dépôt ;
- la rotation de `JWT_SECRET` invalide toutes les sessions API existantes.

## Compte initial

Le seeder crée `admin@prime-vision-solutions.fr` avec un mot de passe
d'initialisation. **Le changer immédiatement** après déplafonnement, ou créer un
administrateur nominatif et désactiver ce compte :

```bash
php bin/console user:create --email=nom@entreprise.fr --password='...' --role=admin
```

### Suppression d'un compte

Depuis **Utilisateurs**, un administrateur peut supprimer un autre compte après
confirmation. Il ne peut pas supprimer le sien. La suppression révoque l'accès,
les rôles, jetons et paramètres de boîte mail enregistrés, et détache le compte
de la fiche salarié sans supprimer celle-ci. L'adresse de connexion est libérée.

Il s'agit d'une suppression logique : l'identifiant et le nom du compte restent
conservés pour l'historique, les messages, les journaux et les preuves de
signature ; ces données ne sont pas effacées. Ce n'est donc pas une suppression
complète des données personnelles. Une adresse réutilisée crée un nouveau compte,
sans accès aux anciennes conversations ni aux anciennes autorisations. Appliquer
la migration `20261107_000020_deleted_users.sql` avant le déploiement.

### Réinitialisation du mot de passe salarié

Le bouton « Modifier la fiche » est réservé aux administrateurs et RH disposant d'`employees.write` ; une fiche rattachée à un compte administrateur ne peut être modifiée que par un administrateur. L'adresse professionnelle d'un salarié avec compte devient son identifiant de connexion ; elle est obligatoire et ne peut appartenir à un autre compte. L'enregistrement est transactionnel et invalide les anciens liens de réinitialisation et jetons « se souvenir de moi » lorsque l'identifiant change. L'adresse personnelle reste une adresse de contact.

Sur la fiche, seul un administrateur peut envoyer un lien de réinitialisation aux adresses personnelle et professionnelle valides renseignées (un seul envoi si elles sont identiques). Le salarié peut aussi utiliser « Mot de passe oublié » avec son identifiant de connexion. La réponse publique reste volontairement identique pour les adresses connues ou inconnues. Les liens expirent après une heure, sont à usage unique et une réinitialisation invalide les autres liens et les jetons de connexion persistante. Les sessions web déjà ouvertes ne sont pas automatiquement fermées.

Configurer `APP_URL` avec l'URL publique HTTPS exacte et `MAIL_HOST`, `MAIL_PORT`, `MAIL_SECURE`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS` et `MAIL_FROM_NAME` pour le SMTP transactionnel ; PHPMailer doit être installé via Composer. Vérifier la réception réelle et les dossiers indésirables après déploiement. Si l'envoi échoue, l'action administrateur le signale ; le formulaire public ne révèle ni l'existence du compte ni l'état de livraison. Activer le limiteur de débit et un proxy de confiance correctement configuré en production. Le limiteur existant n'est pas atomique sous forte concurrence.

Pour une fiche existante sans compte, l'administrateur peut choisir « Ajouter un compte ». Le rôle salarié est présélectionné. Si l'adresse professionnelle n'est pas encore utilisée, un compte lié est créé avec un secret aléatoire et un e-mail d'invitation contenant l'identifiant et un lien personnel valable une heure est envoyé à cette adresse. Aucun mot de passe n'est envoyé. Si l'envoi échoue, le compte reste créé ; après vérification de la configuration SMTP, le bouton d'envoi d'un lien sur la fiche permet de recommencer.

Si l'adresse professionnelle correspond déjà à un compte, une fenêtre propose l'envoi d'un lien à cette seule adresse ou le rattachement explicite du compte, sans modifier ses rôles ni son mot de passe. L'administrateur doit vérifier qu'il appartient bien au salarié. Un compte inactif ou déjà rattaché à un autre salarié ne peut pas être rattaché ou réinitialisé via ce parcours.
