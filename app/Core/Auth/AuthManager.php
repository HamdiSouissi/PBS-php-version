<?php

declare(strict_types=1);

namespace App\Core\Auth;

use App\Core\Database\Connection;
use App\Core\Session\SessionManager;
use Modules\Authentication\Models\User;

/**
 * Gestion de l'authentification courante (web + API) et des permissions RBAC.
 */
final class AuthManager
{
    private ?User $user = null;

    private bool $loaded = false;

    public function __construct(
        private readonly SessionManager $session,
        private readonly Connection $db
    ) {
    }

    public function login(User $user, bool $remember = false): void
    {
        $this->session->regenerate();
        $this->session->set('user_id', $user->id);
        $this->session->set('remember', $remember);
        $this->user = $user;
        $this->loaded = true;
    }

    public function logout(): void
    {
        $this->session->remove('user_id');
        $this->session->remove('remember');
        $this->user = null;
        $this->loaded = true;
    }

    /**
     * Connexion technique sans session (API JWT).
     */
    public function setUser(User $user): void
    {
        $this->user = $user;
        $this->loaded = true;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function guest(): bool
    {
        return !$this->check();
    }

    public function user(): ?User
    {
        if ($this->loaded) {
            return $this->user;
        }
        $this->loaded = true;
        $id = $this->session->get('user_id');
        if ($id === null) {
            return null;
        }
        $user = \App\Core\Application::container()
            ->get(\Modules\Authentication\Repositories\UserRepository::class)
            ->findWithRights((int) $id);
        if ($user === null) {
            $this->session->remove('user_id');
            return null;
        }
        $this->user = $user;
        return $this->user;
    }

    public function id(): ?int
    {
        return $this->user()?->id;
    }

    public function hasPermission(string $permission): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }
        if (in_array('*', $user->permissions, true)) {
            return true;
        }
        return in_array($permission, $user->permissions, true);
    }

    /** @param list<string> $permissions */
    public function hasAnyPermission(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }
        return false;
    }

    public function hasRole(string $role): bool
    {
        return $this->user() !== null && in_array($role, $this->user()->roles, true);
    }

    /**
     * Garde-fou serveur : lève une exception 403 si la permission manque.
     */
    public function requirePermission(string $permission): void
    {
        if (!$this->hasPermission($permission)) {
            throw new \App\Core\Exception\AuthorizationException(
                "Permission requise : {$permission}"
            );
        }
    }

    /** @param list<string> $permissions */
    public function requireAnyPermission(array $permissions): void
    {
        if (!$this->hasAnyPermission($permissions)) {
            throw new \App\Core\Exception\AuthorizationException(
                'Au moins une permission est requise : ' . implode(', ', $permissions)
            );
        }
    }
}
