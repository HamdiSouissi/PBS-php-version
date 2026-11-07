<?php

declare(strict_types=1);

namespace Modules\Authentication\Services;

use App\Core\Database\Connection;
use Modules\Authentication\Models\User;
use Modules\Authentication\Repositories\UserRepository;

/** Défi opaque, limité dans le temps et consommé atomiquement après TOTP valide. */
final class ApiTwoFactorChallengeService
{
    public function __construct(
        private readonly Connection $db,
        private readonly UserRepository $users,
        private readonly AuthService $auth
    ) {
    }

    public function issue(User $user): string
    {
        $token = bin2hex(random_bytes(32));
        $this->db->execute('DELETE FROM api_two_factor_challenges WHERE expires_at <= :now', ['now' => date('Y-m-d H:i:s')]);
        $this->db->execute('INSERT INTO api_two_factor_challenges (user_id, token_hash, password_fingerprint, expires_at) VALUES (:user, :hash, :fingerprint, :expires)', [
            'user' => $user->id, 'hash' => hash('sha256', $token), 'fingerprint' => hash('sha256', $user->passwordHash), 'expires' => date('Y-m-d H:i:s', time() + 300),
        ]);
        return $token;
    }

    public function verify(string $token, string $code): ?User
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $token)) {
            return null;
        }
        $hash = hash('sha256', $token);
        $now = date('Y-m-d H:i:s');
        $challenge = $this->db->fetchOne('SELECT user_id, password_fingerprint FROM api_two_factor_challenges WHERE token_hash = :hash AND used_at IS NULL AND expires_at > :now', ['hash' => $hash, 'now' => $now]);
        if ($challenge === null) {
            return null;
        }
        $user = $this->users->findWithRights((int) $challenge['user_id']);
        if ($user === null || !$user->isActive || !$user->totpEnrolled || !hash_equals((string) $challenge['password_fingerprint'], hash('sha256', $user->passwordHash)) || !$this->auth->validateTwoFactor($user, $code)) {
            return null;
        }
        $consumed = $this->db->execute('UPDATE api_two_factor_challenges SET used_at = :used WHERE token_hash = :hash AND used_at IS NULL AND expires_at > :now', ['used' => $now, 'hash' => $hash, 'now' => $now]);
        return $consumed === 1 ? $user : null;
    }
}
