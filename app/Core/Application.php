<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Audit\AuditLogger;
use App\Core\Auth\AuthManager;
use App\Core\Cache\CacheInterface;
use App\Core\Cache\FileCache;
use App\Core\Database\Connection;
use App\Core\Encryption\Crypto;
use App\Core\Event\EventDispatcher;
use App\Core\Filesystem\FilesystemManager;
use App\Core\Http\Middleware\MiddlewarePipeline;
use App\Core\Logger\Logger;
use App\Core\Queue\QueueManager;
use App\Core\Router\Router;
use App\Core\Scheduler\Scheduler;
use App\Core\Security\Csrf;
use App\Core\Security\RateLimiter;
use App\Core\Session\FlashBag;
use App\Core\Session\SessionManager;
use App\Core\Support\Config;
use App\Core\Support\Env;
use App\Core\Validation\Validator;
use App\Core\View\ViewRenderer;

/**
 * Noyau de l'application : bootstrap, enregistrement des services,
 * accès global au conteneur.
 */
final class Application
{
    private static ?Container $container = null;

    public readonly string $basePath;

    private bool $booted = false;

    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, '/\\');
        self::$container = new Container();
        self::$container->instance(self::class, $this);
    }

    public static function container(): Container
    {
        if (self::$container === null) {
            throw new \LogicException('Application non initialisée.');
        }
        return self::$container;
    }

    public function bootstrap(): void
    {
        if ($this->booted) {
            return;
        }

        Env::load($this->basePath . '/.env');
        Config::load($this->basePath . '/config');

        date_default_timezone_set(config('app.timezone', 'Europe/Paris'));
        mb_internal_encoding('UTF-8');

        $this->registerCoreServices();
        $this->booted = true;
    }

    private function registerCoreServices(): void
    {
        $c = self::$container;
        $root = $this->basePath;

        $c->singleton(Logger::class, static fn () => new Logger($root . '/storage/logs/app.log'));
        $c->singleton(CacheInterface::class, static fn () => new FileCache($root . '/storage/cache'));
        $c->singleton(Connection::class, static fn () => new Connection(config('database')));
        $c->singleton(SessionManager::class, static fn (Container $container) => new SessionManager(
            config('session'),
            $container->get(Connection::class)
        ));
        $c->singleton(FlashBag::class, static fn (Container $container) => new FlashBag(
            $container->get(SessionManager::class)
        ));
        $c->singleton(Csrf::class, static fn (Container $container) => new Csrf(
            $container->get(SessionManager::class)
        ));
        $c->singleton(\App\Core\Security\Password::class, static fn () => new \App\Core\Security\Password(
            config('security.password')
        ));
        $c->singleton(\App\Core\Security\Jwt::class, static fn () => new \App\Core\Security\Jwt(
            config('security.jwt')
        ));
        $c->singleton(\App\Core\Security\Totp::class, static fn () => new \App\Core\Security\Totp(
            config('security.totp')
        ));
        $c->singleton(RateLimiter::class, static fn (Container $container) => new RateLimiter(
            $container->get(CacheInterface::class),
            config('security.rate_limit')
        ));
        $c->singleton(Validator::class, static fn () => new Validator());
        $c->singleton(Router::class, static fn () => new Router());
        $c->singleton(MiddlewarePipeline::class, static fn () => new MiddlewarePipeline());
        $c->singleton(EventDispatcher::class, static fn () => new EventDispatcher());
        $c->singleton(QueueManager::class, static fn () => new QueueManager(config('queue')));
        $c->singleton(Scheduler::class, static fn () => new Scheduler($root . '/storage/scheduler'));
        $c->singleton(FilesystemManager::class, static fn () => new FilesystemManager(config('filesystems')));
        $c->singleton(Crypto::class, static fn () => new Crypto(config('app.key')));
        $c->singleton(AuthManager::class, static fn (Container $container) => new AuthManager(
            $container->get(SessionManager::class),
            $container->get(Connection::class)
        ));
        $c->singleton(AuditLogger::class, static fn (Container $container) => new AuditLogger(
            $container->get(Connection::class),
            $container->get(AuthManager::class)
        ));
        $c->singleton(ViewRenderer::class, static fn (Container $container) => new ViewRenderer(
            $root . '/modules',
            $root . '/app/Views',
            $container
        ));
    }

    public function isDebug(): bool
    {
        return (bool) config('app.debug', false);
    }

    public function isProduction(): bool
    {
        return config('app.env') === 'production';
    }
}
