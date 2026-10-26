<?php

declare(strict_types=1);

namespace App\Core\Http;

use App\Core\Container;
use App\Core\Exception\ExceptionHandler;
use App\Core\Exception\HttpException;
use App\Core\Http\Middleware\MiddlewarePipeline;
use App\Core\Router\Route;
use App\Core\Router\Router;
use Throwable;

/**
 * Kernel HTTP : résolution de la route, pipeline de middlewares,
 * exécution du contrôleur et gestion centralisée des exceptions.
 */
final class Kernel
{
    /**
     * Alias des middlewares nommés utilisables dans les routes.
     *
     * @var array<string,class-string>
     */
    private array $middlewareAliases = [];

    /** @var list<class-string> middlewares globaux (toutes les requêtes) */
    private array $globalMiddleware = [];

    public function __construct(
        private readonly Container $container,
        private readonly Router $router,
        private readonly ExceptionHandler $exceptionHandler
    ) {
    }

    /** @param array<string,class-string> $aliases */
    public function setMiddlewareAliases(array $aliases): void
    {
        $this->middlewareAliases = $aliases;
    }

    /** @param list<class-string> $middleware */
    public function setGlobalMiddleware(array $middleware): void
    {
        $this->globalMiddleware = $middleware;
    }

    public function handle(Request $request): Response
    {
        try {
            $resolved = $this->router->resolve($request->method(), $request->path());
            /** @var Route $route */
            $route = $resolved['route'];

            $pipeline = $this->container->get(MiddlewarePipeline::class);
            foreach ($this->globalMiddleware as $middleware) {
                $pipeline->pipe($middleware);
            }
            foreach ($route->getMiddleware() as $name) {
                [$alias, $parameters] = $this->parseMiddleware($name);
                $class = $this->middlewareAliases[$alias]
                    ?? throw new HttpException(500, "Middleware inconnu : {$alias}");
                $pipeline->pipe($class, $parameters);
            }

            $params = $resolved['params'];
            $core = function (Request $request) use ($route, $params): Response {
                return $this->dispatch($route, $request, $params);
            };

            return $pipeline->handle($request, $core, $this->container);
        } catch (Throwable $exception) {
            return $this->exceptionHandler->handle($exception, $request);
        }
    }

    /**
     * Décompose "alias:arg1,arg2" en alias + paramètres.
     *
     * @return array{0:string,1:list<string>}
     */
    private function parseMiddleware(string $declaration): array
    {
        if (!str_contains($declaration, ':')) {
            return [$declaration, []];
        }
        [$alias, $parameterString] = explode(':', $declaration, 2);
        return [$alias, explode(',', $parameterString)];
    }

    /**
     * @param array<string,string> $params
     */
    private function dispatch(Route $route, Request $request, array $params): Response
    {
        $action = $route->getAction();

        if (is_callable($action) && !is_array($action)) {
            $result = $action($request, ...array_values($params));
        } else {
            [$class, $method] = $action;
            $controller = $this->container->get($class);
            if (!method_exists($controller, $method)) {
                throw new HttpException(500, "Action introuvable : {$class}::{$method}");
            }
            $result = $controller->$method($request, ...array_values($params));
        }

        if ($result instanceof Response) {
            return $result;
        }
        if (is_array($result)) {
            return new JsonResponse($result);
        }
        if (is_string($result)) {
            return new Response($result);
        }
        throw new HttpException(500, 'Le contrôleur doit retourner Response|array|string.');
    }
}
