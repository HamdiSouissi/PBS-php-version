<?php

declare(strict_types=1);

namespace App\Core\Cache;

interface CacheInterface
{
    public function get(string $key, mixed $default = null): mixed;

    public function set(string $key, mixed $value, int $ttl = 3600): void;

    public function delete(string $key): void;

    public function clear(): void;
}
