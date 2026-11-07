<?php

declare(strict_types=1);

namespace Tests\Unit\Http;

use App\Core\Database\Connection;
use App\Core\Exception\HttpException;
use App\Core\Http\Middleware\CsrfMiddleware;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Security\Csrf;
use App\Core\Session\SessionManager;
use Tests\Core\TestCase;

final class RequestSecurityTest extends TestCase
{
    public function testJsonAcceptDoesNotExemptWebPostFromCsrf(): void
    {
        $_SESSION = [];
        $request = new Request(['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/login', 'HTTP_ACCEPT' => 'application/json'], [], [], []);
        self::assertTrue($request->isApi());
        self::assertFalse($request->isApiRoute());
        $middleware = new CsrfMiddleware(new Csrf(new SessionManager([], new Connection([]))));
        $this->expectException(HttpException::class);
        $middleware->handle($request, static fn () => new Response());
    }

    public function testUntrustedHeadersAndProxyChain(): void
    {
        putenv('TRUSTED_PROXIES');
        $server = ['REMOTE_ADDR' => '192.0.2.1', 'HTTP_X_FORWARDED_FOR' => '203.0.113.1', 'HTTP_CF_CONNECTING_IP' => '203.0.113.2', 'HTTP_X_FORWARDED_PROTO' => 'https'];
        $request = new Request($server, [], [], []);
        self::assertSame('192.0.2.1', $request->ip());
        self::assertFalse($request->isSecure());
        putenv('TRUSTED_PROXIES=192.0.2.1,192.0.2.2');
        try {
            $server['HTTP_X_FORWARDED_FOR'] = '198.51.100.99, 203.0.113.1, 192.0.2.2';
            $request = new Request($server, [], [], []);
            self::assertSame('203.0.113.1', $request->ip());
            self::assertTrue($request->isSecure());
            $server['HTTP_X_FORWARDED_FOR'] = 'invalid';
            self::assertSame('192.0.2.1', (new Request($server, [], [], []))->ip());
            self::assertTrue((new Request(['REQUEST_URI' => '/api/v1/auth/login'], [], [], []))->isApiRoute());
        } finally {
            putenv('TRUSTED_PROXIES');
        }
    }
}
