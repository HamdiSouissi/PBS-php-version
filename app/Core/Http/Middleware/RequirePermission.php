<?php

declare(strict_types=1);

namespace App\Core\Http\Middleware;

use App\Core\Auth\AuthManager;
use App\Core\Exception\AuthorizationException;
use App\Core\Http\Request;
use App\Core\Http\Response;

/**
 * Garde-fou RBAC côté serveur. Usage route : "permission:employees.read".
 */
final class RequirePermission implements MiddlewareInterface
{
    public function __construct(private readonly AuthManager $auth)
    {
    }

    public function handle(Request $request, callable $next, string $permission = ''): Response
    {
        if ($permission === '' || !$this->auth->hasPermission($permission)) {
            throw new AuthorizationException($permission === '' ? 'Accès refusé.' : "Permission requise : {$permission}");
        }
        return $next($request);
    }
}
