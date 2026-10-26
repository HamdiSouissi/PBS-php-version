<?php

declare(strict_types=1);

namespace App\Core\Scheduler;

/**
 * Planificateur local de tâches récurrentes (équivalent cron maison).
 * Persisté sur disque ; lancé par `bin/console schedule:run`.
 */
final class Scheduler
{
    /** @var list<array{cron:string,command:string,description:string}> */
    private array $jobs = [];

    public function __construct(private readonly string $stateDirectory)
    {
        if (!is_dir($stateDirectory) && !mkdir($stateDirectory, 0775, true) && !is_dir($stateDirectory)) {
            throw new \RuntimeException('Dossier du planificateur inaccessible.');
        }
    }

    public function command(string $cron, string $command, string $description = ''): void
    {
        $this->jobs[] = ['cron' => $cron, 'command' => $command, 'description' => $description];
    }

    /**
     * Exécute les tâches dues.
     * @return list<string> commandes exécutées
     */
    public function due(?\DateTimeImmutable $now = null): array
    {
        $now ??= new \DateTimeImmutable();
        $stateFile = $this->stateDirectory . '/scheduler.state';
        /** @var array<string,int> $state */
        $state = is_file($stateFile) ? (unserialize((string) file_get_contents($stateFile)) ?: []) : [];
        $executed = [];

        foreach ($this->jobs as $job) {
            $key = hash('sha256', $job['command'] . $job['cron']);
            if (!$this->isDue($job['cron'], $now)) {
                continue;
            }
            $minuteKey = $key . ':' . $now->format('Y-m-d-H-i');
            if (isset($state[$minuteKey])) {
                continue;
            }
            $executed[] = $job['command'];
            $state[$minuteKey] = $now->getTimestamp();
        }
        file_put_contents($stateFile, serialize($state), LOCK_EX);
        return $executed;
    }

    /**
     * Interprète une expression cron 5 champs (minute heure jour mois jour-semaine).
     */
    public function isDue(string $expression, \DateTimeImmutable $now): bool
    {
        $parts = explode(' ', trim($expression));
        if (count($parts) !== 5) {
            throw new \InvalidArgumentException("Expression cron invalide : {$expression}");
        }
        $values = [(int) $now->format('i'), (int) $now->format('G'), (int) $now->format('j'), (int) $now->format('n'), ((int) $now->format('N')) % 7];
        foreach ($parts as $index => $part) {
            if (!$this->fieldMatches($part, $values[$index])) {
                return false;
            }
        }
        return true;
    }

    private function fieldMatches(string $field, int $value): bool
    {
        foreach (explode(',', $field) as $option) {
            if ($option === '*') {
                return true;
            }
            if (str_starts_with($option, '*/')) {
                $step = (int) substr($option, 2);
                if ($step > 0 && $value % $step === 0) {
                    return true;
                }
                continue;
            }
            if ((int) $option === $value) {
                return true;
            }
        }
        return false;
    }

    /** @return list<array{cron:string,command:string,description:string}> */
    public function jobs(): array
    {
        return $this->jobs;
    }
}
