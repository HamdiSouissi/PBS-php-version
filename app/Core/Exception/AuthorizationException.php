<?php

declare(strict_types=1);

namespace App\Core\Exception;

final class AuthorizationException extends HttpException
{
    public function __construct(string $message = 'Accès non autorisé.')
    {
        parent::__construct(403, $message);
    }
}
