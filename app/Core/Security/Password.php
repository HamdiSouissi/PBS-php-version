<?php

declare(strict_types=1);

namespace App\Core\Security;

/**
 * Hachage et vérification des mots de passe (Argon2id par défaut).
 */
final class Password
{
    /** @param array<string,mixed> $config */
    public function __construct(private readonly array $config)
    {
    }

    public function hash(string $plain): string
    {
        return password_hash($plain, (string) $this->config['algo'], $this->config['options']);
    }

    public function verify(string $plain, string $hashed): bool
    {
        return password_verify($plain, $hashed);
    }

    public function needsRehash(string $hashed): bool
    {
        return password_needs_rehash($hashed, (string) $this->config['algo'], $this->config['options']);
    }

    public function validateStrength(string $password): array
    {
        $errors = [];
        $min = (int) $this->config['min_length'];
        if (mb_strlen($password) < $min) {
            $errors[] = "Le mot de passe doit contenir au moins {$min} caractères.";
        }
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Le mot de passe doit contenir au moins une majuscule.';
        }
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Le mot de passe doit contenir au moins une minuscule.';
        }
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Le mot de passe doit contenir au moins un chiffre.';
        }
        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = 'Le mot de passe doit contenir au moins un caractère spécial.';
        }
        return $errors;
    }
}
