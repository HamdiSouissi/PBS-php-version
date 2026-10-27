<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Core\Security\Totp;
use Tests\Core\TestCase;

final class TotpTest extends TestCase
{
    public function testGeneratedCodeIsSixDigits(): void
    {
        $totp = new Totp(['issuer' => 'PBS', 'digits' => 6, 'period' => 30, 'window' => 1]);
        $secret = $totp->generateSecret();
        $code = $totp->currentCode($secret);
        self::assertSame(6, strlen($code));
        self::assertTrue((bool) preg_match('/^[0-9]{6}$/', $code));
    }

    public function testVerifyAcceptsCurrentCode(): void
    {
        $totp = new Totp(['issuer' => 'PBS', 'digits' => 6, 'period' => 30, 'window' => 1]);
        $secret = $totp->generateSecret();
        self::assertTrue($totp->verify($secret, $totp->currentCode($secret)));
    }

    public function testVerifyRejectsWrongCode(): void
    {
        $totp = new Totp(['issuer' => 'PBS', 'digits' => 6, 'period' => 30, 'window' => 0]);
        $secret = $totp->generateSecret();
        self::assertFalse($totp->verify($secret, '000000') && $totp->currentCode($secret) === '000000');
    }
}
