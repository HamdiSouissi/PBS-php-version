<?php

declare(strict_types=1);

namespace App\Core\View;

use App\Core\Http\Response;

/**
 * Réponse HTML rendue par le moteur de vues.
 */
final class View extends Response
{
    /** @param array<string,mixed> $data */
    public function __construct(
        private readonly string $template,
        private readonly array $data = [],
        private readonly ?string $layout = 'layouts/app'
    ) {
        parent::__construct('', 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public function send(): void
    {
        $renderer = \App\Core\Application::container()->get(ViewRenderer::class);
        $this->content = $renderer->renderTemplate($this->template, $this->data, $this->layout);
        parent::send();
    }
}
