<?php

declare(strict_types=1);

namespace App\Core\Session;

use App\Core\Database\Connection;

/**
 * Gestion des sessions PHP natives (fichiers par défaut, paramètres durcis).
 */
final class SessionManager
{
    private bool $started = false;

    /** @param array<string,mixed> $config */
    public function __construct(
        private readonly array $config,
        private readonly Connection $connection
    ) {
    }

    public function start(): void
    {
        if ($this->started || session_status() === PHP_SESSION_ACTIVE) {
            $this->started = true;
            return;
        }
        if (!is_dir($this->config['save_path']) && !mkdir($this->config['save_path'], 0775, true)) {
            throw new \RuntimeException('Dossier de session inaccessible.');
        }
        session_save_path($this->config['save_path']);
        session_name($this->config['name']);
        session_set_cookie_params([
            'lifetime' => $this->config['lifetime'],
            'path' => '/',
            'domain' => '',
            'secure' => $this->config['secure'],
            'httponly' => $this->config['httponly'],
            'samesite' => $this->config['samesite'],
        ]);
        session_start();
        $this->started = true;

        // Protection contre la fixation de session.
        if (!isset($_SESSION['_init'])) {
            session_regenerate_id(true);
            $_SESSION['_init'] = 1;
            $_SESSION['_created'] = time();
        }
        $this->expireIfNeeded();
    }

    private function expireIfNeeded(): void
    {
        $lifetime = (int) $this->config['lifetime'];
        if (isset($_SESSION['_last_activity']) && time() - (int) $_SESSION['_last_activity'] > $lifetime) {
            $this->invalidate();
            $this->start();
        }
        $_SESSION['_last_activity'] = time();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public function invalidate(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_unset();
            session_destroy();
        }
        $this->started = false;
    }

    public function regenerate(): void
    {
        session_regenerate_id(true);
    }

    public function getId(): string
    {
        return session_id() ?: '';
    }

    /**
     * Persiste une trace de session en base pour le journal des connexions.
     */
    public function persistConnection(int $userId, string $ip, string $userAgent, bool $remember): void
    {
        $this->connection->execute(
            'INSERT INTO auth_login_logs (user_id, session_id, ip_address, user_agent, remember_me, created_at)
             VALUES (:user_id, :sid, :ip, :ua, :remember, ' . $this->connection->nowExpression() . ')',
            [
                'user_id' => $userId,
                'sid' => $this->getId(),
                'ip' => substr($ip, 0, 45),
                'ua' => substr($userAgent, 0, 255),
                'remember' => $remember,
            ]
        );
    }
}
