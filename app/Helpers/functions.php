<?php

declare(strict_types=1);

/**
 * Fonctions globales de l'application (chargées via l'autoloader PSR-4 "files").
 */

if (!function_exists('env')) {
    /**
     * Lit une variable d'environnement avec valeur par défaut.
     * Transforme les mots-clés booléens/null en types PHP.
     */
    function env(string $key, ?string $default = null): mixed
    {
        $value = $_ENV[$key] ?? getenv($key);
        if ($value === false || $value === null) {
            return $default;
        }
        return match (strtolower((string) $value)) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'null', '(null)' => null,
            'empty', '(empty)' => '',
            default => $value,
        };
    }
}

if (!function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        $root = dirname(__DIR__, 2);
        return $path === '' ? $root : $root . DIRECTORY_SEPARATOR . ltrim($path, '/\\');
    }
}

if (!function_exists('storage_path')) {
    function storage_path(string $path = ''): string
    {
        return base_path('storage' . ($path !== '' ? DIRECTORY_SEPARATOR . ltrim($path, '/\\') : ''));
    }
}

if (!function_exists('public_path')) {
    function public_path(string $path = ''): string
    {
        return base_path('public' . ($path !== '' ? DIRECTORY_SEPARATOR . ltrim($path, '/\\') : ''));
    }
}

if (!function_exists('asset')) {
    /**
     * URL d'un asset statique avec invalidation de cache par date de
     * modification : toute mise à jour du fichier change la requête et
     * force le navigateur à récupérer la nouvelle version.
     */
    function asset(string $path): string
    {
        $url = '/' . ltrim($path, '/');
        $absolute = public_path($path);
        if (is_file($absolute)) {
            $url .= '?v=' . dechex(filemtime($absolute));
        }
        return $url;
    }
}

if (!function_exists('config_path')) {
    function config_path(string $path = ''): string
    {
        return base_path('config' . ($path !== '' ? DIRECTORY_SEPARATOR . ltrim($path, '/\\') : ''));
    }
}

if (!function_exists('app_path')) {
    function app_path(string $path = ''): string
    {
        return base_path('app' . ($path !== '' ? DIRECTORY_SEPARATOR . ltrim($path, '/\\') : ''));
    }
}

if (!function_exists('modules_path')) {
    function modules_path(string $path = ''): string
    {
        return base_path('modules' . ($path !== '' ? DIRECTORY_SEPARATOR . ltrim($path, '/\\') : ''));
    }
}

if (!function_exists('config')) {
    /**
     * Accès paresseux à la configuration via notation pointée : config('app.name').
     */
    function config(string $key, mixed $default = null): mixed
    {
        return \App\Core\Support\Config::get($key, $default);
    }
}

if (!function_exists('container')) {
    function container(): \App\Core\Container
    {
        return \App\Core\Application::container();
    }
}

if (!function_exists('db')) {
    function db(): \App\Core\Database\Connection
    {
        return container()->get(\App\Core\Database\Connection::class);
    }
}

if (!function_exists('view')) {
    /**
     * Rend une vue PHP avec layout.
     * @param array<string,mixed> $data
     */
    function view(string $template, array $data = [], ?string $layout = 'layouts/app'): \App\Core\View\View
    {
        return new \App\Core\View\View($template, $data, $layout);
    }
}

if (!function_exists('json_response')) {
    /** @param array<string,mixed>|list<mixed> $data */
    function json_response(array $data, int $status = 200, array $headers = []): \App\Core\Http\JsonResponse
    {
        return new \App\Core\Http\JsonResponse($data, $status, $headers);
    }
}

if (!function_exists('redirect')) {
    function redirect(string $url, int $status = 302): \App\Core\Http\RedirectResponse
    {
        return new \App\Core\Http\RedirectResponse($url, $status);
    }
}

if (!function_exists('e')) {
    /** Échappement HTML (protection XSS). */
    function e(mixed $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return container()->get(\App\Core\Security\Csrf::class)->token();
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
    }
}

if (!function_exists('auth')) {
    function auth(): \App\Core\Auth\AuthManager
    {
        return container()->get(\App\Core\Auth\AuthManager::class);
    }
}

if (!function_exists('current_user')) {
    function current_user(): ?\Modules\Authentication\Models\User
    {
        return auth()->user();
    }
}

if (!function_exists('money_format')) {
    /** Formatage monétaire français (centimes gérés en amont via bcmath). */
    function money_format(float|int|string $amount, string $currency = 'EUR'): string
    {
        $formatter = new NumberFormatter('fr_FR', NumberFormatter::CURRENCY);
        return $formatter->formatCurrency((float) $amount, $currency);
    }
}

if (!function_exists('num_format')) {
    function num_format(float|int|string $value, int $decimals = 2): string
    {
        return number_format((float) $value, $decimals, ',', ' ');
    }
}

if (!function_exists('percent_format')) {
    function percent_format(float|int|string $rate, int $decimals = 2): string
    {
        return num_format($rate, $decimals) . ' %';
    }
}

if (!function_exists('old')) {
    function old(string $key, mixed $default = ''): mixed
    {
        $flash = container()->get(\App\Core\Session\FlashBag::class);
        return $flash->old($key, $default);
    }
}

if (!function_exists('flash')) {
    function flash(string $type, string $message): void
    {
        container()->get(\App\Core\Session\FlashBag::class)->set($type, $message);
    }
}

if (!function_exists('logger')) {
    function logger(): \App\Core\Logger\Logger
    {
        return container()->get(\App\Core\Logger\Logger::class);
    }
}

if (!function_exists('audit')) {
    function audit(string $action, string $entityType, int|string|null $entityId = null, array $changes = []): void
    {
        container()->get(\App\Core\Audit\AuditLogger::class)->log($action, $entityType, $entityId, $changes);
    }
}

if (!function_exists('translit_slug')) {
    function translit_slug(string $text): string
    {
        $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
        $text = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $text) ?? '');
        return trim($text, '-');
    }
}
