<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Application;
use App\Core\Console\Command;
use App\Core\Console\Input;
use App\Core\Console\Output;
use Modules\Invoicing\Repositories\InvoiceRepository;

final class InvoiceOverdueCommand extends Command
{
    protected string $name = 'invoices:mark-overdue';

    protected string $description = 'Bascule les factures échues non soldées au statut overdue';

    public function handle(Input $input, Output $output): int
    {
        $count = Application::container()->get(InvoiceRepository::class)->markOverdue();
        $output->success("{$count} facture(s) passée(s) en retard.");
        return $this->success();
    }
}
