<?php

declare(strict_types=1);

namespace App\Core\Exception;

/**
 * Levée quand la validation serveur échoue.
 */
final class ValidationException extends HttpException
{
    /** @param array<string,string> $errors champ => message */
    public function __construct(private readonly array $errors)
    {
        parent::__construct(422, 'Validation des données échouée.');
    }

    /** @return array<string,string> */
    public function errors(): array
    {
        return $this->errors;
    }
}
