# 10 — API REST / JWT

API JSON versionnée sous `/api/v1`, authentification par jeton JWT Bearer,
limitation de débit à 120 requêtes/minute par IP (groupe `throttle:api`).

## Authentification

### Connexion

```http
POST /api/v1/auth/login
Content-Type: application/json

{"email":"admin@prime-vision-solutions.fr","password":"********"}
```

Réponse :

```json
{
  "success": true,
  "token_type": "Bearer",
  "access_token": "eyJ0eXAiOiJKV1Qi…",
  "refresh_token": "eyJ0eXAiOiJKV1Qi…"
}
```

| Endpoint | Rôle |
|----------|------|
| `POST /api/v1/auth/login` | Identifiants → jetons |
| `POST /api/v1/auth/2fa` | Validation TOTP lorsque la 2FA est activée |
| `POST /api/v1/auth/refresh` | Renouvellement du jeton d'accès |
| `GET /api/v1/auth/me` | Profil, rôles et permissions de l'appelant |

### Utilisation

```http
GET /api/v1/invoices
Authorization: Bearer <access_token>
```

Sans jeton ou avec un jeton invalide : `401` JSON. Les permissions RBAC du
compte s'appliquent à l'identique du web (`invoicing.write`,
`invoicing.payments`, `employees.read`…).

## Endpoints métier

### Employés

| Méthode | Chemin | Permission | Description |
|---------|--------|-----------|-------------|
| GET | `/api/v1/employees` | `employees.read` | Liste avec recherche `?q=` |
| GET | `/api/v1/employees/{id}` | `employees.read` | Fiche détaillée |

### Facturation

| Méthode | Chemin | Permission | Description |
|---------|--------|-----------|-------------|
| GET | `/api/v1/invoices` | `invoicing.read` | Liste paginée (`?page=&status=`) |
| GET | `/api/v1/invoices/{id}` | `invoicing.read` | Facture, lignes, paiements |
| POST | `/api/v1/invoices` | `invoicing.write` | Création en brouillon |
| POST | `/api/v1/invoices/{id}/issue` | `invoicing.validate` | Émission |
| POST | `/api/v1/invoices/{id}/payments` | `invoicing.payments` | Encaissement |

Exemple de création :

```bash
curl -X POST http://localhost:8080/api/v1/invoices \
  -H "Authorization: Bearer $JETON" \
  -H 'Content-Type: application/json' \
  -d '{
    "third_party_id": 2,
    "issue_date": "2026-10-26",
    "due_date": "2026-11-25",
    "lines": [
      {"label": "Prestation", "quantity": 5, "unit_price": 200, "vat_rate": 20}
    ]
  }'
```

Réponse :

```json
{"success": true, "data": {"id": 6}}
```

## Conventions de réponse

- succès : `{"success": true, "data": …}` (liste paginée avec méta `total`,
  `page`, `per_page`, `pages`) ;
- erreur métier : `{"success": false, "error": { "type": …, "code": …,
  "message": …, "trace": […] }}` avec code HTTP approprié (401, 403, 404, 422,
  500) ;
- les montants utilisent le point décimal et sont exprimés dans la devise de
  la facture (EUR par défaut) ;
- les taux de TVA acceptés sont les taux français en vigueur : 20, 10, 5,5,
  2,1 et 0 %.

## Sécurité de l'API

- aucun secret (clés IA, SMTP, base) n'est jamais exposé dans une réponse ;
- les jetons d'accès expirent en 1 h (`JWT_TTL`), ceux de rafraîchissement en
  14 jours ;
- le CSRF ne s'applique pas à l'API (authentification par jeton, pas de cookie) ;
- les écritures web conservent la protection CSRF par jeton de session.

## Extension

Chaque module expose `Routes/api.php` chargé automatiquement. Les nouveaux
endpoints se placent dans un groupe `['jwt']` et les contrôleurs dans
`Controllers/Api/`, en réutilisant les mêmes services que le web (logique
unique, double exposition).
