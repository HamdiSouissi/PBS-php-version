<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Application;
use App\Core\Console\Command;
use App\Core\Console\Input;
use App\Core\Console\Output;
use Modules\Compliance\Services\BackupService;

final class BackupRestoreCommand extends Command
{
    protected string $name = 'backup:restore';

    protected string $description = 'Restaure une sauvegarde SQL (--file=chemin)';

    public function handle(Input $input, Output $output): int
    {
        $file = $input->option('file');
        if ($file === null) {
            $output->error('Option --file obligatoire.');
            return $this->failure();
        }
        Application::container()->get(BackupService::class)->restoreBackup($file);
        $output->success("Sauvegarde restaurée : {$file}");
        return $this->success();
    }
}
