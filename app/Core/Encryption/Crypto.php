<?php

declare(strict_types=1);

namespace App\Core\Encryption;

/**
 * Chiffrement symétrique des secrets (clés API, tokens) via libsodium.
 * La clé provient de APP_KEY (base64, 32 octets une fois décodée).
 */
final class Crypto
{
    private const PREFIX = 'enc::';

    public function __construct(private readonly string $appKey)
    {
    }

    public function encrypt(string $plaintext): string
    {
        $key = $this->key();
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = sodium_crypto_secretbox($plaintext, $nonce, $key);
        return self::PREFIX . base64_encode($nonce . $ciphertext);
    }

    public function decrypt(string $payload): string
    {
        if (!str_starts_with($payload, self::PREFIX)) {
            // Valeur non chiffrée (reprise sur données historiques).
            return $payload;
        }
        $raw = base64_decode(substr($payload, strlen(self::PREFIX)), true);
        if ($raw === false || strlen($raw) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new \RuntimeException('Secret chiffré invalide.');
        }
        $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plaintext = sodium_crypto_secretbox_open($ciphertext, $nonce, $this->key());
        if ($plaintext === false) {
            throw new \RuntimeException('Déchiffrement impossible (clé invalide ?).');
        }
        return $plaintext;
    }

    public function isEncrypted(string $value): bool
    {
        return str_starts_with($value, self::PREFIX);
    }

    public static function generateKey(): string
    {
        return base64_encode(random_bytes(32));
    }

    private function key(): string
    {
        if ($this->appKey === '') {
            throw new \RuntimeException('APP_KEY absent : générez une clé avec app:key-generate.');
        }
        $decoded = base64_decode($this->appKey, true);
        if ($decoded === false || strlen($decoded) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new \RuntimeException('APP_KEY invalide (32 octets, base64 attendu).');
        }
        return $decoded;
    }
}
