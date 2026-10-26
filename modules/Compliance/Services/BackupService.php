<?php

declare(strict_types=1);

namespace Modules\Compliance\Services;

use App\Core\Database\Connection;
use App\Core\Exception\AppException;

/**
 * Sauvegarde et restauration de la base PostgreSQL.
 *
 * Stratégie :
 *  - dump SQL cohérent via pg_dump (binaire du client PostgreSQL), compressé
 *    en gzip directement depuis PHP (pas de dépendance au binaire gzip) ;
 *  - archive ZIP optionnelle des documents générés (uploads, exports,
 *    e-facturation), via ext-zip ;
 *  - purge selon la rétention BACKUP_RETENTION_DAYS ;
 *  - toute exécution est tracée dans le journal d'audit.
 *
 * Prérequis : les binaires pg_dump / psql doivent être dans le PATH
 * (paquet postgresql-client dans l'image Docker).
 */
final class BackupService
{
    private const DOCUMENT_DIRS = ['uploads', 'documents', 'einvoicing', 'exports'];

    public function __construct(private readonly Connection $db)
    {
    }

    /**
     * Réalise une sauvegarde complète et retourne le chemin du fichier SQL gzip.
     */
    public function createBackup(): string
    {
        $config = $this->config();
        $directory = $this->backupDirectory();
        $stamp = date('Ymd-His');
        $sqlFile = $directory . '/pbs-' . $stamp . '.sql.gz';

        $command = [
            $this->binary('pg_dump'),
            '--host=' . $config['host'],
            '--port=' . (string) $config['port'],
            '--username=' . $config['user'],
            '--dbname=' . $config['dbname'],
            '--no-password',
            '--clean',
            '--if-exists',
        ];

        $pipes = [];
        $process = proc_open(
            $command,
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            $this->processEnv($config['password'])
        );
        if (!is_resource($process)) {
            throw new AppException('Impossible de lancer pg_dump. Le client PostgreSQL est-il installé ?');
        }
        fclose($pipes[0]);

        $gz = gzopen($sqlFile, 'wb9');
        if ($gz === false) {
            throw new AppException("Impossible d'écrire la sauvegarde : {$sqlFile}");
        }
        stream_copy_to_stream($pipes[1], $gz);
        fclose($pipes[1]);
        gzclose($gz);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($exitCode !== 0 || !is_file($sqlFile) || filesize($sqlFile) === 0) {
            @unlink($sqlFile);
            throw new AppException('Échec de pg_dump : ' . trim((string) $error));
        }

        $this->archiveDocuments($directory . '/pbs-' . $stamp . '-documents.zip');
        $purged = $this->applyRetention($directory);

        audit('backup.create', 'Backup', null, [
            'file' => basename($sqlFile),
            'size' => filesize($sqlFile),
            'purged' => $purged,
        ]);

        return $sqlFile;
    }

    /**
     * Restaure un dump gzip précédemment produit.
     * Le chemin est résolu dans le dossier de sauvegarde pour empêcher toute
     * lecture arbitraire de fichier.
     */
    public function restoreBackup(string $fileName): void
    {
        $path = $this->backupDirectory() . '/' . basename($fileName);
        if (!is_file($path)) {
            throw new AppException("Sauvegarde introuvable : {$fileName}");
        }

        $config = $this->config();
        $command = [
            $this->binary('psql'),
            '--host=' . $config['host'],
            '--port=' . (string) $config['port'],
            '--username=' . $config['user'],
            '--dbname=' . $config['dbname'],
            '--no-password',
            '--set=ON_ERROR_STOP=1',
            '--quiet',
        ];

        $pipes = [];
        $process = proc_open(
            $command,
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            $this->processEnv($config['password'])
        );
        if (!is_resource($process)) {
            throw new AppException('Impossible de lancer psql. Le client PostgreSQL est-il installé ?');
        }

        $gz = gzopen($path, 'rb');
        if ($gz === false) {
            throw new AppException("Impossible de lire la sauvegarde : {$path}");
        }
        stream_copy_to_stream($gz, $pipes[0]);
        gzclose($gz);
        // Fermer stdin signale la fin du flux, puis drainer stdout/stderr
        // avant la fin du process pour éviter tout « broken pipe ».
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            throw new AppException('Échec de la restauration : ' . trim((string) $error));
        }

        audit('backup.restore', 'Backup', null, ['file' => basename($fileName)]);
    }

    /**
     * Liste les sauvegardes disponibles, de la plus récente à la plus ancienne.
     *
     * @return list<array<string,mixed>>
     */
    public function listBackups(): array
    {
        $files = glob($this->backupDirectory() . '/pbs-*.sql.gz') ?: [];
        rsort($files);
        return array_map(static function (string $path): array {
            return [
                'file' => basename($path),
                'size' => filesize($path),
                'created_at' => date('c', (int) filemtime($path)),
            ];
        }, $files);
    }

    /**
     * @return array{host:string,port:int,dbname:string,user:string,password:string}
     */
    private function config(): array
    {
        $config = config('database.connections.pgsql');
        return [
            'host' => (string) ($config['host'] ?? '127.0.0.1'),
            'port' => (int) ($config['port'] ?? 5432),
            'dbname' => (string) ($config['dbname'] ?? 'pbs'),
            'user' => (string) ($config['user'] ?? 'pbs'),
            'password' => (string) ($config['password'] ?? ''),
        ];
    }

    /**
     * @return array<string,string>
     */
    private function processEnv(string $password): array
    {
        $env = [];
        foreach (($_ENV ?? []) as $key => $value) {
            if (is_scalar($value)) {
                $env[(string) $key] = (string) $value;
            }
        }
        $env['PGPASSWORD'] = $password;
        return $env;
    }

    private function binary(string $name): string
    {
        return PHP_OS_FAMILY === 'Windows' ? $name . '.exe' : $name;
    }

    private function backupDirectory(): string
    {
        $configured = (string) env('BACKUP_PATH', 'storage/backups');
        $directory = str_starts_with($configured, '/')
            ? $configured
            : base_path(ltrim($configured, '/'));
        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
            throw new AppException("Impossible de créer le dossier de sauvegarde : {$directory}");
        }
        return $directory;
    }

    private function archiveDocuments(string $zipPath): void
    {
        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return;
        }
        $added = 0;
        foreach (self::DOCUMENT_DIRS as $dir) {
            $root = storage_path($dir);
            if (!is_dir($root)) {
                continue;
            }
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $zip->addFile($file->getPathname(), $dir . '/' . substr($file->getPathname(), strlen($root) + 1));
                    $added++;
                }
            }
        }
        $zip->close();
        if ($added === 0 && is_file($zipPath)) {
            unlink($zipPath);
        }
    }

    /**
     * Supprime les sauvegardes plus anciennes que la rétention configurée.
     */
    private function applyRetention(string $directory): int
    {
        $retentionDays = max(1, (int) env('BACKUP_RETENTION_DAYS', '30'));
        $threshold = time() - $retentionDays * 86400;
        $purged = 0;
        foreach (glob($directory . '/pbs-*') ?: [] as $path) {
            if (is_file($path) && filemtime($path) < $threshold) {
                unlink($path);
                $purged++;
            }
        }
        return $purged;
    }
}
