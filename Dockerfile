# =============================================================================
# Prime Business Suite — Image applicative PHP-FPM 8.4 (production + dev)
# Framework : aucun. PHP natif OO, PostgreSQL via PDO.
# =============================================================================
FROM php:8.4-fpm-bookworm AS base

# ---- Dépendances système ----------------------------------------------------
# libpq (PDO PostgreSQL), icu (intl), zip, gd (png/jpeg/freetype), xml,
# tesseract-ocr (module Dépenses/OCR), git/unzip pour Composer.
RUN apt-get update && apt-get install -y --no-install-recommends \
        libpq-dev \
        libicu-dev \
        libzip-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libxml2-dev \
        unzip \
        git \
        tesseract-ocr \
        postgresql-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql \
        pgsql \
        mbstring \
        intl \
        bcmath \
        gd \
        zip \
        exif \
        opcache \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# ---- Composer (binaire officiel) --------------------------------------------
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/pbs

# Dépendances PHP d'abord (meilleure réutilisation du cache de couches).
COPY composer.json ./
RUN composer install --no-interaction --no-scripts --prefer-dist || true

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
HEALTHCHECK --interval=30s --timeout=10s --start-period=40s --retries=5 \
    CMD php bin/health.php || exit 1

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]
