<?php

declare(strict_types=1);

namespace App\Core\Security;

use App\Core\Cache\CacheInterface;

/**
 * Limiteur de débit à fenêtre glissante (cache file/redis).
 */
final class RateLimiter
{
    /** @param array<string,mixed> $config */
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly array $config
    ) {
    }

    public function isEnabled(): bool
    {
        return (bool) $this->config['enabled'];
    }

    public function attempt(string $key, ?int $max = null, ?int $window = null): bool
    {
        if (!$this->isEnabled()) {
            return true;
        }
        $max ??= (int) $this->config['max'];
        $window ??= (int) $this->config['window'];

        $cacheKey = 'ratelimit:' . $key;
        /** @var array{count:int,reset:int} $bucket */
        $bucket = $this->cache->get($cacheKey, ['count' => 0, 'reset' => time() + $window]);
        if (time() > $bucket['reset']) {
            $bucket = ['count' => 0, 'reset' => time() + $window];
        }
        $bucket['count']++;
        $this->cache->set($cacheKey, $bucket, $window);
        return $bucket['count'] <= $max;
    }

    public function remaining(string $key, ?int $max = null): int
    {
        $max ??= (int) $this->config['max'];
        /** @var array{count:int,reset:int} $bucket */
        $bucket = $this->cache->get('ratelimit:' . $key, ['count' => 0, 'reset' => 0]);
        return max(0, $max - $bucket['count']);
    }

    public function clear(string $key): void
    {
        $this->cache->delete('ratelimit:' . $key);
    }
}
