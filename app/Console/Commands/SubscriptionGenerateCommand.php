<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Application;
use App\Core\Console\Command;
use App\Core\Console\Input;
use App\Core\Console\Output;
use Modules\Subscriptions\Services\SubscriptionScheduler;

final class SubscriptionGenerateCommand extends Command
{
    protected string $name = 'subscriptions:generate';

    protected string $description = 'Génère les factures des abonnements dus';

    public function handle(Input $input, Output $output): int
    {
        $count = Application::container()->get(SubscriptionScheduler::class)->generateDueInvoices();
        $output->success("{$count} facture(s) d'abonnement générée(s).");
        return $this->success();
    }
}
