<?php

declare(strict_types=1);

namespace App\Core\Http\Middleware;

use App\Core\Container;
use App\Core\Http\Request;
use App\Core\Http\Response;

/**
 * Pipeline de middlewares maison (oignon), avec paramètres optionnels.
 */
final class MiddlewarePipeline
{
    /** @var list<array{class:string|MiddlewareInterface,params:list<string>}> */
    private array $stack = [];

    /** @param list<string> $params */
    public function pipe(string|MiddlewareInterface $middleware, array $params = []): self
    {
        $this->stack[] = ['class' => $middleware, 'params' => $params];
        return $this;
    }

    public function handle(Request $request, callable $core, ?Container $container = null): Response
    {
        $pipeline = array_reduce(
            array_reverse($this->stack),
            static fn (callable $next, array $entry): callable =>
                static function (Request $request) use ($next, $entry, $container): Response {
                    $middleware = $entry['class'];
                    $instance = $middleware instanceof MiddlewareInterface
                        ? $middleware
                        : ($container?->get($middleware) ?? new $middleware());
                    return $instance->handle($request, $next, ...$entry['params']);
                },
            $core
        );
        return $pipeline($request);
    }
}
