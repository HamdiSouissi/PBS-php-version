<?php

declare(strict_types=1);

namespace App\Core;

use Closure;
use RuntimeException;

/**
 * Conteneur d'injection de dépendances maison.
 * Singletons, fabriques, liaisons d'interfaces et résolution paresseuse.
 */
final class Container
{
    /** @var array<string,Closure> */
    private array $factories = [];

    /** @var array<string,object> */
    private array $singletons = [];

    /** @var array<string,string> interface => implémentation */
    private array $aliases = [];

    public function bind(string $id, Closure $factory): void
    {
        $this->factories[$id] = $factory;
        unset($this->singletons[$id]);
    }

    public function singleton(string $id, Closure $factory): void
    {
        $this->factories[$id] = $factory;
    }

    public function instance(string $id, object $instance): void
    {
        $this->singletons[$id] = $instance;
        $this->factories[$id] = static fn () => $instance;
    }

    public function alias(string $abstract, string $concrete): void
    {
        $this->aliases[$abstract] = $concrete;
    }

    public function has(string $id): bool
    {
        return isset($this->factories[$id]) || isset($this->singletons[$id]) || class_exists($id);
    }

    public function get(string $id): mixed
    {
        $id = $this->aliases[$id] ?? $id;
        if (isset($this->singletons[$id])) {
            return $this->singletons[$id];
        }
        if (isset($this->factories[$id])) {
            $object = ($this->factories[$id])($this);
            $this->singletons[$id] = $object;
            return $object;
        }
        if (class_exists($id)) {
            $object = $this->build($id);
            $this->singletons[$id] = $object;
            return $object;
        }
        throw new RuntimeException("Service introuvable dans le conteneur : {$id}");
    }

    /**
     * Instanciation automatique avec injection au constructeur (limitée aux
     * dépendances concrètes et aux services déjà liés).
     *
     * @param class-string $class
     * @param array<string,mixed> $override
     */
    public function build(string $class, array $override = []): object
    {
        $reflection = new \ReflectionClass($class);
        $constructor = $reflection->getConstructor();
        if ($constructor === null) {
            return new $class();
        }
        $arguments = [];
        foreach ($constructor->getParameters() as $parameter) {
            $name = $parameter->getName();
            if (array_key_exists($name, $override)) {
                $arguments[] = $override[$name];
                continue;
            }
            $type = $parameter->getType();
            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $arguments[] = $this->get($type->getName());
            } elseif ($parameter->isDefaultValueAvailable()) {
                $arguments[] = $parameter->getDefaultValue();
            } elseif ($parameter->allowsNull()) {
                $arguments[] = null;
            } else {
                throw new RuntimeException("Impossible de résoudre le paramètre \${$name} de {$class}");
            }
        }
        return $reflection->newInstanceArgs($arguments);
    }
}
