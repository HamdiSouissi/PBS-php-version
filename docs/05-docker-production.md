# 05 — Docker avancé et production

## Architecture des conteneurs

```
                        ┌──────────────────────────────┐
 navigateur  ──:8080──▶ │ nginx (nginx:1.27-alpine)    │
                        │  reverse proxy + assets      │
                        └───────────────┬──────────────┘
                                        │ fastcgi :9000
                        ┌───────────────▼──────────────┐
                        │ app (php:8.4-fpm-bookworm)   │
                        │  PHP-FPM, extensions, OCR    │
                        │  entrypoint : migrations     │
                        └───────────────┬──────────────┘
                                        │ PDO pgsql :5432
                        ┌───────────────▼──────────────┐
                        │ db (postgres:18-alpine)      │
                        │  volume pgdata               │
                        └──────────────────────────────┘
      redis:7-alpine (profil "redis", cache/queue optionnels)
```

### Services du `docker-compose.yml`

| Service | Image | Rôle | Healthcheck |
|---------|-------|------|-------------|
| `db` | postgres:18-alpine | Données métier | `pg_isready` |
| `app` | build local (Dockerfile) | PHP-FPM + console | `php bin/health.php` |
| `nginx` | nginx:1.27-alpine | HTTP 80 → hôte 8080 | `wget /health` |
| `redis` | redis:7-alpine | Cache/queue, profil `redis` | `redis-cli ping` |

`app` ne démarre le FPM qu'une fois `db` sain (`depends_on: condition:
service_healthy`) et migrations/seeders exécutés.

## Construction de l'image

Le `Dockerfile` :

- part de `php:8.4-fpm-bookworm` ;
- compile les extensions `pdo_pgsql, pgsql, mbstring, intl, bcmath, gd, zip,
  exif, opcache` ;
- installe Tesseract (OCR des notes de frais) ;
- copie Composer 2 et installe les dépendances ;
- expose le port 9000 (FastCGI, jamais exposé à l'hôte) ;
- déclare un `HEALTHCHECK` et un entrypoint idempotent.

```bash
docker build -t pbs-app:latest .
```

## Durcissement pour la production

### 1. Variables d'environnement

Créer un fichier `.env.production` (hors dépôt) ou utiliser les secrets de
l'orchestrateur :

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://erp.prime-vision-solutions.fr
DB_PASSWORD=<mot de passe fort généré>
RATE_LIMIT_ENABLED=true
CACHE_DRIVER=redis
QUEUE_DRIVER=redis
```

### 2. Supprimer le bind mount de développement

En production, le code doit provenir de l'image et non du montage lié. Retirer
le volume `./:/var/www/pbs` du service `app` dans un fichier de surcharge,
par exemple `docker-compose.prod.yml` :

```yaml
services:
  app:
    volumes:
      - storage:/var/www/pbs/storage   # uniquement les données persistantes
    environment:
      APP_ENV: production
      APP_DEBUG: "false"
  nginx:
    ports:
      - "127.0.0.1:8080:80"            # derrière un reverse proxy TLS frontal
```

Lancer avec :

```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d
```

### 3. TLS

L'image Nginx fournie est en HTTP clair : placer un reverse proxy TLS de
périphérie (Traefik, Caddy, Nginx avec certificats Let's Encrypt) devant le
port 8080 publié en loopback. Les en-têtes de sécurité de base
(`X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`) sont déjà
posés par `docker/nginx/default.conf` et complétés par le middleware PHP.

### 4. OPcache

`docker/php/php.ini` active OPcache. Pour des déploiements immuables, passer :

```ini
opcache.validate_timestamps=0
```

(nécessite un rebuild/redémarrage à chaque version).

## Volumes et sauvegarde

| Volume | Contenu | Sauvegarde |
|--------|---------|------------|
| `pgdata` | Cluster PostgreSQL | `pg_dump` logique ou snapshot |
| `storage` | Bulletins, exports, e-facturation, uploads, sessions | copie incrémentale |
| `redisdata` | Cache/queue (reconstituable) | non critique |

Voir le guide 06 pour les commandes `backup:run` / `backup:restore`.

## Commandes utiles dans le conteneur

```bash
docker compose exec app php bin/console migrate:status
docker compose exec app php bin/console test:run unit
docker compose exec app php bin/console schedule:run
docker compose exec app php bin/console queue:work
docker compose exec db psql -U pbs -d pbs
docker compose logs -f app nginx db
```

## Mise à jour applicative

```bash
git fetch && git checkout <version>
docker compose build app
docker compose up -d                        # l'entrypoint rejoue les migrations
docker compose exec app php bin/console test:run unit
```

Les migrations sont conçues pour être ré-exécutées sans risque (piste dans
`schema_migrations`) ; les seeders sont idempotents (`ON CONFLICT DO NOTHING`).
