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
| `rh` | Organisation, employés, congés, documents, portail |
| `paie` | Paie, bulletins, DSN, employés, congés |
| `comptable` | Comptabilité, trésorerie, facturation, frais, conformité |
| `employe` | Portail salarié en self-service |

### Permissions

45 permissions codées par module (ex. `invoicing.write`, `hr.leaves.approve`,
`employees.delete`, `invoicing.einvoice`, `audit.read`). La liaison se fait via
les tables `roles`, `permissions`, `role_permission`, `role_user`.

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
