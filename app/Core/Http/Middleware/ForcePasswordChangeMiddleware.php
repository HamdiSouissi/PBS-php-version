<?php

declare(strict_types=1);

namespace App\Core\Http\Middleware;

use App\Core\Auth\AuthManager;
use App\Core\Exception\AuthorizationException;
use App\Core\Http\RedirectResponse;
use App\Core\Http\Request;
use App\Core\Http\Response;

/**
 * Force le changement du mot de passe temporaire à la première connexion.
 *
 * Tant que users.must_change_password est vrai, l'utilisateur ne peut
 * atteindre que sa page de profil, la mise à jour de son mot de passe et
 * la déconnexion. Le middleware est global : il est sans effet pour les
 * invités et les comptes ayant déjà changé leur mot de passe.
 */
final class ForcePasswordChangeMiddleware implements MiddlewareInterface
{
    /** Routes autorisées pendant la période de changement forcé. */
    private const ALLOWED_PATHS = ['/profile', '/profile/password', '/logout', '/health'];

    public function __construct(private readonly AuthManager $auth)
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        $user = $this->auth->user();
        if ($user === null || !$user->mustChangePassword) {
            return $next($request);
        }

        if (in_array($request->path(), self::ALLOWED_PATHS, true)) {
            return $next($request);
        }

        if ($request->isApi() || $request->isAjax()) {
            throw new AuthorizationException(
                'Mot de passe temporaire : vous devez le changer avant de continuer.'
            );
        }

        flash('warning', 'Pour des raisons de sécurité, vous devez changer votre mot de passe temporaire.');
        return new RedirectResponse('/profile');
    }
}
