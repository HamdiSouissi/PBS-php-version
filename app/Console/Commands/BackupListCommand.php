<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Application;
use App\Core\Console\Command;
use App\Core\Console\Input;
use App\Core\Console\Output;
use Modules\Compliance\Services\BackupService;

final class BackupListCommand extends Command
{
    protected string $name = 'backup:list';

    protected string $description = 'Liste les sauvegardes disponibles avec leur taille';

    public function handle(Input $input, Output $output): int
    {
        $backups = Application::container()->get(BackupService::class)->listBackups();
        if ($backups === []) {
            $output->info('Aucune sauvegarde.');
            return $this->success();
        }
        foreach ($backups as $backup) {
            $output->writeln(sprintf(
                '%s | %s octets | %s',
                $backup['file'],
                number_format((float) $backup['size'], 0, ',', ' '),
                $backup['created_at']
            ));
        }
        return $this->success();
    }
}
