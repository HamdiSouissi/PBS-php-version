<?php

declare(strict_types=1);

namespace App\Core\Http;

use App\Core\Auth\AuthManager;
use App\Core\Container;
use App\Core\Validation\Validator;
use App\Core\View\View;

/**
 * Contrôleur de base : services transversaux accessibles aux modules.
 */
abstract class Controller
{
    protected function view(string $template, array $data = [], ?string $layout = 'layouts/app'): View
    {
        return view($template, $data, $layout);
    }

    protected function validator(): Validator
    {
        return \App\Core\Application::container()->get(Validator::class);
    }

    protected function auth(): AuthManager
    {
        return \App\Core\Application::container()->get(AuthManager::class);
    }

    /**
     * Extrait les entiers d'un paramètre de route.
     */
    protected function intId(mixed $value): int
    {
        return (int) $value;
    }
}
