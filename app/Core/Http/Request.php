<?php

declare(strict_types=1);

namespace App\Core\Http;

/**
 * Encapsulation immuable d'une requête HTTP entrante.
 */
final class Request
{
    /** @var array<string,mixed> */
    private array $query;
    /** @var array<string,mixed> */
    private array $post;
    /** @var array<string,mixed> */
    private array $json;
    /** @var array<string,string> */
    private array $headers;
    /** @var array<string,string> */
    private array $cookies;

    private ?array $bearerToken = null;

    /**
     * @param array<string,mixed> $server
     * @param array<string,mixed> $query
     * @param array<string,mixed> $post
     * @param array<string,string> $cookies
     */
    public function __construct(
        private readonly array $server,
        array $query,
        array $post,
        array $cookies,
        private readonly string $rawBody = ''
    ) {
        $this->query = $query;
        $this->post = $post;
        $this->cookies = $cookies;
        $this->headers = $this->normalizeHeaders($server);
        $this->json = $this->parseJson($rawBody);
    }

    public static function capture(): self
    {
        return new self($_SERVER, $_GET, $_POST, $_COOKIE, (string) file_get_contents('php://input'));
    }

    public function method(): string
    {
        $method = strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
        // Support de la surcharge de verbe via _method (formulaires HTML).
        if ($method === 'POST' && isset($this->post['_method'])) {
            $method = strtoupper((string) $this->post['_method']);
        }
        return $method;
    }

    public function isMethod(string $method): bool
    {
        return $this->method() === strtoupper($method);
    }

    public function path(): string
    {
        $uri = $this->server['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        return rtrim($path, '/') ?: '/';
    }

    public function fullUrl(): string
    {
        $scheme = $this->isSecure() ? 'https' : 'http';
        $host = $this->headers['host'] ?? 'localhost';
        return $scheme . '://' . $host . ($this->server['REQUEST_URI'] ?? '/');
    }

    public function isSecure(): bool
    {
        return ($this->server['HTTPS'] ?? '') === 'on'
            || ($this->headers['x-forwarded-proto'] ?? '') === 'https';
    }

    public function ip(): string
    {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
            if (!empty($this->server[$key])) {
                $ip = explode(',', (string) $this->server[$key])[0];
                return trim($ip);
            }
        }
        return '0.0.0.0';
    }

    public function userAgent(): string
    {
        return $this->headers['user-agent'] ?? '';
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->post[$key]
            ?? $this->json[$key]
            ?? $this->query[$key]
            ?? $default;
    }

    /**
     * Toutes les entrées fusionnées (POST + JSON + GET).
     * @return array<string,mixed>
     */
    public function all(): array
    {
        return array_merge($this->query, $this->json, $this->post);
    }

    /**
     * @param list<string> $keys
     * @return array<string,mixed>
     */
    public function only(array $keys): array
    {
        return array_intersect_key($this->all(), array_flip($keys));
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function file(string $key): ?UploadedFile
    {
        if (!isset($_FILES[$key]) || ($_FILES[$key]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        return new UploadedFile($_FILES[$key]);
    }

    public function header(string $name, ?string $default = null): ?string
    {
        return $this->headers[strtolower($name)] ?? $default;
    }

    public function bearerToken(): ?string
    {
        if ($this->bearerToken !== null) {
            return $this->bearerToken[0] ?? null;
        }
        $authorization = $this->header('authorization', '');
        if ($authorization !== null && preg_match('/^Bearer\s+(\S+)$/i', $authorization, $matches)) {
            $this->bearerToken = [$matches[1]];
            return $matches[1];
        }
        $this->bearerToken = [];
        return null;
    }

    public function isApi(): bool
    {
        return str_starts_with($this->path(), '/api/')
            || $this->header('accept') === 'application/json';
    }

    public function isAjax(): bool
    {
        return strtolower($this->header('x-requested-with', '') ?? '') === 'xmlhttprequest';
    }

    public function cookie(string $name): ?string
    {
        return $this->cookies[$name] ?? null;
    }

    public function rawBody(): string
    {
        return $this->rawBody;
    }

    private function parseJson(string $body): array
    {
        if ($body === '' || !str_contains($this->header('content-type', '') ?? '', 'application/json')) {
            return [];
        }
        $decoded = json_decode($body, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string,mixed> $server
     * @return array<string,string>
     */
    private function normalizeHeaders(array $server): array
    {
        $headers = [];
        foreach ($server as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$name] = (string) $value;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
                $name = strtolower(str_replace('_', '-', $key));
                $headers[$name] = (string) $value;
            }
        }
        return $headers;
    }
}
