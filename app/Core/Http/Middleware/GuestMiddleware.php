<?php

declare(strict_types=1);

namespace App\Core\Http\Middleware;

use App\Core\Auth\AuthManager;
use App\Core\Http\RedirectResponse;
use App\Core\Http\Request;
use App\Core\Http\Response;

/**
 * Réservé aux visiteurs non connectés (pages de connexion).
 */
final class GuestMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly AuthManager $auth)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        if ($this->auth->check()) {
            return new RedirectResponse('/dashboard');
        }
        return $next($request);
    }
}
