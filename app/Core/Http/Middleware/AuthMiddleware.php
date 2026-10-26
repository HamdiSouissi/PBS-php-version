<?php

declare(strict_types=1);

namespace App\Core\Http\Middleware;

use App\Core\Auth\AuthManager;
use App\Core\Exception\AuthenticationException;
use App\Core\Http\RedirectResponse;
use App\Core\Http\Request;
use App\Core\Http\Response;

/**
 * Authentification web obligatoire (redirige vers /login).
 */
final class AuthMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly AuthManager $auth)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        if ($this->auth->guest()) {
            if ($request->isAjax()) {
                throw new AuthenticationException();
            }
            return new RedirectResponse('/login');
        }
        return $next($request);
    }
}
