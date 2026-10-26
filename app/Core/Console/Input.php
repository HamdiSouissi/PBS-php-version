<?php

declare(strict_types=1);

namespace App\Core\Console;

/**
 * Paramètres d'une commande console.
 */
final class Input
{
    /** @param list<string> $argv */
    public function __construct(private readonly array $argv)
    {
    }

    public function commandName(): ?string
    {
        return $this->argv[1] ?? null;
    }

    public function argument(int $position): ?string
    {
        return $this->argv[$position + 1] ?? null;
    }

    public function option(string $name, ?string $default = null): ?string
    {
        foreach ($this->argv as $token) {
            if (str_starts_with($token, '--' . $name . '=')) {
                return substr($token, strlen('--' . $name . '='));
            }
            if ($token === '--' . $name) {
                return '1';
            }
        }
        return $default;
    }

    public function hasFlag(string $name): bool
    {
        return in_array('--' . $name, $this->argv, true);
    }
}
