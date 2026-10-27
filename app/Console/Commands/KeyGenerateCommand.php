<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\Console\Command;
use App\Core\Console\Input;
use App\Core\Console\Output;
use App\Core\Encryption\Crypto;

/**
 * Génère APP_KEY et JWT_SECRET dans le fichier .env.
 */
final class KeyGenerateCommand extends Command
{
    protected string $name = 'app:key-generate';

    protected string $description = 'Génère les clés secrètes APP_KEY et JWT_SECRET';

    public function handle(Input $input, Output $output): int
    {
        $envFile = base_path('.env');
        if (!is_file($envFile)) {
            $output->error('.env introuvable. Copiez .env.example en .env.');
            return $this->failure();
        }
        $contents = (string) file_get_contents($envFile);
        $appKey = Crypto::generateKey();
        $jwtSecret = bin2hex(random_bytes(32));
        $contents = preg_replace('/^APP_KEY=.*$/m', 'APP_KEY=' . $appKey, $contents);
        $contents = preg_replace('/^JWT_SECRET=.*$/m', 'JWT_SECRET=' . $jwtSecret, $contents);
        file_put_contents($envFile, $contents);
        chmod($envFile, 0640);
        $output->success('Clés générées dans .env (APP_KEY, JWT_SECRET).');
        return $this->success();
    }
}
