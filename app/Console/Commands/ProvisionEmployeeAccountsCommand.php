<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Application;
use App\Core\Console\Command;
use App\Core\Console\Input;
use App\Core\Console\Output;
use Modules\Employees\Services\EmployeeAccountService;

/**
 * Ancienne commande conservée uniquement pour informer de sa désactivation.
 */
final class ProvisionEmployeeAccountsCommand extends Command
{
    protected string $name = 'employees:provision-accounts';

    protected string $description = "Commande désactivée : gestion explicite depuis la fiche salarié";

    public function handle(Input $input, Output $output): int
    {
        $output->error('Commande désactivée : créez ou rattachez chaque compte explicitement depuis la fiche salarié.');
        return $this->failure();
    }
}
