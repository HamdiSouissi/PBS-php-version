<?php

declare(strict_types=1);

namespace App\Core\Security;

/**
 * Émission et vérification de JWT HMAC-SHA256 (implémentation maison,
 * aucune dépendance externe).
 */
final class Jwt
{
    /** @param array<string,mixed> $config */
    public function __construct(private readonly array $config)
    {
    }

    /**
     * @param array<string,mixed> $claims
     */
    public function issue(array $claims, ?int $ttl = null): string
    {
        $now = time();
        $payload = array_merge([
            'iss' => $this->config['issuer'],
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + ($ttl ?? (int) $this->config['ttl']),
            'jti' => bin2hex(random_bytes(16)),
        ], $claims);

        $header = $this->base64UrlEncode(json_encode(['typ' => 'JWT', 'alg' => $this->config['algo']], JSON_THROW_ON_ERROR));
        $body = $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));
        $signature = $this->base64UrlEncode(
            hash_hmac('sha256', $header . '.' . $body, $this->secret(), true)
        );
        return $header . '.' . $body . '.' . $signature;
    }

    /**
     * @return array<string,mixed> claims décodés
     */
    public function verify(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new \InvalidArgumentException('JWT mal formé.');
        }
        [$header, $body, $signature] = $parts;

        $expected = $this->base64UrlEncode(hash_hmac('sha256', $header . '.' . $body, $this->secret(), true));
        if (!hash_equals($expected, $signature)) {
            throw new \InvalidArgumentException('Signature JWT invalide.');
        }

        $decodedHeader = json_decode($this->base64UrlDecode($header), true);
        if (($decodedHeader['alg'] ?? '') !== $this->config['algo']) {
            throw new \InvalidArgumentException('Algorithme JWT inattendu.');
        }

        $claims = json_decode($this->base64UrlDecode($body), true);
        if (!is_array($claims)) {
            throw new \InvalidArgumentException('Charges utiles JWT illisibles.');
        }
        if (isset($claims['exp']) && time() >= (int) $claims['exp']) {
            throw new \InvalidArgumentException('JWT expiré.');
        }
        if (isset($claims['nbf']) && time() < (int) $claims['nbf']) {
            throw new \InvalidArgumentException('JWT pas encore valide.');
        }
        return $claims;
    }

    private function secret(): string
    {
        $secret = (string) $this->config['secret'];
        if (strlen($secret) < 32) {
            throw new \RuntimeException('JWT_SECRET absent ou trop court (32 caractères minimum).');
        }
        return $secret;
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'), true) ?: '';
    }
}
