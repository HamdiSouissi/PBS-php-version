<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Application;
use App\Core\Console\Command;
use App\Core\Console\Input;
use App\Core\Console\Output;
use Modules\Invoicing\Services\RecurringInvoiceService;

final class RecurringInvoiceRunCommand extends Command
{
    protected string $name = 'invoices:recurring-run';

    protected string $description = 'Génère les factures récurrentes dont la date est due';

    public function handle(Input $input, Output $output): int
    {
        $created = Application::container()->get(RecurringInvoiceService::class)->runDue();
        $output->success(count($created) . ' facture(s) récurrente(s) générée(s).');
        return $this->success();
    }
}
