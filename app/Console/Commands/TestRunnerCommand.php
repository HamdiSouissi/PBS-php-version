<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Console\Command;
use App\Core\Console\Input;
use App\Core\Console\Output;
use Tests\Core\TestRunner;

/**
 * Exécute les tests via le moteur maison quand PHPUnit n'est pas installé
 * (environnement restreint). En environnement Docker, `composer test` lance
 * PHPUnit avec la même configuration et les mêmes classes.
 */
final class TestRunnerCommand extends Command
{
    protected string $name = 'test:run';

    protected string $description = 'Exécute la suite de tests (moteur maison compatible PHPUnit)';

    public function handle(Input $input, Output $output): int
    {
        $suite = $input->argument(1);
        $paths = match ($suite) {
            'unit' => [base_path('tests/Unit'), base_path('modules')],
            'functional' => [base_path('tests/Functional')],
            'integration' => [base_path('tests/Integration')],
            'api' => [base_path('tests/Api')],
            'security' => [base_path('tests/Security')],
            default => [
                base_path('tests'),
                base_path('modules'),
            ],
        };
        // En mode modules, ne garde que les dossiers Tests des modules.
        $resolved = [];
        foreach ($paths as $path) {
            if (basename($path) === 'modules') {
                foreach (glob($path . '/*/Tests') ?: [] as $moduleTests) {
                    $resolved[] = $moduleTests;
                }
                continue;
            }
            $resolved[] = $path;
        }

        $runner = new TestRunner();
        $exit = $runner->run($resolved);
        $output->writeln($runner->summary());
        return $exit === 0 ? $this->success() : $this->failure();
    }
}
