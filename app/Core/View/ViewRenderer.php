<?php

declare(strict_types=1);

namespace App\Core\View;

use App\Core\Container;
use App\Core\Exception\HttpException;

/**
 * Rendu des vues PHP natives (pas de moteur de templates externe).
 *
 * Convention de nommage :
 *  - "Module::dossier/vue" => modules/Module/Views/dossier/vue.php
 *  - "dossier/vue"         => app/Views/dossier/vue.php
 */
final class ViewRenderer
{
    public function __construct(
        private readonly string $modulesPath,
        private readonly string $appViewsPath,
        private readonly Container $container
    ) {
    }

    /**
     * @param array<string,mixed> $data
     */
    public function renderTemplate(string $template, array $data = [], ?string $layout = 'layouts/app'): string
    {
        $content = $this->renderView($template, $data);
        if ($layout === null) {
            return $content;
        }
        return $this->renderView($layout, [...$data, 'content' => $content]);
    }

    /**
     * @param array<string,mixed> $data
     */
    public function renderView(string $template, array $data): string
    {
        $file = $this->resolve($template);
        if (!is_file($file)) {
            throw new HttpException(500, "Vue introuvable : {$template} ({$file})");
        }
        $renderer = function (string $__file, array $__data): string {
            extract($__data, EXTR_SKIP);
            ob_start();
            require $__file;
            return (string) ob_get_clean();
        };
        // Helpers de vues exposés proprement.
        $data['viewRenderer'] = $this;
        $data['container'] = $this->container;
        return $renderer($file, $data);
    }

    /**
     * Lien de navigation latérale, affiché uniquement si la permission
     * (optionnelle) est accordée à l'utilisateur courant.
     */
    public function navItem(string $url, string $label, string $icon, string $permission = ''): string
    {
        if ($permission !== '' && !\App\Core\Application::container()->get(\App\Core\Auth\AuthManager::class)->hasPermission($permission)) {
            return '';
        }
        $current = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
        $isActive = $current === $url || str_starts_with($current, rtrim($url, '/') . '/');
        $active = $isActive ? 'active' : '';
        $ariaCurrent = $isActive ? ' aria-current="page"' : '';
        return sprintf(
            '<a class="nav-link %s" href="%s"%s><i class="bi bi-%s"></i><span>%s</span></a>',
            $active,
            htmlspecialchars($url, ENT_QUOTES),
            $ariaCurrent,
            htmlspecialchars($icon, ENT_QUOTES),
            htmlspecialchars($label, ENT_QUOTES)
        );
    }

    public function navSection(string $label): string
    {
        return '<div class="nav-section">'
            . htmlspecialchars($label, ENT_QUOTES) . '</div>';
    }

    private function resolve(string $template): string
    {
        if (str_contains($template, '::')) {
            [$module, $relative] = explode('::', $template, 2);
            return $this->modulesPath . DIRECTORY_SEPARATOR . $module
                . DIRECTORY_SEPARATOR . 'Views' . DIRECTORY_SEPARATOR
                . str_replace('/', DIRECTORY_SEPARATOR, $relative) . '.php';
        }
        return $this->appViewsPath . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $template) . '.php';
    }
}
