<?php

declare(strict_types=1);

namespace App\Core\Router;

/**
 * Déclaration d'une route : méthode HTTP, motif, action et middlewares.
 */
final class Route
{
    /** @var list<string> */
    private array $middleware = [];

    private ?string $name = null;

    /** @var list<string> */
    private array $methods;

    /**
     * @param list<string>|string $methods
     * @param array{0:class-string,1:string}|callable $action
     */
    public function __construct(
        array|string $methods,
        private readonly string $pattern,
        private readonly mixed $action
    ) {
        $this->methods = is_array($methods) ? array_map('strtoupper', $methods) : [strtoupper($methods)];
    }

    public function middleware(string ...$middleware): self
    {
        array_push($this->middleware, ...$middleware);
        return $this;
    }

    public function name(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getMethods(): array
    {
        return $this->methods;
    }

    public function getAction(): mixed
    {
        return $this->action;
    }

    /**
     * @return array<string,string> paramètres extraits de l'URI, ou [] si pas de correspondance
     */
    public function match(string $method, string $path): ?array
    {
        if (!in_array(strtoupper($method), $this->methods, true)) {
            return null;
        }
        $pattern = $this->pattern === '' ? '/' : $this->pattern;
        $regex = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $pattern);
        $anchored = $regex === '/' ? '/' : rtrim((string) $regex, '/');
        $regex = '#^' . $anchored . '$#';
        if (preg_match($regex, $path, $matches)) {
            return array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
        }
        return null;
    }

    public function getMiddleware(): array
    {
        return $this->middleware;
    }

    public function getPattern(): string
    {
        return $this->pattern;
    }
}
