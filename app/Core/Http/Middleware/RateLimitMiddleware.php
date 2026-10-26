<?php

declare(strict_types=1);

namespace App\Core\Http\Middleware;

use App\Core\Exception\HttpException;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Security\RateLimiter;

/**
 * Limite le débit par IP et par clé de zone. Usage : "throttle:login,5,60".
 */
final class RateLimitMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly RateLimiter $limiter)
    {
    }

    public function handle(Request $request, callable $next, string $zone = 'global', string $max = '60', string $window = '60'): Response
    {
        $key = $zone . ':' . $request->ip();
        if (!$this->limiter->attempt($key, (int) $max, (int) $window)) {
            throw new HttpException(429, 'Trop de tentatives. Veuillez réessayer plus tard.');
        }
        return $next($request);
    }
}
