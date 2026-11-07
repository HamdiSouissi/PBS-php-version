<?php

declare(strict_types=1);

namespace App\Core\Http\Middleware;

use App\Core\Exception\HttpException;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Security\Csrf;

/**
 * Vérifie le jeton CSRF sur toute écriture des routes qui utilisent ce middleware.
 */
final class CsrfMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly Csrf $csrf)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $token = $request->input('_csrf') ?? $request->header('x-csrf-token');
            if (!$this->csrf->validate(is_array($token) ? null : (string) $token)) {
                throw new HttpException(419, 'Jeton CSRF invalide ou expiré. Veuillez renvoyer le formulaire.');
            }
        }
        return $next($request);
    }
}
