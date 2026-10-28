<?php

declare(strict_types=1);

/**
 * Enregistrement des commandes console.
 */

use App\Console\Commands\BackupListCommand;
use App\Console\Commands\BackupRunCommand;
use App\Console\Commands\BackupRestoreCommand;
use App\Console\Commands\DatabaseSeedCommand;
use App\Console\Commands\KeyGenerateCommand;
use App\Console\Commands\MigrateCommand;
use App\Console\Commands\ProvisionEmployeeAccountsCommand;
use App\Console\Commands\MigrateRollbackCommand;
use App\Console\Commands\InvoiceOverdueCommand;
use App\Console\Commands\MigrateStatusCommand;
use App\Console\Commands\QueueWorkCommand;
use App\Console\Commands\RecurringInvoiceRunCommand;
use App\Console\Commands\ScheduleRunCommand;
use App\Console\Commands\SubscriptionGenerateCommand;
use App\Console\Commands\TestRunnerCommand;
use App\Console\Commands\UserCreateCommand;
use App\Core\Console\ConsoleKernel;

return static function (ConsoleKernel $kernel): void {
    $kernel->register(KeyGenerateCommand::class);
    $kernel->register(MigrateCommand::class);
    $kernel->register(MigrateStatusCommand::class);
    $kernel->register(MigrateRollbackCommand::class);
    $kernel->register(DatabaseSeedCommand::class);
    $kernel->register(QueueWorkCommand::class);
    $kernel->register(ScheduleRunCommand::class);
    $kernel->register(BackupRunCommand::class);
    $kernel->register(BackupRestoreCommand::class);
    $kernel->register(BackupListCommand::class);
    $kernel->register(SubscriptionGenerateCommand::class);
    $kernel->register(InvoiceOverdueCommand::class);
    $kernel->register(RecurringInvoiceRunCommand::class);
    $kernel->register(UserCreateCommand::class);
    $kernel->register(ProvisionEmployeeAccountsCommand::class);
    $kernel->register(TestRunnerCommand::class);
};
