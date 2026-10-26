<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Console\Command;
use App\Core\Console\Input;
use App\Core\Console\Output;
use App\Core\Database\Connection;
use App\Core\Database\MigrationRunner;

final class MigrateRollbackCommand extends Command
{
    protected string $name = 'migrate:rollback';

    protected string $description = 'Annule la dernière migration (script .down.sql requis)';

    public function handle(Input $input, Output $output): int
    {
        $runner = new MigrationRunner(
            \App\Core\Application::container()->get(Connection::class),
            base_path('database/migrations'),
            (string) config('database.migrations_table')
        );
        $name = $runner->rollbackLast();
        if ($name === null) {
            $output->info('Aucune migration à annuler.');
            return $this->success();
        }
        $output->warning("Migration annulée : {$name}");
        return $this->success();
    }
}
