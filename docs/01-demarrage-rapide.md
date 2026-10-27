# 01 — Démarrage rapide avec Docker

Le moyen le plus rapide d'obtenir une instance PBS complète : PHP-FPM 8.4,
Nginx, MySQL 8.4 et (facultatif) Redis, orchestrés par Docker Compose.

## Prérequis

- Docker Engine 24+ et le plugin Compose v2 (`docker compose version`)
- 2 Go de mémoire disponible, 10 Go d'espace disque
- Ports 8080 (HTTP) et 3306 (MySQL, interne par défaut) libres

## Étapes

```bash
# 1. Récupérer les sources
git clone https://github.com/HamdiSouissi/PBS-php-version.git
cd PBS-php-version

# 2. Préparer la configuration
cp .env.example .env
#   Modifier DB_PASSWORD (obligatoire en production).
#   APP_KEY et JWT_SECRET seront générés automatiquement au premier démarrage
#   s'ils sont absents.

# 3. Construire et démarrer les trois services
docker compose up -d --build

# 4. Suivre les journaux du démarrage (migrations automatiques visibles)
docker compose logs -f app
```

Le point d'entrée du conteneur applicatif (`docker/php/entrypoint.sh`) :

1. crée `.env` depuis `.env.example` s'il n'existe pas ;
2. génère `APP_KEY` et `JWT_SECRET` si l'un d'eux est vide ;
3. attend que MySQL réponde (`php bin/health.php`, 60 tentatives) ;
4. exécute `php bin/console migrate:run` (migrations SQL versionnées) ;
5. exécute `php bin/console db:seed` (seeders idempotents : rôles, PCG, règles) ;
6. lance PHP-FPM.

## Première connexion

Ouvrir <http://localhost:8080>.

| Champ | Valeur par défaut |
|-------|-------------------|
| E-mail | `admin@prime-vision-solutions.fr` |
| Mot de passe | `PBS@2026!admin` |

> **Sécurité** : ce compte est créé par le seeder pour l'initialisation.
> Changez le mot de passe immédiatement après la première connexion sur tout
> environnement partagé ou de production.

## Vérifier l'état des services

```bash
docker compose ps                 # état et healthchecks
curl http://localhost:8080/health # sonde JSON : {"success":true,...}
```

## Arrêter / redémarrer / tout effacer

```bash
docker compose stop               # arrêt sans perte de données
docker compose up -d              # redémarrage
docker compose down               # arrêt + suppression des conteneurs (volumes conservés)
docker compose down -v            # ATTENTION : supprime aussi les volumes (base incluse)
```

## Activer Redis (facultatif)

L'application fonctionne entièrement sans Redis (cache et file en mode fichier).
Pour l'activer :

```bash
docker compose --profile redis up -d --build
```

Puis dans `.env` : `CACHE_DRIVER=redis` et `QUEUE_DRIVER=redis`.

## Problèmes fréquents

| Symptôme | Cause probable | Remède |
|----------|---------------|--------|
| `app` redémarre en boucle | MySQL injoignable | `docker compose logs db`, vérifier `DB_HOST=db` |
| HTTP 502 sur nginx | PHP-FPM pas encore prêt | attendre la fin des migrations (healthcheck) |
| `APP_KEY absent` | `.env` sans clé et génération impossible | `php bin/console app:key-generate` dans le conteneur |
| Port 8080 occupé | autre service | changer `APP_HTTP_PORT` dans `.env` |
| Échec build `Package requirements (oniguruma) were not met` | image construite avec un ancien Dockerfile | `git pull` puis reconstruire : `docker compose build --no-cache app` |
| Échec build `Extensions PHP manquantes` | une extension n'a pas pu être compilée | lire les lignes `configure: error` précédentes ; vérifier les paquets `*-dev` du Dockerfile |
