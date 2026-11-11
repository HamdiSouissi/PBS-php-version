<?php

declare(strict_types=1);

namespace App\Core\Cache;

/**
 * Cache fichier sérialisé, sans dépendance externe.
 */
final class FileCache implements CacheInterface
{
    public function __construct(private readonly string $directory)
    {
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException("Dossier de cache inaccessible : {$directory}");
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $file = $this->path($key);
        if (!is_file($file)) {
            return $default;
        }
        $raw = file_get_contents($file);
        if ($raw === false) {
            return $default;
        }
        $entry = @unserialize($raw, ['allowed_classes' => true]);
        if (!is_array($entry) || !isset($entry['expires'], $entry['value'])) {
            @unlink($file);
            return $default;
        }
        if ($entry['expires'] !== 0 && $entry['expires'] < time()) {
            @unlink($file);
            return $default;
        }
        return $entry['value'];
    }

    public function set(string $key, mixed $value, int $ttl = 3600): void
    {
        $entry = ['expires' => $ttl > 0 ? time() + $ttl : 0, 'value' => $value];
        file_put_contents($this->path($key), serialize($entry), LOCK_EX);
    }

    public function delete(string $key): void
    {
        @unlink($this->path($key));
    }

    public function clear(): void
    {
        foreach (glob($this->directory . '/*.cache') ?: [] as $file) {
            @unlink($file);
        }
    }

    public function synchronized(string $key, callable $operation): mixed
    {
        // Un nombre borné de verrous persistants : ne jamais les supprimer pendant
        // que des processus peuvent les utiliser (un nouveau inode casserait flock).
        $stripe = hexdec(substr(hash('sha256', $key), 0, 2)) % 64;
        $lock = fopen($this->directory . DIRECTORY_SEPARATOR . '.rate-lock-' . $stripe, 'c');
        if ($lock === false) {
            throw new \RuntimeException('Verrou du cache inaccessible.');
        }
        try {
            if (!flock($lock, LOCK_EX)) {
                throw new \RuntimeException('Verrouillage du cache impossible.');
            }
            try {
                return $operation();
            } finally {
                flock($lock, LOCK_UN);
            }
        } finally {
            fclose($lock);
        }
    }

    private function path(string $key): string
    {
        return $this->directory . DIRECTORY_SEPARATOR . hash('sha256', $key) . '.cache';
    }
}
