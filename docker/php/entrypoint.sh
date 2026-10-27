#!/usr/bin/env bash
# =============================================================================
# Point d'entrée du conteneur applicatif PBS.
#  1. Prépare .env et les clés secrètes (APP_KEY, JWT_SECRET)
#  2. Attend PostgreSQL
#  3. Exécute les migrations SQL (idempotent) puis les seeds de référence
#  4. Lance la commande du conteneur (php-fpm par défaut)
# =============================================================================
set -euo pipefail

cd /var/www/pbs

log() { echo "[pbs-entrypoint] $*"; }

# ---- 1. Fichier .env ---------------------------------------------------------
if [ ! -f .env ]; then
    log ".env absent — copie depuis .env.example"
    cp .env.example .env
    chmod 640 .env
fi

# Génération des clés si absentes. On exige une vraie valeur (au moins un
# caractère qui n'est ni un blanc ni un '#') : une ligne "APP_KEY=  # commentaire"
# du .env.example ne doit pas être considérée comme pourvue.
if ! grep -qE '^APP_KEY=[[:space:]]*[^[:space:]#]' .env \
   || ! grep -qE '^JWT_SECRET=[[:space:]]*[^[:space:]#]' .env; then
    log "Génération des clés APP_KEY / JWT_SECRET"
    php bin/console app:key-generate
fi

# ---- 2. Attente de PostgreSQL ------------------------------------------------
log "Attente de PostgreSQL (${DB_HOST:-db}:${DB_PORT:-5432})…"
attempts=0
max_attempts=60
until php bin/health.php >/dev/null 2>&1; do
    attempts=$((attempts + 1))
    if [ "$attempts" -ge "$max_attempts" ]; then
        log "ERREUR : PostgreSQL injoignable après ${max_attempts} tentatives"
        exit 1
    fi
    sleep 1
done
log "PostgreSQL accessible."

# ---- 3. Migrations et données de référence -----------------------------------
log "Exécution des migrations…"
php bin/console migrate:run

log "Vérification des données de référence (seed idempotent)…"
php bin/console db:seed

# ---- 4. Lancement ------------------------------------------------------------
log "Démarrage : $*"
exec "$@"
