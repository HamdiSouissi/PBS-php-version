# 02 — Installation sous Windows

Trois voies sont décrites :

- **Voie A — Laragon** (recommandée pour le développement local) : Apache,
  PHP 8.4 et MySQL/MariaDB réunis dans une stack prête à l'emploi ;
- **Voie B — Docker Desktop** : environnement identique à la production ;
- **Voie C — Installation native** pour qui n'utilise pas Laragon.

## Voie A — Laragon (recommandée)

[Laragon](https://laragon.org/download/) (édition **Full**) fournit Apache,
PHP, MySQL (ou MariaDB) et Composer. PHP 8.4 est requis : si Laragon ne
l'embarque pas, le télécharger sur [windows.php.net](https://windows.php.net/download/)
(build **x64 Thread Safe**), l'extraire dans `C:\laragon\bin\php\php-8.4`
puis le sélectionner dans *Menu Laragon → PHP → Version*.

### 1. Récupérer le projet

```powershell
cd C:\laragon\www
git clone https://github.com/HamdiSouissi/PBS-php-version.git pbs
cd pbs
```

### 2. Activer les extensions PHP

Dans `C:\laragon\bin\php\php-8.4\php.ini` (copier `php.ini-development`
s'il n'existe pas), décommenter :

```ini
extension=pdo_mysql
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

Vérification dans un terminal :

```powershell
php -v
php -m | findstr /i "pdo_mysql intl gd"
```

### 3. Créer la base MySQL

Démarrer Laragon (services Apache + MySQL), ouvrir *Menu → MySQL →
 HeidiSQL/Adminer* (ou utiliser la console `mysql -u root`) puis :

```sql
CREATE DATABASE pbs CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'pbs'@'localhost' IDENTIFIED BY 'changez_moi';
GRANT ALL PRIVILEGES ON pbs.* TO 'pbs'@'localhost';
CREATE DATABASE pbs_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON pbs_test.* TO 'pbs'@'localhost';
FLUSH PRIVILEGES;
```

> Laragon utilise souvent MariaDB : le schéma et les requêtes PBS sont
> compatibles MariaDB 10.11+, les mêmes commandes s'appliquent.

### 4. Configurer l'application

```powershell
copy .env.example .env
notepad .env
```

Adapter la section base de données (Laragon écoute en local sur 3306) :

```ini
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=pbs
DB_USERNAME=pbs
DB_PASSWORD=changez_moi
APP_URL=http://pbs.test
```

### 5. Dépendances, clés, migrations, seeds

```powershell
composer install
php bin/console app:key-generate
php bin/console migrate:run
php bin/console db:seed
```

### 6. Déclarer l'hôte virtuel

Deux options :

- **Option automatique** : *Menu Laragon → Apache → sites-auto* activé, puis
  clic droit sur le dossier `C:\laragon\www\pbs` ; Laragon crée l'hôte
  `http://pbs.test`.
- **Option manuelle** : créer
  `C:\laragon\etc\apache2\sites-enabled\pbs.conf` :

  ```apache
  <VirtualHost *:80>
      ServerName pbs.test
      DocumentRoot "C:/laragon/www/pbs/public"
      <Directory "C:/laragon/www/pbs/public">
          AllowOverride All
          Require all granted
      </Directory>
  </VirtualHost>
  ```

  Ajouter dans `C:\Windows\System32\drivers\etc\hosts` (en admin) :

  ```text
  127.0.0.1 pbs.test
  ```

Le projet contient déjà `public/.htaccess` (réécriture vers le front
controller, protection des fichiers cachés, en-têtes de sécurité). Redémarrer
Apache puis ouvrir <http://pbs.test>.

### 7. Tests

La suite par défaut tourne sur SQLite, aucun serveur requis :

```powershell
php bin/console test:run unit
```

Pour tester contre MySQL :

```powershell
set DB_CONNECTION=mysql
set PBS_TEST_DB=pbs_test
php bin/console test:run functional
```

### Dépannage Laragon

| Symptôme | Cause / remède |
|---|---|
| 404 sur toutes les routes sauf `/` | `AllowOverride None` → passer à `All`, ou `mod_rewrite` désactivé (Laragon l'active par défaut) |
| `SQLSTATE[HY000] [2002] Connection refused` | MySQL non démarré dans Laragon, ou `DB_PORT` faux (3306) |
| Erreur d'extension manquante | décommenter la ligne correspondante dans `php.ini` puis redémarrer Apache |
| Caractères accentués cassés | vérifier que la base est en `utf8mb4` (commande `CREATE DATABASE` ci-dessus) |
| Droits sur `storage/` | clic droit → Propriétés → donner contrôle total à l'utilisateur IIS/Apache |

## Voie B — Docker Desktop

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
notepad .env          # ajuster DB_PASSWORD et DB_ROOT_PASSWORD
docker compose up -d --build
```

La première construction prend quelques minutes (extensions PHP compilées dans
l'image). Le service `db` est un conteneur MySQL 8.4 (volume `mysqldata`),
l'application est disponible sur <http://localhost:8080>.

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

## Voie C — Installation native (sans Laragon)

### 1. PHP 8.4

Récupérer PHP 8.4 pour Windows sur
[windows.php.net](https://windows.php.net/download/) (build **x64 Thread Safe**),
l'extraire dans `C:\php-8.4` et ajouter le dossier au `PATH`.

Activer dans `C:\php-8.4\php.ini` (copier `php.ini-development`) :

```ini
extension=pdo_mysql
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
php -m | findstr /i "pdo_mysql intl gd"
```

### 2. MySQL 8 (ou MariaDB 10.11+)

Installer MySQL via
[l'installeur MySQL Installer](https://dev.mysql.com/downloads/installer/)
ou avec winget :

```powershell
winget install Oracle.MySQL
```

Créer les bases et l'utilisateur (SQL Shell `mysql`) :

```sql
CREATE DATABASE pbs CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'pbs'@'localhost' IDENTIFIED BY 'changez_moi';
GRANT ALL PRIVILEGES ON pbs.* TO 'pbs'@'localhost';
CREATE DATABASE pbs_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON pbs_test.* TO 'pbs'@'localhost';
FLUSH PRIVILEGES;
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
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=pbs
DB_USERNAME=pbs
DB_PASSWORD=changez_moi
```

Puis :

```powershell
php bin/console app:key-generate
php bin/console migrate:run
php bin/console db:seed
php -S 0.0.0.0:8080 -t public
```

L'application est servie par le serveur interne de PHP sur
<http://localhost:8080> (développement uniquement — prévoir Apache/Nginx en
production, voir le guide 05). Le fichier `public/.htaccess` fourni permet
aussi de basculer directement sous Apache.

### 5. Tests

```powershell
php bin/console test:run unit
```

## OCR (facultatif)

Le module de notes de frais peut utiliser Tesseract. Sous Windows, installer le
[binaire officiel](https://github.com/UB-Mannheim/tesseract/wiki) et renseigner
le chemin complet dans `.env` (`TESSERACT_PATH=`). L'application reste
entièrement fonctionnelle si l'OCR est désactivé (`OCR_ENABLED=false`).
