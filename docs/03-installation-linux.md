# 03 — Installation sous Linux

Deux voies : **Docker** (le plus simple, identique à la production) et
**installation native** pour le développement.

## Voie A — Docker (recommandée)

```bash
# Debian/Ubuntu : installer Docker Engine + plugin compose
# https://docs.docker.com/engine/install/

git clone https://github.com/HamdiSouissi/PBS-php-version.git
cd PBS-php-version
cp .env.example .env
nano .env                       # modifier DB_PASSWORD au minimum

docker compose up -d --build
docker compose ps
curl http://localhost:8080/health
```

Aucun autre paquet n'est nécessaire sur l'hôte : PHP, les extensions, Nginx,
MySQL et Tesseract sont dans les images. Pour ajouter Redis :

```bash
docker compose --profile redis up -d --build
```

## Voie B — Installation native

Exemples pour Debian 12 / Ubuntu 24.04.

### 1. PHP 8.4 et les extensions requises

```bash
# Dépôt SURY (Debian/Ubuntu) pour obtenir PHP 8.4
sudo apt update
sudo apt install -y ca-certificates lsb-release apt-transport-https
curl -sSL https://packages.sury.org/php/README.txt | sudo bash -x

sudo apt install -y \
    php8.4-fpm php8.4-cli \
    php8.4-mysql php8.4-mbstring php8.4-intl php8.4-xml \
    php8.4-zip php8.4-gd php8.4-bcmath php8.4-opcache \
    php8.4-curl php8.4-sodium
php -v
```

Extensions vérifiées par `composer.json` : `pdo_mysql`, `mbstring`, `json`,
`openssl`, `sodium`, `zip`, `dom`, `xml`, `gd`, `intl`, `bcmath`, `fileinfo`.

### 2. MySQL 8 (ou MariaDB 10.11+)

```bash
# MySQL 8 via le dépôt officiel APT
sudo apt install -y mysql-server
sudo systemctl enable --now mysql

sudo mysql <<'SQL'
CREATE DATABASE pbs CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE pbs_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'pbs'@'localhost' IDENTIFIED BY 'changez_moi';
GRANT ALL PRIVILEGES ON pbs.* TO 'pbs'@'localhost';
GRANT ALL PRIVILEGES ON pbs_test.* TO 'pbs'@'localhost';
FLUSH PRIVILEGES;
SQL
```

Pour MariaDB (défaut de Debian), remplacer `mysql-server` par `mariadb-server`
et la connexion par `sudo mariadb` : le SQL est identique.

### 3. Composer et dépendances

```bash
curl -sSL https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

cd /votre/chemin/PBS-php-version
composer install
```

Sans `vendor/`, l'autoloader maison prend le relais ; Composer n'est
indispensable que pour les bibliothèques documentaires (FPDF, PHPMailer,
PhpSpreadsheet, PHPWord).

### 4. Configuration et clés

```bash
cp .env.example .env
nano .env
```

```ini
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=pbs
DB_USERNAME=pbs
DB_PASSWORD=changez_moi
APP_URL=http://localhost:8080
```

```bash
php bin/console app:key-generate
php bin/console migrate:run
php bin/console db:seed
```

### 5. Servir l'application

Développement :

```bash
php -S 0.0.0.0:8080 -t public
```

Production avec PHP-FPM + Nginx : utiliser la configuration fournie dans
`docker/nginx/default.conf` comme modèle (racine `public/`, rewrite vers
`index.php`, passage à `php-fpm` sur la socket ou le port 9000).

Permissions du stockage :

```bash
mkdir -p storage/{cache,sessions,queue,logs,uploads,documents,exports,backups,einvoicing}
sudo chown -R www-data:www-data storage
chmod -R 770 storage
```

### 6. Tests

```bash
# Suite par défaut sur SQLite : aucun serveur requis
php bin/console test:run unit

# Variante contre MySQL (base pbs_test)
export DB_CONNECTION=mysql
export PBS_TEST_DB=pbs_test
php bin/console test:run functional
```

### 7. OCR Tesseract (facultatif)

```bash
sudo apt install -y tesseract-ocr tesseract-ocr-fra
# .env : OCR_ENABLED=true, TESSERACT_PATH=tesseract
```

## Tâches planifiées (production native)

Les commandes récurrentes se programment via cron ou un timer systemd :

```cron
# migrations de données et rappels (adapté à votre politique de déploiement)
0 2 * * * cd /opt/pbs && /usr/bin/php bin/console schedule:run >> storage/logs/cron.log 2>&1
# factures récurrentes et lettrage des retards
15 2 * * * cd /opt/pbs && /usr/bin/php bin/console invoices:recurring-run
30 2 * * * cd /opt/pbs && /usr/bin/php bin/console invoices:mark-overdue
```
