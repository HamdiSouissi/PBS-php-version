<?php

declare(strict_types=1);

namespace App\Core\Console;

/**
 * Sortie console colorisée (codes ANSI).
 */
final class Output
{
    public function writeln(string $line = ''): void
    {
        echo $line . PHP_EOL;
    }

    public function write(string $text): void
    {
        echo $text;
    }

    public function success(string $message): void
    {
        $this->writeln("\033[32m[OK]\033[0m {$message}");
    }

    public function error(string $message): void
    {
        $stderr = defined('STDERR') ? STDERR : @fopen('php://stderr', 'a');
        if ($stderr !== false && $stderr !== null) {
            fwrite($stderr, "\033[31m[ERREUR]\033[0m {$message}" . PHP_EOL);
        }
        // En contexte web (passerelle de développement), renvoyer aussi sur stdout.
        if (PHP_SAPI !== 'cli') {
            echo "\033[31m[ERREUR]\033[0m {$message}" . PHP_EOL;
        }
    }

    public function warning(string $message): void
    {
        $this->writeln("\033[33m[ATTENTION]\033[0m {$message}");
    }

    public function info(string $message): void
    {
        $this->writeln("\033[36m[INFO]\033[0m {$message}");
    }
}
