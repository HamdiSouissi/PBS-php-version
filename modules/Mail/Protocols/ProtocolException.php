<?php

declare(strict_types=1);

namespace Modules\Mail\Protocols;

use App\Core\Exception\AppException;

/**
 * Erreur de communication avec le serveur de messagerie (connexion,
 * négociation TLS, réponse inattendue ou rejet du serveur). Le message
 * reste volontairement sobre : aucun secret ne doit y figurer.
 */
final class ProtocolException extends AppException
{
}
