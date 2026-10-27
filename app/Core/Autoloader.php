<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Autoloader PSR-4 maison (PSR-1 / PSR-4 / PSR-12).
 *
 * Gère les préfixes App\ et Modules\ ainsi que les bibliothèques tierces
 * présentes dans /vendor (qu'elles soient installées par Composer ou
 * vendorisées manuellement en environnement hors-ligne).
 */
final class Autoloader
{
    /** @var array<string,string> préfixe => racine */
    private array $prefixes = [];

    private bool $registered = false;

    public function add(string $prefix, string $baseDir): self
    {
        $this->prefixes[rtrim($prefix, '\\') . '\\'] = rtrim($baseDir, '/\\') . DIRECTORY_SEPARATOR;
        return $this;
    }

    public function register(): void
    {
        if ($this->registered) {
            return;
        }
        spl_autoload_register([$this, 'load'], true, true);
        $this->registered = true;
    }

    public function load(string $class): bool
    {
        foreach ($this->prefixes as $prefix => $baseDir) {
            if (!str_starts_with($class, $prefix)) {
                continue;
            }
            $relative = substr($class, strlen($prefix));
            $file = $baseDir . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';
            if (is_file($file)) {
                require_once $file;
                return true;
            }
        }
        return false;
    }

    /**
     * Initialisation standard de l'application.
     */
    public static function bootstrap(): self
    {
        // Racine projet : .../app/Core/Autoloader.php => deux niveaux au-dessus.
        $root = dirname(__DIR__, 2);
        $loader = new self();
        $loader->add('App\\', $root . '/app');
        $loader->add('Modules\\', $root . '/modules');
        $loader->add('Tests\\', $root . '/tests');
        $loader->add('Database\\', $root . '/database');
        $loader->register();

        // Bibliothèques tierces : autoload Composer s'il existe, sinon carte
        // PSR-4 de secours pour les paquets vendorisés hors-ligne.
        $composerAutoload = $root . '/vendor/autoload.php';
        if (is_file($composerAutoload)) {
            require_once $composerAutoload;
        } else {
            $fallback = $root . '/config/vendor_map.php';
            if (is_file($fallback)) {
                foreach (require $fallback as $prefix => $dir) {
                    $loader->add($prefix, $root . '/' . ltrim($dir, '/'));
                }
            }
        }

        return $loader;
    }
}
