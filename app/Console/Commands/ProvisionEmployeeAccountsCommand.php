<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Application;
use App\Core\Console\Command;
use App\Core\Console\Input;
use App\Core\Console\Output;
use Modules\Employees\Services\EmployeeAccountService;

/**
 * Reprise de parc : crée et rattache un compte utilisateur pour chaque
 * fiche salarié non encore rattachée (migration 016, comptes existants).
 */
final class ProvisionEmployeeAccountsCommand extends Command
{
    protected string $name = 'employees:provision-accounts';

    protected string $description = "Crée les comptes utilisateurs des salariés sans compte (rôle employe)";

    public function handle(Input $input, Output $output): int
    {
        $service = Application::container()->get(EmployeeAccountService::class);
        $stats = $service->provisionAll();

        $output->writeln(sprintf(
            'Comptes créés : %d | comptes existants rattachés : %d | salariés sans e-mail ignorés : %d',
            $stats['created'],
            $stats['linked'],
            $stats['skipped']
        ));

        return $this->success();
    }
}
