<?php

declare(strict_types=1);

namespace App\Core\Event;

/**
 * Dispatcher d'événements maison (pattern observateur, synchrone).
 */
final class EventDispatcher
{
    /** @var array<class-string,list<class-string|callable>> */
    private array $listeners = [];

    /** @param class-string $eventClass @param class-string|callable $listener */
    public function listen(string $eventClass, string|callable $listener): void
    {
        $this->listeners[$eventClass][] = $listener;
    }

    public function dispatch(object $event): void
    {
        foreach ($this->listeners[$event::class] ?? [] as $listener) {
            if (is_string($listener)) {
                $instance = \App\Core\Application::container()->get($listener);
                if (!method_exists($instance, 'handle')) {
                    throw new \RuntimeException("Le listener {$listener} doit implémenter handle().");
                }
                $instance->handle($event);
            } else {
                $listener($event);
            }
        }
    }
}
