<?php

declare(strict_types=1);

namespace App\Core\Http;

/**
 * Réponse JSON standard de l'API.
 */
final class JsonResponse extends Response
{
    /**
     * @param array<string,mixed>|list<mixed> $data
     * @param array<string,string> $headers
     */
    public function __construct(array $data, int $status = 200, array $headers = [])
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        parent::__construct($json, $status, $headers);
        $this->withHeader('Content-Type', 'application/json; charset=UTF-8');
    }
}
