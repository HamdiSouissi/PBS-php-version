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
        $response->withHeader(
            'Content-Security-Policy',
            "default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; "
            . "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; font-src 'self' https://cdn.jsdelivr.net; "
            . "connect-src 'self'; frame-ancestors 'none'"
        );
        $response->withHeader('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');
        return $response;
    }
}
