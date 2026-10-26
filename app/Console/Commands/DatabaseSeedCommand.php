<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Console\Command;
use App\Core\Console\Input;
use App\Core\Console\Output;
use Database\Seeds\DatabaseSeeder;

final class DatabaseSeedCommand extends Command
{
    protected string $name = 'db:seed';

    protected string $description = 'Charge les données de référence (rôles, PCG, règles de paie, admin)';

    public function handle(Input $input, Output $output): int
    {
        $seeder = new DatabaseSeeder(\App\Core\Application::container());
        $seeder->run();
        $output->success('Données de référence chargées.');
        return $this->success();
    }
}
