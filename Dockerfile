# =============================================================================
# Prime Business Suite — Image applicative PHP-FPM 8.4 (production + dev)
# Framework : aucun. PHP natif OO, PostgreSQL via PDO.
# =============================================================================
FROM php:8.4-fpm-bookworm AS base

# ---- Dépendances système ----------------------------------------------------
# Dépendances de compilation des extensions PHP :
#   libpq-dev      -> pdo_pgsql, pgsql
#   libonig-dev    -> mbstring (expressions régulières multioctets)
#   libicu-dev     -> intl
#   libzip-dev     -> zip
#   libpng/libjpeg/libfreetype/libwebp -> gd
#   libxml2-dev    -> dom, xml
#   libsodium-dev  -> sodium
# Outils runtime : tesseract-ocr (OCR notes de frais), postgresql-client
# (pg_dump/psql des sauvegardes), git/unzip pour Composer.
RUN apt-get update \
    && DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \
        libpq-dev \
        libonig-dev \
        libicu-dev \
        libzip-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libwebp-dev \
        libxml2-dev \
        libsodium-dev \
        pkg-config \
        unzip \
        git \
        tesseract-ocr \
        postgresql-client \
    && docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
        --with-webp \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql \
        pgsql \
        mbstring \
        intl \
        bcmath \
        gd \
        zip \
        exif \
        sodium \
        opcache \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Vérification immédiate : le build s'arrête ici si une extension requise
# par composer.json n'est pas chargée, au lieu d'échouer à l'exécution.
# Le script est dans un fichier dédié (docker/php/) pour éviter tout problème
# de quoting multi-ligne dans une instruction RUN du Dockerfile.
COPY docker/php/check-extensions.php /tmp/check-extensions.php
RUN php /tmp/check-extensions.php

# ---- Composer (binaire officiel) --------------------------------------------
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/pbs

# Dépendances PHP d'abord (meilleure réutilisation du cache de couches).
COPY composer.json ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts

# ---- Code de l'application ---------------------------------------------------
COPY . .

# Configuration PHP runtime + script de démarrage.
COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-pbs.ini
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh \
    && mkdir -p storage/cache storage/sessions storage/queue storage/logs \
                storage/uploads storage/documents storage/exports \
                storage/backups storage/einvoicing \
    && chown -R www-data:www-data /var/www/pbs/storage

# Le master php-fpm tourne en root, les workers en www-data (image officielle).
USER root
EXPOSE 9000
HEALTHCHECK --interval=30s --timeout=10s --start-period=60s --retries=5 \
    CMD php bin/health.php || exit 1

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]
