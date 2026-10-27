<?php

declare(strict_types=1);

namespace Modules\Authentication\Services;

use App\Core\Auth\AuthManager;
use App\Core\Database\Connection;
use App\Core\Exception\AuthenticationException;
use App\Core\Security\Csrf;
use App\Core\Security\Password;
use App\Core\Security\RateLimiter;
use App\Core\Security\Totp;
use App\Core\Session\SessionManager;
use Modules\Authentication\Models\User;
use Modules\Authentication\Repositories\UserRepository;

/**
 * Logique de connexion / déconnexion, avec limitation de débit,
 * verrouillage de compte, Remember Me et double authentification TOTP.
 */
final class AuthService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly Password $password,
        private readonly RateLimiter $rateLimiter,
        private readonly AuthManager $auth,
        private readonly SessionManager $session,
        private readonly Totp $totp,
        private readonly Connection $db
    ) {
    }

    /**
     * Première étape d'authentification. Retourne l'utilisateur si le mot de
     * passe est valide ; lève une exception sinon. Le TOTP est traité ensuite.
     */
    public function attempt(string $email, string $plainPassword, string $ip): User
    {
        if (!$this->rateLimiter->attempt('login:' . $ip, (int) config('security.rate_limit.login_max'), 300)) {
            throw new AuthenticationException('Trop de tentatives. Réessayez dans 5 minutes.');
        }

        $user = $this->users->findByEmail($email);
        if ($user === null || !$user->isActive) {
            throw new AuthenticationException('Identifiants invalides.');
        }
        if ($user->isLocked()) {
            throw new AuthenticationException('Compte verrouillé après tentatives répétées. Réessayez plus tard.');
        }
        if (!$this->password->verify($plainPassword, $user->passwordHash)) {
            $this->users->recordFailedAttempt($user->id, (int) config('security.rate_limit.login_max'));
            throw new AuthenticationException('Identifiants invalides.');
        }

        // Re-hachage si les paramètres de sécurité ont évolué.
        if ($this->password->needsRehash($user->passwordHash)) {
            $this->db->execute(
                'UPDATE users SET password_hash = :h WHERE id = :id',
                ['h' => $this->password->hash($plainPassword), 'id' => $user->id]
            );
        }
        return $user;
    }

    /**
     * Connexion complète (après vérification mot de passe puis TOTP éventuel).
     */
    public function login(User $user, bool $remember, string $ip, string $userAgent): void
    {
        if ($user->totpEnrolled && $this->session->get('2fa_pending') !== $user->id) {
            throw new AuthenticationException('La double authentification doit être validée.');
        }
        $this->auth->login($user, $remember);
        $this->users->resetFailedAttempts($user->id);
        $this->session->remove('2fa_pending');
        $this->session->persistConnection($user->id, $ip, $userAgent, $remember);

        if ($remember) {
            $this->issueRememberToken($user);
        }
    }

    public function prepareTwoFactor(User $user): void
    {
        $this->session->set('2fa_pending', $user->id);
    }

    public function validateTwoFactor(User $user, string $code): bool
    {
        if ($user->totpSecret === null) {
            return false;
        }
        return $this->totp->verify($user->totpSecret, $code);
    }

    public function loginFromRememberCookie(): ?User
    {
        $token = $_COOKIE['pbs_remember'] ?? null;
        if (!is_string($token) || $token === '') {
            return null;
        }
        $user = $this->users->findByRememberToken($token);
        if ($user === null) {
            return null;
        }
        // Rotation du jeton à chaque utilisation.
        $this->db->execute('DELETE FROM remember_tokens WHERE token_hash = :h', ['h' => hash('sha256', $token)]);
        $this->auth->login($user, true);
        $this->issueRememberToken($user);
        return $user;
    }

    public function logout(): void
    {
        $userId = $this->auth->id();
        if ($userId !== null) {
            // Sous-requête corrélée portable (MySQL et SQLite n'acceptent pas
            // tous deux ORDER BY/LIMIT directement dans un UPDATE).
            $this->db->execute(
                'UPDATE auth_login_logs SET logout_at = ' . $this->db->nowExpression() . '
                 WHERE id = (
                     SELECT latest_id FROM (
                         SELECT id AS latest_id FROM auth_login_logs
                         WHERE user_id = :u AND logout_at IS NULL
                         ORDER BY id DESC LIMIT 1
                     ) AS latest
                 )',
                ['u' => $userId]
            );
            $this->db->execute('DELETE FROM remember_tokens WHERE user_id = :u', ['u' => $userId]);
        }
        setcookie('pbs_remember', '', time() - 3600, '/', '', false, true);
        $this->auth->logout();
    }

    private function issueRememberToken(User $user): void
    {
        $token = bin2hex(random_bytes(32));
        $expires = new \DateTimeImmutable('+' . config('session.remember_lifetime') . ' seconds');
        $this->users->storeRememberToken($user->id, hash('sha256', $token), $expires);
        setcookie(
            'pbs_remember',
            $token,
            [
                'expires' => $expires->getTimestamp(),
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Lax',
                'secure' => (bool) config('session.secure'),
            ]
        );
    }
}
