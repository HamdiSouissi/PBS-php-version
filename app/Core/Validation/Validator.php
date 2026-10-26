<?php

declare(strict_types=1);

namespace App\Core\Validation;

use App\Core\Exception\ValidationException;

/**
 * Validateur serveur maison : règles déclaratives, messages en français.
 */
final class Validator
{
    /**
     * @param array<string,mixed> $data
     * @param array<string,string> $rules ex: ['email' => 'required|email|max:180']
     * @param array<string,string> $labels libellés lisibles des champs
     * @return array<string,mixed> données validées (uniquement les champs régulés)
     */
    public function validate(array $data, array $rules, array $labels = []): array
    {
        $errors = [];
        $validated = [];
        foreach ($rules as $field => $ruleString) {
            $value = $data[$field] ?? null;
            $label = $labels[$field] ?? $field;
            foreach (explode('|', $ruleString) as $rule) {
                $parameters = [];
                if (str_contains($rule, ':')) {
                    [$rule, $parameterString] = explode(':', $rule, 2);
                    $parameters = explode(',', $parameterString);
                }
                $message = $this->check($rule, $field, $value, $parameters, $label, $data);
                if ($message !== null) {
                    $errors[$field][] = $message;
                    break;
                }
            }
            if (!array_key_exists($field, $errors)) {
                $validated[$field] = $value;
            }
        }
        if ($errors !== []) {
            throw new ValidationException(array_map(static fn (array $m) => $m[0], $errors));
        }
        return $validated;
    }

    /** @param list<string> $parameters @param array<string,mixed> $all */
    private function check(string $rule, string $field, mixed $value, array $parameters, string $label, array $all): ?string
    {
        $filled = $value !== null && $value !== '';
        return match ($rule) {
            'required' => $filled ? null : "Le champ {$label} est obligatoire.",
            'nullable' => null,
            'string' => $filled && !is_string($value) ? "Le champ {$label} doit être une chaîne de caractères." : null,
            'integer' => $filled && filter_var($value, FILTER_VALIDATE_INT) === false ? "Le champ {$label} doit être un entier." : null,
            'numeric' => $filled && !is_numeric($value) ? "Le champ {$label} doit être numérique." : null,
            'boolean' => $filled && !in_array($value, [true, false, 0, 1, '0', '1'], true)
                ? "Le champ {$label} doit être un booléen." : null,
            'email' => $filled && filter_var($value, FILTER_VALIDATE_EMAIL) === false
                ? "Le champ {$label} doit être une adresse e-mail valide." : null,
            'url' => $filled && filter_var($value, FILTER_VALIDATE_URL) === false
                ? "Le champ {$label} doit être une URL valide." : null,
            'min' => $this->minCheck($rule, $field, $value, $parameters, $label, $filled),
            'max' => $this->maxCheck($value, $parameters, $label, $filled),
            'in' => $filled && !in_array($value, $parameters, true)
                ? "Le champ {$label} doit faire partie des valeurs autorisées." : null,
            'date' => $filled && strtotime((string) $value) === false
                ? "Le champ {$label} doit être une date valide." : null,
            'date_format' => $filled && \DateTime::createFromFormat($parameters[0], (string) $value) === false
                ? "Le champ {$label} doit respecter le format {$parameters[0]}." : null,
            'regex' => $filled && preg_match($parameters[0], (string) $value) !== 1
                ? "Le champ {$label} a un format invalide." : null,
            'confirmed' => $value !== ($all[$field . '_confirmation'] ?? null)
                ? "La confirmation du champ {$label} ne correspond pas." : null,
            'same' => $value !== ($all[$parameters[0]] ?? null)
                ? "Le champ {$label} doit être identique à {$parameters[0]}." : null,
            'decimal' => $filled && !preg_match('/^-?\d+(\.\d{1,' . ($parameters[0] ?? 2) . '})?$/', (string) $value)
                ? "Le champ {$label} doit être un montant décimal valide." : null,
            'sirene' => $filled && !preg_match('/^[0-9]{9}$/', (string) $value)
                ? "Le champ {$label} doit contenir 9 chiffres (SIREN)." : null,
            'siret' => $filled && !preg_match('/^[0-9]{14}$/', (string) $value)
                ? "Le champ {$label} doit contenir 14 chiffres (SIRET)." : null,
            'iban' => $filled && !preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9 ]{11,30}$/', (string) $value)
                ? "Le champ {$label} doit être un IBAN valide." : null,
            default => null,
        };
    }

    /** @param list<string> $parameters */
    private function minCheck(string $rule, string $field, mixed $value, array $parameters, string $label, bool $filled): ?string
    {
        if (!$filled) {
            return null;
        }
        $min = (int) $parameters[0];
        if (is_numeric($value)) {
            return (float) $value < $min ? "Le champ {$label} doit être supérieur ou égal à {$min}." : null;
        }
        return mb_strlen((string) $value) < $min ? "Le champ {$label} doit contenir au moins {$min} caractères." : null;
    }

    /** @param list<string> $parameters */
    private function maxCheck(mixed $value, array $parameters, string $label, bool $filled): ?string
    {
        if (!$filled) {
            return null;
        }
        $max = (int) $parameters[0];
        if (is_numeric($value)) {
            return (float) $value > $max ? "Le champ {$label} doit être inférieur ou égal à {$max}." : null;
        }
        return mb_strlen((string) $value) > $max ? "Le champ {$label} ne doit pas dépasser {$max} caractères." : null;
    }
}
