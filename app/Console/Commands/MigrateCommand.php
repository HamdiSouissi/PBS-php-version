<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Console\Command;
use App\Core\Console\Input;
use App\Core\Console\Output;
use App\Core\Database\Connection;
use App\Core\Database\MigrationRunner;

final class MigrateCommand extends Command
{
    protected string $name = 'migrate:run';

    protected string $description = 'Exécute les migrations SQL sur la base configurée';

    public function handle(Input $input, Output $output): int
    {
        $runner = new MigrationRunner(
            \App\Core\Application::container()->get(Connection::class),
            base_path('database/migrations'),
            (string) config('database.migrations_table')
        );
        $executed = $runner->run();
        if ($executed === []) {
            $output->info('Aucune migration en attente. Base à jour.');
            return $this->success();
        }
        foreach ($executed as $migration) {
            $output->success("Migration appliquée : {$migration}");
        }
        return $this->success();
    }
}
