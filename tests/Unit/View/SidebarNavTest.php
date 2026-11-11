<?php

declare(strict_types=1);

namespace Tests\Unit\View;

use App\Core\Container;
use App\Core\View\ViewRenderer;
use Tests\Core\TestCase;

final class SidebarNavTest extends TestCase
{
    public function testLabelsRemainAccessibleAndEscaped(): void
    {
        $renderer = new ViewRenderer('', '', new Container());
        $previous = $_SERVER['REQUEST_URI'] ?? null;
        $_SERVER['REQUEST_URI'] = '/dashboard';
        try {
            $html = $renderer->navItem('/dashboard', 'Titre " & <test>', 'speedometer2');
            self::assertStringContainsString('aria-current="page"', $html);
            self::assertStringContainsString('aria-label="Titre &quot; &amp; &lt;test&gt;"', $html);
            self::assertStringContainsString('data-nav-label="Titre &quot; &amp; &lt;test&gt;"', $html);
            self::assertStringContainsString('class="nav-label"', $html);
        } finally {
            if ($previous === null) {
                unset($_SERVER['REQUEST_URI']);
            } else {
                $_SERVER['REQUEST_URI'] = $previous;
            }
        }
    }
}
