# 02 — Installation sous Windows

Deux voies sont décrites : **Docker Desktop** (recommandé) et une
**installation native** pour le développement sans conteneur.

## Voie A — Docker Desktop (recommandée)

### Prérequis

1. Windows 10 22H2 (64 bits) ou Windows 11.
2. WSL2 installé :

   ```powershell
   wsl --install
   ```

   Redémarrer puis vérifier : `wsl --status`.
3. [Docker Desktop](https://www.docker.com/products/docker-desktop/) avec le
   backend WSL2 activé (paramètre par défaut depuis la version 4.x).
4. Git for Windows et un terminal (PowerShell 7 ou Windows Terminal).

### Lancement

```powershell
git clone https://github.com/HamdiSouissi/PBS-php-version.git
cd PBS-php-version
copy .env.example .env
notepad .env          # ajuster DB_PASSWORD au minimum
docker compose up -d --build
```

La première construction prend quelques minutes (extensions PHP compilées dans
l'image). L'application est disponible sur <http://localhost:8080>.

### Points de vigilance Windows

- **Fins de ligne CRLF** : configurer Git pour ne pas convertir les scripts shell
  (`entrypoint.sh` resterait exécutable dans l'image Linux) :

  ```powershell
  git config --global core.autocrlf input
  ```

- **Performances des bind mounts** : le montage `./:/var/www/pbs` passe par WSL2.
  Pour de meilleurs débits, cloner le projet dans le système de fichiers WSL
  (`\\wsl$\Ubuntu\home\<user>\...`) plutôt que sous `C:\`.
- **Ports** : si 8080 est occupé, définir `APP_HTTP_PORT=8090` dans `.env`.
- **Docker Desktop doit tourner** : vérifier l'état avec `docker info`.

## Voie B — Installation native (développement)

### 1. PHP 8.4

Récupérer PHP 8.4 pour Windows sur
[windows.php.net](https://windows.php.net/download/) (build **x64 Thread Safe**),
l'extraire dans `C:\php-8.4` et ajouter le dossier au `PATH`.

Activer dans `C:\php-8.4\php.ini` (copier `php.ini-development`) :

```ini
extension=pdo_pgsql
extension=pgsql
extension=mbstring
extension=intl
extension=openssl
extension=sodium
extension=zip
extension=gd
extension=bcmath
extension=fileinfo
extension=exif
```

Vérification :

```powershell
php -v
php -m | findstr /i "pdo_pgsql intl gd"
```

### 2. PostgreSQL 18

Installer PostgreSQL via
[l'installeur EDB](https://www.enterprisedb.com/downloads/postgres-postgresql-downloads)
ou avec winget :

```powershell
winget install PostgreSQL.PostgreSQL.18
```

Créer la base et l'utilisateur (SQL Shell `psql`) :

```sql
CREATE USER pbs WITH PASSWORD 'changez_moi';
CREATE DATABASE pbs OWNER pbs;
CREATE DATABASE pbs_test OWNER pbs;
```

### 3. Composer

[Installer Composer](https://getcomposer.org/download/) puis, dans le projet :

```powershell
composer install
```

L'application fonctionne aussi sans `vendor/` grâce à son autoloader PSR-4
maison ; Composer n'est requis que pour les bibliothèques de génération
documentaire (FPDF, PHPMailer, PhpSpreadsheet, PHPWord).

### 4. Configuration et initialisation

```powershell
copy .env.example .env
notepad .env
```

Adapter dans `.env` :

```ini
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=pbs
DB_USERNAME=pbs
DB_PASSWORD=changez_moi
```

Puis :

```powershell
php bin\console app:key-generate
php bin\console migrate:run
php bin\console db:seed
php -S 0.0.0.0:8080 -t public
```

L'application est servie par le serveur interne de PHP sur
<http://localhost:8080> (développement uniquement — prévoir Nginx/Apache en
production, voir le guide 05).

### 5. Tests

```powershell
$env:PBS_TEST_DB="pbs_test"   # nom de la base dédiée (valeur par défaut)
php bin\console test:run unit
```

## OCR (facultatif)

Le module de notes de frais peut utiliser Tesseract. Sous Windows, installer
[binaire officiel](https://github.com/UB-Mannheim/tesseract/wiki) et renseigner
le chemin complet dans `.env` (`TESSERACT_PATH=`). L'application reste
entièrement fonctionnelle si l'OCR est désactivé (`OCR_ENABLED=false`).
