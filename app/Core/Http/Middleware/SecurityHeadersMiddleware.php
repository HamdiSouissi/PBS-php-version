<?php

declare(strict_types=1);

namespace App\Core\Http\Middleware;

use App\Core\Http\Request;
use App\Core\Http\Response;

/**
 * Ajoute les en-têtes de sécurité recommandés (OWASP).
 */
final class SecurityHeadersMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        $response = $next($request);
        $response->withHeader('X-Content-Type-Options', 'nosniff');
        $response->withHeader('X-Frame-Options', 'DENY');
        $response->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        // Toutes les bibliothèques front sont self-hosted (public/assets/vendor) :
        // aucune origine tierce n'est nécessaire. 'unsafe-inline' couvre les
        // scripts de thème anti-scintillement et les styles à jetons des vues.
        $response->withHeader(
            'Content-Security-Policy',
            "default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; "
            . "script-src 'self' 'unsafe-inline'; font-src 'self'; "
            . "connect-src 'self'; frame-ancestors 'none'"
        );
        $response->withHeader('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');
        return $response;
    }
}
