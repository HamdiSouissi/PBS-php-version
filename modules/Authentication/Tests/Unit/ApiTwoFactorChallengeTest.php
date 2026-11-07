<?php

declare(strict_types=1);

namespace Modules\Authentication\Tests\Unit;

use App\Core\Application;
use App\Core\Auth\AuthManager;
use App\Core\Security\Totp;
use App\Core\Session\SessionManager;
use Modules\Authentication\Repositories\UserRepository;
use Modules\Authentication\Services\ApiTwoFactorChallengeService;
use Tests\Core\DatabaseTestCase;

final class ApiTwoFactorChallengeTest extends DatabaseTestCase
{
    public function testChallengeRequiresCodeAndIsSingleUse(): void
    {
        $secret = 'JBSWY3DPEHPK3PXP';
        $this->db->execute('INSERT INTO users (email, password_hash, first_name, last_name, is_active, totp_enrolled, totp_secret) VALUES (:email, :password, :first, :last, 1, 1, :secret)', [
            'email' => 'security@example.test', 'password' => 'hash-a', 'first' => 'Test', 'last' => 'Security', 'secret' => $secret,
        ]);
        $container = Application::container();
        $user = $container->get(UserRepository::class)->findByEmail('security@example.test');
        $service = $container->get(ApiTwoFactorChallengeService::class);
        $code = $container->get(Totp::class)->currentCode($secret);
        self::assertNull($service->verify('', $code));
        $token = $service->issue($user);
        self::assertNull($service->verify($token, 'invalid'));
        self::assertNotNull($service->verify($token, $code));
        self::assertNull($service->verify($token, $code));
        $token = $service->issue($user);
        $this->db->execute('UPDATE users SET password_hash = :hash WHERE id = :id', ['hash' => 'hash-b', 'id' => $user->id]);
        self::assertNull($service->verify($token, $code));
        $token = $service->issue($user);
        $this->db->execute('UPDATE users SET is_active = 0 WHERE id = :id', ['id' => $user->id]);
        self::assertNull($service->verify($token, $code));
        $container->get(SessionManager::class)->set('user_id', $user->id);
        self::assertNull($container->get(AuthManager::class)->user());
    }
    public function testApiLoginRequiresPasswordBeforeTotpAndInvalidatesRefreshOnPasswordChange(): void
    {
        $secret = 'JBSWY3DPEHPK3PXP';
        $this->db->execute('INSERT INTO users (email, password_hash, first_name, last_name, is_active, totp_enrolled, totp_secret) VALUES (:email, :password, :first, :last, 1, 1, :secret)', [
            'email' => 'api-security@example.test', 'password' => password_hash('CorrectPassword123!', PASSWORD_BCRYPT), 'first' => 'Test', 'last' => 'API', 'secret' => $secret,
        ]);
        $c = Application::container();
        $controller = new \Modules\Authentication\Controllers\Api\AuthApiController(
            $c->get(\Modules\Authentication\Services\AuthService::class),
            new \App\Core\Security\Jwt(['secret' => str_repeat('z', 64), 'algo' => 'HS256', 'ttl' => 3600, 'issuer' => 'test']),
            $c->get(AuthManager::class), $c->get(\App\Core\Security\RateLimiter::class),
            $c->get(ApiTwoFactorChallengeService::class)
        );
        $ip = '198.18.' . random_int(1, 254) . '.' . random_int(1, 254);
        $request = static fn (array $data): \App\Core\Http\Request => new \App\Core\Http\Request(
            ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/api/v1/auth/2fa', 'REMOTE_ADDR' => $ip], [], $data, []
        );
        $code = $c->get(Totp::class)->currentCode($secret);
        self::assertSame(401, $controller->verifyTwoFactor($request(['challenge' => str_repeat('a', 64), 'code' => $code]))->getStatus());
        $login = $controller->login($request(['email' => 'api-security@example.test', 'password' => 'CorrectPassword123!']));
        self::assertSame(200, $login->getStatus());
        $data = json_decode($login->getContent(), true);
        self::assertTrue($data['two_factor_required']);
        $verified = $controller->verifyTwoFactor($request(['challenge' => $data['challenge'], 'code' => $code]));
        self::assertSame(200, $verified->getStatus());
        $tokens = json_decode($verified->getContent(), true);
        self::assertSame(401, $controller->verifyTwoFactor($request(['challenge' => $data['challenge'], 'code' => $code]))->getStatus());
        $this->db->execute('UPDATE users SET password_hash = :hash WHERE email = :email', ['hash' => 'new-hash', 'email' => 'api-security@example.test']);
        self::assertSame(401, $controller->refresh($request(['refresh_token' => $tokens['refresh_token']]))->getStatus());
    }

}
