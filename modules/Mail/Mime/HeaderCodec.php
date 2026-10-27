<?php

declare(strict_types=1);

namespace Modules\Mail\Mime;

/**
 * Codage/décodage des en-têtes RFC 2047 (mots encodés =?UTF-8?B?..?=) et
 * manipulation des listes d'adresses RFC 5322.
 */
final class HeaderCodec
{
    /**
     * Décode un en-tête (mots encodés + repliement) en UTF-8 lisible.
     */
    public static function decodeHeader(string $value): string
    {
        $decoded = mb_decode_mimeheader($value);
        if ($decoded === '' || mb_check_encoding($decoded, 'UTF-8')) {
            return $decoded;
        }
        return mb_convert_encoding($decoded, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
    }

    /**
     * Encode un en-tête UTF-8 pour l'émission (encodage B base64).
     */
    public static function encodeHeader(string $value): string
    {
        if (preg_match('/^[\x20-\x7E]*$/', $value)) {
            return $value;
        }
        return mb_encode_mimeheader($value, 'UTF-8', 'B', "\r\n", 75);
    }

    /**
     * Construit une ligne d'adresse : « Nom encodé <email> » ou l'email seul.
     *
     * @param list<array{name?:string,email:string}> $addresses
     */
    public static function encodeAddressList(array $addresses): string
    {
        $parts = [];
        foreach ($addresses as $address) {
            $email = trim($address['email']);
            $name = trim($address['name'] ?? '');
            $parts[] = $name !== ''
                ? self::encodeHeader($name) . ' <' . $email . '>'
                : $email;
        }
        return implode(', ', $parts);
    }

    /**
     * Parse une liste d'adresses (From, To, Cc) en tableaux name/email.
     *
     * @return list<array{name:string,email:string}>
     */
    public static function decodeAddressList(string $value): array
    {
        $value = self::decodeHeader($value);
        $addresses = [];
        // Le découpage respecte les guillemets et les chevrons.
        foreach (self::splitAddresses($value) as $chunk) {
            $chunk = trim($chunk);
            if ($chunk === '') {
                continue;
            }
            if (preg_match('/^(.*)<([^>]+)>\s*$/', $chunk, $m)) {
                $name = trim($m[1], " \t\"");
                $email = trim($m[2]);
            } else {
                $name = '';
                $email = trim($chunk);
            }
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $addresses[] = ['name' => $name, 'email' => $email];
            }
        }
        return $addresses;
    }

    /**
     * @return list<string>
     */
    private static function splitAddresses(string $value): array
    {
        $chunks = [];
        $buffer = '';
        $depth = 0;
        $quoted = false;
        foreach (str_split($value) as $char) {
            if ($char === '"') {
                $quoted = !$quoted;
            } elseif (!$quoted && $char === '<') {
                $depth++;
            } elseif (!$quoted && $char === '>') {
                $depth = max(0, $depth - 1);
            }
            if ($char === ',' && !$quoted && $depth === 0) {
                $chunks[] = $buffer;
                $buffer = '';
                continue;
            }
            $buffer .= $char;
        }
        if ($buffer !== '') {
            $chunks[] = $buffer;
        }
        return $chunks;
    }
}
