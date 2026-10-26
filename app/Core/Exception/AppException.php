<?php

declare(strict_types=1);

namespace App\Core\Exception;

/**
 * Exception métier de base (message affichable à l'utilisateur).
 */
class AppException extends \RuntimeException
{
    /** @var array<string,string> */
    protected array $context = [];

    /** @param array<string,string> $context */
    public function __construct(string $message = '', int $code = 0, array $context = [], ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    /** @return array<string,string> */
    public function context(): array
    {
        return $this->context;
    }
}
