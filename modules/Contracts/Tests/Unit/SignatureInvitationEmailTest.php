<?php

declare(strict_types=1);

namespace Modules\Contracts\Tests\Unit;

use Modules\Contracts\Services\SignatureInvitationEmail;
use Tests\Core\TestCase;

final class SignatureInvitationEmailTest extends TestCase
{
    public function testEmailEscapesDynamicContentAndIncludesLink(): void
    {
        $url = 'https://example.org/signature/sabc?x=1&y=2';
        $html = SignatureInvitationEmail::html($url, '<Client>', 'CTR & 123');
        self::assertTrue(str_contains($html, '&lt;Client&gt;'));
        self::assertTrue(str_contains($html, 'CTR &amp; 123'));
        self::assertTrue(str_contains($html, 'href="https://example.org/signature/sabc?x=1&amp;y=2"'));
        self::assertFalse(str_contains($html, '<Client>'));
        $text = SignatureInvitationEmail::text($url, 'Client', 'CTR-123');
        self::assertTrue(str_contains($text, $url));
        self::assertTrue(str_contains($text, '7 jours'));
    }
}
