<?php

declare(strict_types=1);

namespace App\Core\Exception;

class HttpException extends AppException
{
    public function __construct(private readonly int $statusCode, string $message = '', array $context = [])
    {
        parent::__construct($message, $statusCode, $context);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
