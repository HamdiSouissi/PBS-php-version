<?php

declare(strict_types=1);

namespace App\Core\Http\Middleware;

use App\Core\Http\Request;
use App\Core\Http\Response;

/**
 * Contrat commun des middlewares HTTP.
 */
interface MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response;
}
