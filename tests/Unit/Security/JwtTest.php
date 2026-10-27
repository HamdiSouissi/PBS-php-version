<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Core\Security\Jwt;
use Tests\Core\TestCase;

final class JwtTest extends TestCase
{
    private function jwt(): Jwt
    {
        return new Jwt([
            'secret' => str_repeat('a', 48),
            'algo' => 'HS256',
            'ttl' => 3600,
            'refresh_ttl' => 7200,
            'issuer' => 'http://localhost',
        ]);
    }

    public function testIssueAndVerifyRoundTrip(): void
    {
        $jwt = $this->jwt();
        $token = $jwt->issue(['sub' => 7, 'typ' => 'access']);
        $claims = $jwt->verify($token);
        self::assertSame(7, $claims['sub']);
        self::assertSame('access', $claims['typ']);
        self::assertSame('http://localhost', $claims['iss']);
    }

    public function testTamperedTokenIsRejected(): void
    {
        $jwt = $this->jwt();
        $token = $jwt->issue(['sub' => 1]);
        $this->expectException(\InvalidArgumentException::class);
        $jwt->verify($token . 'x');
    }

    public function testExpiredTokenIsRejected(): void
    {
        $jwt = $this->jwt();
        $token = $jwt->issue(['sub' => 1], -10);
        $this->expectException(\InvalidArgumentException::class);
        $jwt->verify($token);
    }

    public function testShortSecretIsRejected(): void
    {
        $this->expectException(\RuntimeException::class);
        new Jwt(['secret' => 'court', 'algo' => 'HS256', 'ttl' => 60, 'refresh_ttl' => 120, 'issuer' => 'x']);
    }
}
