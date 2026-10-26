<?php

declare(strict_types=1);

namespace App\Core\Filesystem;

/**
 * Gestionnaire de disques locaux (uploads, documents, exports, sauvegardes).
 */
final class FilesystemManager
{
    /** @param array<string,mixed> $config */
    public function __construct(private readonly array $config)
    {
    }

    public function disk(string $name = 'local'): Filesystem
    {
        $root = $this->config['disks'][$name]['root']
            ?? throw new \InvalidArgumentException("Disque inconnu : {$name}");
        return new Filesystem($root);
    }

    public function put(string $disk, string $relativePath, string $contents): string
    {
        return $this->disk($disk)->put($relativePath, $contents);
    }

    public function exists(string $disk, string $relativePath): bool
    {
        return $this->disk($disk)->exists($relativePath);
    }

    public function delete(string $disk, string $relativePath): void
    {
        $this->disk($disk)->delete($relativePath);
    }
}
