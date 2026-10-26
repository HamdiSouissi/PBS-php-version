<?php

declare(strict_types=1);

namespace App\Core\Router;

use App\Core\Exception\HttpException;

/**
 * Routeur maison : groupes, préfixes, middlewares et routes nommées.
 */
final class Router
{
    /** @var list<Route> */
    private array $routes = [];

    private string $prefix = '';

    /** @var list<string> */
    private array $groupMiddleware = [];

    /**
     * @param list<string> $middleware
     */
    public function group(array $middleware, string $prefix, callable $callback): void
    {
        $previousPrefix = $this->prefix;
        $previousMiddleware = $this->groupMiddleware;
        $this->prefix = $previousPrefix . $prefix;
        $this->groupMiddleware = array_merge($previousMiddleware, $middleware);
        $callback($this);
        $this->prefix = $previousPrefix;
        $this->groupMiddleware = $previousMiddleware;
    }

    public function get(string $pattern, mixed $action): Route
    {
        return $this->add(['GET'], $pattern, $action);
    }

    public function post(string $pattern, mixed $action): Route
    {
        return $this->add(['POST'], $pattern, $action);
    }

    public function put(string $pattern, mixed $action): Route
    {
        return $this->add(['PUT'], $pattern, $action);
    }

    public function patch(string $pattern, mixed $action): Route
    {
        return $this->add(['PATCH'], $pattern, $action);
    }

    public function delete(string $pattern, mixed $action): Route
    {
        return $this->add(['DELETE'], $pattern, $action);
    }

    public function any(string $pattern, mixed $action): Route
    {
        return $this->add(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], $pattern, $action);
    }

    private function add(array $methods, string $pattern, mixed $action): Route
    {
        $fullPattern = $this->prefix . ($pattern === '/' ? '' : $pattern);
        $route = new Route($methods, $fullPattern === '' ? '/' : $fullPattern, $action);
        $route->middleware(...$this->groupMiddleware);
        $this->routes[] = $route;
        return $route;
    }

    /**
     * @return array{route:Route,params:array<string,string>}
     */
    public function resolve(string $method, string $path): array
    {
        $methodMatched = false;
        foreach ($this->routes as $route) {
            $params = $route->match($method, $path);
            if ($params !== null) {
                return ['route' => $route, 'params' => $params];
            }
            // Mémorise l'existence d'une route sur ce chemin avec un autre verbe.
            foreach ($route->getMethods() as $verb) {
                $probe = (new Route($verb, $route->getPattern(), null))->match($verb, $path);
                if ($probe !== null) {
                    $methodMatched = true;
                }
            }
        }
        throw $methodMatched
            ? new HttpException(405, 'Méthode HTTP non autorisée pour cette route.')
            : new HttpException(404, 'Route introuvable.');
    }

    /**
     * Génère l'URL d'une route nommée.
     * @param array<string,int|string> $params
     */
    public function routeUrl(string $name, array $params = []): string
    {
        foreach ($this->routes as $route) {
            if ($route->getName() === $name) {
                $url = $route->getPattern();
                foreach ($params as $key => $value) {
                    $url = str_replace('{' . $key . '}', (string) $value, $url);
                }
                return $url;
            }
        }
        throw new \InvalidArgumentException("Route nommée introuvable : {$name}");
    }

    /**
     * @return list<Route>
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }
}
