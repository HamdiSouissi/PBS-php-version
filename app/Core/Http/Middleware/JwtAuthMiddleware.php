<?php

declare(strict_types=1);

namespace App\Core\Http\Middleware;

use App\Core\Auth\AuthManager;
use App\Core\Database\Connection;
use App\Core\Exception\AuthenticationException;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Security\Jwt;
use Modules\Authentication\Models\User;

/**
 * Authentification API par jeton JWT Bearer.
 */
final class JwtAuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly Jwt $jwt,
        private readonly Connection $db,
        private readonly AuthManager $auth
    ) {
    }

    public function handle(Request $request, callable $next): Response
    {
        $token = $request->bearerToken();
        if ($token === null) {
            throw new AuthenticationException('Jeton Bearer manquant.');
        }
        try {
            $claims = $this->jwt->verify($token);
        } catch (\Throwable $exception) {
            throw new AuthenticationException('Jeton invalide ou expiré : ' . $exception->getMessage());
        }
        if (($claims['typ'] ?? '') !== 'access') {
            throw new AuthenticationException('Type de jeton invalide.');
        }
        $user = \App\Core\Application::container()
            ->get(\Modules\Authentication\Repositories\UserRepository::class)
            ->findWithRights((int) ($claims['sub'] ?? 0));
        if ($user === null || !$user->isActive) {
            throw new AuthenticationException('Utilisateur lié au jeton introuvable.');
        }
        $this->auth->setUser($user);
        return $next($request);
    }
}
