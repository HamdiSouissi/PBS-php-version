<?php

declare(strict_types=1);

namespace App\Core\Support;

/**
 * Gestionnaire de configuration avec notation pointée (app.name) et cache mémoire.
 */
final class Config
{
    /** @var array<string,mixed> */
    private static array $items = [];

    /** @var array<string,mixed> */
    private static array $loaded = [];

    public static function load(string $configDir): void
    {
        self::$items = [];
        self::$loaded = [];
        foreach (glob($configDir . '/*.php') ?: [] as $file) {
            $key = basename($file, '.php');
            self::$loaded[$key] = require $file;
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, self::$items)) {
            return self::$items[$key];
        }
        $parts = explode('.', $key);
        $file = array_shift($parts);
        if (!isset(self::$loaded[$file])) {
            return $default;
        }
        $value = self::$loaded[$file];
        foreach ($parts as $part) {
            if (is_array($value) && array_key_exists($part, $value)) {
                $value = $value[$part];
            } else {
                return $default;
            }
        }
        return self::$items[$key] = $value;
    }

    public static function set(string $key, mixed $value): void
    {
        self::$items[$key] = $value;
    }

    /** @return array<string,mixed> */
    public static function all(string $file): array
    {
        return self::$loaded[$file] ?? [];
    }
}
