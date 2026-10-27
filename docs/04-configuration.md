# 04 — Configuration (.env)

Toute la configuration passe par des variables d'environnement lues depuis le
fichier `.env` (parser maison, `App\Core\Support\Env`) ou directement depuis
l'environnement du processus (Docker, systemd…). Le fichier `.env` n'est jamais
versionné (`.gitignore`) ; le modèle est `.env.example`.

## Génération des clés

```bash
php bin/console app:key-generate
```

Renseigne `APP_KEY` (chiffrement applicatif, base64 de 32 octets) et
`JWT_SECRET` (signature HMAC des jetons API). Une rotation de `JWT_SECRET`
invalide tous les jetons d'accès émis ; les utilisateurs se reconnectent.

## Référence des variables

### Application

| Variable | Défaut | Rôle |
|----------|--------|------|
| `APP_NAME` | Prime Business Suite | Nom affiché et émetteur TOTP |
| `APP_ENV` | development | `development` ou `production` |
| `APP_DEBUG` | true | Pages d'erreur détaillées — **`false` en production** |
| `APP_URL` | http://localhost:8080 | URL publique (liens, e-mails) |
| `APP_TIMEZONE` | Europe/Paris | Fuseau horaire |
| `APP_LOCALE` | fr | Langue de l'interface |
| `APP_KEY` | — | Clé de chiffrement, obligatoire |

### MySQL / MariaDB

| Variable | Défaut | Rôle |
|----------|--------|------|
| `DB_CONNECTION` | mysql | Seul driver officiel (sqlite réservé aux tests) |
| `DB_HOST` | db (Docker) / 127.0.0.1 | Hôte MySQL |
| `DB_PORT` | 3306 | Port TCP |
| `DB_DATABASE` | pbs | Nom de la base applicative |
| `DB_USERNAME` | pbs | Utilisateur applicatif |
| `DB_PASSWORD` | — | Mot de passe (jamais commité) |
| `DB_ROOT_PASSWORD` | — | Mot de passe root, utilisé par le service `db` de Docker Compose |

### Sessions et sécurité

| Variable | Défaut | Rôle |
|----------|--------|------|
| `SESSION_NAME` | pbs_session | Nom du cookie de session |
| `SESSION_LIFETIME` | 7200 | Durée de session (secondes) |
| `REMEMBER_LIFETIME` | 2592000 | Durée du « se souvenir de moi » (30 j) |
| `JWT_SECRET` | — | Secret HMAC des JWT, obligatoire |
| `JWT_TTL` | 3600 | Durée de vie du jeton d'accès (1 h) |
| `JWT_REFRESH_TTL` | 1209600 | Durée du jeton de rafraîchissement (14 j) |

### Limitation de débit

| Variable | Défaut | Rôle |
|----------|--------|------|
| `RATE_LIMIT_ENABLED` | true | Activation globale |
| `RATE_LIMIT_MAX` | 60 | Requêtes par fenêtre, par IP |
| `RATE_LIMIT_WINDOW` | 60 | Fenêtre glissante (secondes) |

La connexion dispose d'un plafond propre (`login_max = 5` tentatives) codé dans
`config/security.php`.

### Cache et file de travaux

| Variable | Valeurs | Rôle |
|----------|---------|------|
| `CACHE_DRIVER` | `file` (défaut) / `redis` | Stockage du cache |
| `QUEUE_DRIVER` | `file` (défaut) / `redis` | File asynchrone |
| `REDIS_HOST` / `REDIS_PORT` | redis / 6379 | Utilisés si driver `redis` |

### E-mail (PHPMailer / SMTP)

`MAIL_HOST`, `MAIL_PORT`, `MAIL_SECURE` (`tls`|`ssl`|vide), `MAIL_USERNAME`,
`MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`. Aucun envoi réel
n'est tenté si le serveur SMTP n'est pas configuré.

### Boîtes mail intégrées (IMAP/SMTP, ex. IONOS)

Les valeurs par défaut des nouveaux comptes sont surchargeables par
l'environnement, chaque compte restant modifiable individuellement depuis
l'écran d'administration (`/mail/admin/accounts`) :

| Variable | Défaut | Rôle |
|----------|--------|------|
| `MAILBOX_IMAP_HOST` / `MAILBOX_IMAP_PORT` | `imap.ionos.fr` / `993` | Serveur entrant |
| `MAILBOX_IMAP_ENCRYPTION` | `ssl` | `ssl` (TLS implicite), `tls` (STARTTLS) ou `none` |
| `MAILBOX_SMTP_HOST` / `MAILBOX_SMTP_PORT` | `smtp.ionos.fr` / `465` | Serveur sortant |
| `MAILBOX_SMTP_ENCRYPTION` | `ssl` | Idem, `tls` correspond au port 587 |
| `MAILBOX_TIMEOUT` | `15` | Délai de connexion en secondes |
| `MAILBOX_PAGE_SIZE` | `20` | Messages par page |
| `MAILBOX_MAX_ATTACHMENTS_SIZE` | `10485760` (10 Mo) | Taille cumulée maximale des pièces jointes |
| `MAILBOX_MAX_RECIPIENTS` | `50` | Destinataires maximum (To/Cc/Cci) |

Les identifiants de connexion ne se configurent pas ici : ils sont saisis par
le gestionnaire dans l'application et chiffrés au repos avec `APP_KEY`.

### Intelligence artificielle (tout optionnel)

| Variable | Rôle |
|----------|------|
| `OLLAMA_ENABLED` | Active le fournisseur local Ollama |
| `OLLAMA_URL` / `OLLAMA_MODEL` / `OLLAMA_TIMEOUT` / `OLLAMA_TEMPERATURE` | Connexion Ollama |
| `CODECRAFT_ENABLED` | Active le fournisseur externe CodeCraftAPI |
| `CODECRAFT_URL` / `CODECRAFT_API_KEY` / `CODECRAFT_MODEL` | API externe |

Règles de sécurité : la clé CodeCraftAPI n'est jamais transmise au navigateur
(utilisation serveur uniquement). L'application reste pleinement fonctionnelle
avec les deux fournisseurs désactivés.

### OCR et sauvegardes

| Variable | Rôle |
|----------|------|
| `OCR_ENABLED` / `TESSERACT_PATH` | Activation et chemin du binaire Tesseract |
| `BACKUP_PATH` | Répertoire des archives (volume persistant Docker) |
| `BACKUP_RETENTION_DAYS` | Rétention avant purge |

## Ordre de précédence

1. variable d'environnement réelle du processus (`getenv`, utile pour Docker) ;
2. valeur dans `.env` ;
3. valeur par défaut du fichier `config/*.php`.

## Vérifier sa configuration

```bash
php bin/console migrate:status     # connectivité base + migrations appliquées
php bin/health.php                 # sonde complète (code de sortie 0/1)
curl http://localhost:8080/health  # version JSON HTTP
```
