<?php

declare(strict_types=1);

namespace App\Core\Security;

use App\Core\Session\SessionManager;

/**
 * Protection CSRF par jetons synchronisateur liés à la session.
 */
final class Csrf
{
    private const KEY = '_csrf_token';

    public function __construct(private readonly SessionManager $session)
    {
    }

    public function token(): string
    {
        $token = $this->session->get(self::KEY);
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            $this->session->set(self::KEY, $token);
        }
        return $token;
    }

    public function validate(?string $token): bool
    {
        $known = $this->session->get(self::KEY);
        return is_string($known) && is_string($token) && hash_equals($known, $token);
    }
}
