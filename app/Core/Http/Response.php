<?php

declare(strict_types=1);

namespace App\Core\Http;

/**
 * Réponse HTTP générique (contenu texte/HTML).
 */
class Response
{
    /** @var array<string,list<string>> */
    protected array $headers = [];

    public function __construct(
        protected string $content = '',
        protected int $status = 200,
        array $headers = []
    ) {
        foreach ($headers as $name => $value) {
            $this->withHeader($name, (string) $value);
        }
    }

    public function withHeader(string $name, string $value): static
    {
        $this->headers[strtolower($name)] = [$value];
        return $this;
    }

    public function addHeader(string $name, string $value): static
    {
        $this->headers[strtolower($name)][] = $value;
        return $this;
    }

    public function status(int $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $values) {
            $first = true;
            foreach ($values as $value) {
                header($this->normalizeHeader($name) . ': ' . $value, $first);
                $first = false;
            }
        }
        echo $this->content;
    }

    private function normalizeHeader(string $name): string
    {
        return implode('-', array_map('ucfirst', explode('-', $name)));
    }
}
