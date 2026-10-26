<?php

declare(strict_types=1);

namespace App\Core\Session;

/**
 * Messages flash et conservation des saisies de formulaire.
 */
final class FlashBag
{
    public function __construct(private readonly SessionManager $session)
    {
    }

    public function set(string $type, string $message): void
    {
        $_SESSION['_flash'][$type][] = $message;
    }

    /** @return array<string,list<string>> */
    public function pull(): array
    {
        $flash = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $flash;
    }

    public function old(string $key, mixed $default = ''): mixed
    {
        return $_SESSION['_flash']['_old'][$key] ?? $default;
    }

    /** @return array<string,string> */
    public function errors(): array
    {
        return $_SESSION['_flash']['errors'] ?? [];
    }
}
