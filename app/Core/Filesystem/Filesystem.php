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
        $parts = $this->segments($relativePath);
        $full = (string) realpath($this->root);
        foreach ($parts as $index => $part) {
            $full .= DIRECTORY_SEPARATOR . $part;
            if (is_link($full)) {
                throw new \RuntimeException('Lien symbolique interdit.');
            }
            if ($index < count($parts) - 1 && !is_dir($full)
                && !mkdir($full, 0775) && !is_dir($full)) {
                throw new \RuntimeException("Création de dossier impossible : {$full}");
            }
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
        try {
            return is_file($this->safeRealPath($relativePath));
        } catch (\RuntimeException $exception) {
            return false;
        }
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
        $parts = $this->segments($relativePath);
        $root = realpath($this->root);
        $full = realpath($this->root . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $parts));
        if ($root === false || $full === false || !str_starts_with($full, rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) {
            throw new \RuntimeException('Chemin de fichier interdit.');
        }
        return $full;
    }

    /** @return list<string> */
    private function segments(string $path): array
    {
        if ($path === '' || str_contains($path, "\0") || str_starts_with($path, '/') || str_starts_with($path, '\\')
            || preg_match('/^[a-zA-Z]:/', $path)) {
            throw new \RuntimeException('Chemin de fichier interdit.');
        }
        $parts = preg_split('#[/\\\\]+#', $path);
        if (!$parts || in_array('..', $parts, true) || in_array('.', $parts, true) || in_array('', $parts, true)) {
            throw new \RuntimeException('Chemin de fichier interdit.');
        }
        return $parts;
    }
}
