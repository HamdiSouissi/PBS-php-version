<?php

declare(strict_types=1);

namespace App\Core\Http\Middleware;

use App\Core\Auth\AuthManager;
use App\Core\Exception\AuthorizationException;
use App\Core\Http\Request;
use App\Core\Http\Response;

/**
 * Restreint une route à un rôle. Usage : "role:admin".
 */
final class RequireRole implements MiddlewareInterface
{
    public function __construct(private readonly AuthManager $auth)
    {
    }

    public function handle(Request $request, callable $next, string $role = ''): Response
    {
        if (!$this->auth->hasRole($role)) {
            throw new AuthorizationException("Rôle requis : {$role}");
        }
        return $next($request);
    }
}
