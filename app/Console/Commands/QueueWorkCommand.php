<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Application;
use App\Core\Console\Command;
use App\Core\Console\Input;
use App\Core\Console\Output;
use App\Core\Queue\QueueManager;

/**
 * Traite les travaux en file. Les jobs doivent implémenter handle(array $payload).
 */
final class QueueWorkCommand extends Command
{
    protected string $name = 'queue:work';

    protected string $description = 'Traite la file de travaux locale (Ctrl+C pour arrêter)';

    public function handle(Input $input, Output $output): int
    {
        $queue = $input->option('queue', 'default');
        $maxJobs = (int) $input->option('max-jobs', '0');
        $manager = Application::container()->get(QueueManager::class);
        $processed = 0;

        $output->info("Worker démarré sur la file '{$queue}'");
        while (true) {
            $job = $manager->pop($queue);
            if ($job === null) {
                if ($maxJobs > 0 && $processed >= $maxJobs) {
                    break;
                }
                sleep(1);
                continue;
            }
            try {
                /** @var class-string $jobClass */
                $jobClass = $job['job'];
                $instance = Application::container()->get($jobClass);
                $instance->handle($job['payload'] ?? []);
                $manager->acknowledge($job);
                $processed++;
                $output->success("Job traité : {$jobClass} ({$job['id']})");
            } catch (\Throwable $exception) {
                $output->error("Échec job {$job['job']} : {$exception->getMessage()}");
                if (($job['attempts'] ?? 1) >= 3) {
                    $manager->acknowledge($job);
                    $output->warning('Job abandonné après 3 tentatives.');
                } else {
                    $manager->release($job, 120);
                }
            }
            if ($maxJobs > 0 && $processed >= $maxJobs) {
                break;
            }
        }
        return $this->success();
    }
}
