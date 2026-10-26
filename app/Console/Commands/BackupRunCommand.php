<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Application;
use App\Core\Console\Command;
use App\Core\Console\Input;
use App\Core\Console\Output;
use Modules\Compliance\Services\BackupService;

final class BackupRunCommand extends Command
{
    protected string $name = 'backup:run';

    protected string $description = 'Réalise une sauvegarde SQL (pg_dump) et des documents';

    public function handle(Input $input, Output $output): int
    {
        $service = Application::container()->get(BackupService::class);
        $file = $service->createBackup();
        $output->success("Sauvegarde créée : {$file}");
        return $this->success();
    }
}
