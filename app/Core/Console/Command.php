<?php

declare(strict_types=1);

namespace App\Core\Console;

/**
 * Classe de base des commandes console.
 */
abstract class Command
{
    protected string $name = '';

    protected string $description = '';

    abstract public function handle(Input $input, Output $output): int;

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    protected function success(): int
    {
        return 0;
    }

    protected function failure(): int
    {
        return 1;
    }
}
