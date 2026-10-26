<?php

declare(strict_types=1);

namespace App\Core\Filesystem;

/**
 * Abstraction disque local avec protection contre les traversées de chemin.
 */
final class Filesystem
{
    public function __construct(private readonly string $root)
    {
        if (!is_dir($root) && !mkdir($root, 0775, true) && !is_dir($root)) {
            throw new \RuntimeException("Racine filesystem inaccessible : {$root}");
        }
    }

    public function root(): string
    {
        return $this->root;
    }

    public function path(string $relativePath): string
    {
        $full = realpath($this->root) . DIRECTORY_SEPARATOR . ltrim($relativePath, '/\\');
        $directory = dirname($full);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException("Création de dossier impossible : {$directory}");
        }
        return $full;
    }

    public function put(string $relativePath, string $contents): string
    {
        $full = $this->path($relativePath);
        if (file_put_contents($full, $contents, LOCK_EX) === false) {
            throw new \RuntimeException("Écriture impossible : {$full}");
        }
        chmod($full, 0640);
        return $full;
    }

    public function get(string $relativePath): string
    {
        $full = $this->safeRealPath($relativePath);
        $contents = file_get_contents($full);
        if ($contents === false) {
            throw new \RuntimeException("Lecture impossible : {$relativePath}");
        }
        return $contents;
    }

    public function exists(string $relativePath): bool
    {
        return is_file($this->root . DIRECTORY_SEPARATOR . ltrim($relativePath, '/\\'));
    }

    public function delete(string $relativePath): void
    {
        $full = $this->safeRealPath($relativePath);
        @unlink($full);
    }

    /**
     * Empêche les attaques par traversée (../).
     */
    private function safeRealPath(string $relativePath): string
    {
        $root = realpath($this->root);
        $full = realpath($this->root . DIRECTORY_SEPARATOR . ltrim($relativePath, '/\\'));
        if ($full === false || !str_starts_with($full, $root)) {
            throw new \RuntimeException('Chemin de fichier interdit.');
        }
        return $full;
    }
}
