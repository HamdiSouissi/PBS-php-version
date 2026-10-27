<?php

declare(strict_types=1);

namespace App\Core\Support;

/**
 * Chargeur minimal de fichiers .env (pas de dépendance externe).
 */
final class Env
{
    public static function load(string $file): void
    {
        if (!is_file($file) || !is_readable($file)) {
            return;
        }
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$key, $rawValue] = explode('=', $line, 2);
            $key = trim($key);
            $trimmed = trim($rawValue);
            $length = strlen($trimmed);
            if ($length >= 2) {
                $first = $trimmed[0];
                $last = $trimmed[$length - 1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    // Valeur entièrement entre guillemets : contenu brut,
                    // un éventuel '#' interne est conservé tel quel.
                    $value = substr($trimmed, 1, -1);
                } else {
                    // Valeur non quotée : un '#' en début ou précédé d'un blanc
                    // démarre un commentaire en ligne (convention dotenv).
                    $value = trim((string) preg_replace('/(^|\s)#.*$/', '', $rawValue));
                }
            } else {
                $value = $trimmed;
            }
            if (getenv($key) === false) {
                putenv("{$key}={$value}");
                $_ENV[$key] = $value;
            }
        }
    }
}
