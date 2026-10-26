<?php

declare(strict_types=1);

namespace App\Core\Security;

/**
 * TOTP RFC 6238 (double authentification) implémenté maison.
 */
final class Totp
{
    /** @param array<string,mixed> $config */
    public function __construct(private readonly array $config)
    {
    }

    public function generateSecret(int $length = 32): string
    {
        // Alphabet Base32 RFC 4648 compatible Google Authenticator.
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= $alphabet[random_int(0, 31)];
        }
        return $secret;
    }

    public function codeAt(string $secret, int $timestamp): string
    {
        $period = (int) $this->config['period'];
        $counter = (int) floor($timestamp / $period);
        $binCounter = pack('N*', 0) . pack('N*', $counter);
        $key = $this->decodeBase32($secret);
        $hash = hash_hmac('sha1', $binCounter, $key, true);
        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        $value = (
            ((ord($hash[$offset]) & 0x7F) << 24)
            | (ord($hash[$offset + 1]) << 16)
            | (ord($hash[$offset + 2]) << 8)
            | ord($hash[$offset + 3])
        ) % (10 ** (int) $this->config['digits']);
        return str_pad((string) $value, (int) $this->config['digits'], '0', STR_PAD_LEFT);
    }

    public function currentCode(string $secret): string
    {
        return $this->codeAt($secret, time());
    }

    public function verify(string $secret, string $code, ?int $timestamp = null): bool
    {
        $timestamp ??= time();
        $period = (int) $this->config['period'];
        $window = (int) $this->config['window'];
        $code = preg_replace('/\s+/', '', $code) ?? '';
        for ($i = -$window; $i <= $window; $i++) {
            $candidate = $this->codeAt($secret, $timestamp + ($i * $period));
            if (hash_equals($candidate, $code)) {
                return true;
            }
        }
        return false;
    }

    /**
     * URI otpauth:// pour génération de QR code.
     */
    public function provisioningUri(string $secret, string $account): string
    {
        $issuer = rawurlencode((string) $this->config['issuer']);
        $account = rawurlencode($account);
        return "otpauth://totp/{$issuer}:{$account}?secret={$secret}&issuer={$issuer}"
            . '&digits=' . $this->config['digits'] . '&period=' . $this->config['period'];
    }

    private function decodeBase32(string $value): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $value = strtoupper(rtrim($value, '='));
        $buffer = 0;
        $bits = 0;
        $output = '';
        foreach (str_split($value) as $char) {
            $index = strpos($alphabet, $char);
            if ($index === false) {
                throw new \InvalidArgumentException('Secret TOTP Base32 invalide.');
            }
            $buffer = ($buffer << 5) | $index;
            $bits += 5;
            if ($bits >= 8) {
                $bits -= 8;
                $output .= chr(($buffer >> $bits) & 0xFF);
            }
        }
        return $output;
    }
}
