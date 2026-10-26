<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Console\Command;
use App\Core\Console\Input;
use App\Core\Console\Output;
use App\Core\Database\Connection;
use App\Core\Database\MigrationRunner;

final class MigrateStatusCommand extends Command
{
    protected string $name = 'migrate:status';

    protected string $description = 'Affiche l\'état des migrations';

    public function handle(Input $input, Output $output): int
    {
        $runner = new MigrationRunner(
            \App\Core\Application::container()->get(Connection::class),
            base_path('database/migrations'),
            (string) config('database.migrations_table')
        );
        foreach ($runner->status() as $row) {
            $output->writeln(sprintf('  %-45s %s', $row['migration'], $row['applied_at']));
        }
        return $this->success();
    }
}
