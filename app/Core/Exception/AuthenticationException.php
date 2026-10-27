<?php

declare(strict_types=1);

namespace App\Core\Exception;

final class AuthenticationException extends HttpException
{
    public function __construct(string $message = 'Authentification requise.')
    {
        parent::__construct(401, $message);
    }
}
