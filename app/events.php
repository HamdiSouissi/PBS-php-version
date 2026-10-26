<?php

declare(strict_types=1);

/**
 * Enregistrement centralisé des listeners d'événements.
 *
 * @var \App\Core\Container $container
 */

use App\Core\Event\EventDispatcher;

return static function (\App\Core\Container $container): void {
    $dispatcher = $container->get(EventDispatcher::class);

    $listeners = require __DIR__ . '/listeners.php';
    foreach ($listeners as $event => $eventListeners) {
        foreach ($eventListeners as $listener) {
            $dispatcher->listen($event, $listener);
        }
    }
};
