<?php

declare(strict_types=1);

namespace App\Core\Queue;

/**
 * File de travaux locale, persistée sur disque (aucun service externe requis).
 * Redis peut être activé via la configuration pour la production.
 */
final class QueueManager
{
    /** @param array<string,mixed> $config */
    public function __construct(private readonly array $config)
    {
    }

    /**
     * @param array<string,mixed> $payload
     */
    public function push(string $jobClass, array $payload = [], string $queue = 'default', ?int $availableAt = null): void
    {
        $job = [
            'id' => bin2hex(random_bytes(12)),
            'job' => $jobClass,
            'payload' => $payload,
            'queue' => $queue,
            'available_at' => $availableAt ?? time(),
            'created_at' => time(),
            'attempts' => 0,
        ];
        $directory = $this->directory($queue);
        file_put_contents($directory . '/' . $job['id'] . '.job', serialize($job), LOCK_EX);
    }

    /**
     * @return array<string,mixed>|null
     */
    public function pop(string $queue = 'default'): ?array
    {
        foreach (glob($this->directory($queue) . '/*.job') ?: [] as $file) {
            $job = unserialize((string) file_get_contents($file), ['allowed_classes' => true]);
            if (!is_array($job) || ($job['available_at'] ?? 0) > time()) {
                continue;
            }
            // Verrou par renommage atomique.
            $locked = $file . '.lock';
            if (!@rename($file, $locked)) {
                continue;
            }
            $job['attempts'] = ($job['attempts'] ?? 0) + 1;
            $job['_file'] = $locked;
            return $job;
        }
        return null;
    }

    /** @param array<string,mixed> $job */
    public function acknowledge(array $job): void
    {
        @unlink($job['_file'] ?? '');
    }

    /** @param array<string,mixed> $job */
    public function release(array $job, int $delaySeconds = 60): void
    {
        $file = $job['_file'] ?? null;
        if ($file === null) {
            return;
        }
        $job['available_at'] = time() + $delaySeconds;
        unset($job['_file']);
        file_put_contents(substr($file, 0, -5), serialize($job), LOCK_EX);
        @unlink($file);
    }

    public function size(string $queue = 'default'): int
    {
        return count(glob($this->directory($queue) . '/*.job') ?: []);
    }

    private function directory(string $queue): string
    {
        $directory = rtrim((string) $this->config['file']['path'], '/\\') . DIRECTORY_SEPARATOR . $queue;
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException("Dossier de queue inaccessible : {$directory}");
        }
        return $directory;
    }
}
