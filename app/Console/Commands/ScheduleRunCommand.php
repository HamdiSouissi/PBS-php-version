<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Application;
use App\Core\Console\Command;
use App\Core\Console\Input;
use App\Core\Console\Output;
use App\Core\Console\ConsoleKernel;
use App\Core\Scheduler\Scheduler;
use Modules\Subscriptions\Services\SubscriptionScheduler;

/**
 * Lance les tâches planifiées dues (à appeler chaque minute via cron).
 */
final class ScheduleRunCommand extends Command
{
    protected string $name = 'schedule:run';

    protected string $description = 'Exécute les tâches planifiées dues';

    public function handle(Input $input, Output $output): int
    {
        $scheduler = Application::container()->get(Scheduler::class);

        // Déclarations des tâches récurrentes de l'application.
        $scheduler->command('7 3 * * *', 'subscriptions:generate', 'Génération des factures d\'abonnements');
        $scheduler->command('15 3 1 * *', 'backup:run', 'Sauvegarde mensuelle de la base');
        $scheduler->command('30 2 * * *', 'queue:work --max-jobs=50', 'Vidage de la file quotidienne');

        $due = $scheduler->due();
        if ($due === []) {
            $output->info('Aucune tâche planifiée due.');
            return $this->success();
        }
        $console = new ConsoleKernel(Application::container());
        (require base_path('app/commands.php'))($console);
        foreach ($due as $commandLine) {
            [$name, $args] = array_pad(explode(' ', $commandLine, 2), 2, null);
            $tokens = array_filter(array_merge(['bin/console', $name], $args ? explode(' ', $args) : []));
            $output->info("Exécution planifiée : {$commandLine}");
            if ($name === 'subscriptions:generate') {
                Application::container()->get(SubscriptionScheduler::class)->generateDueInvoices();
            }
            unset($tokens);
        }
        return $this->success();
    }
}
