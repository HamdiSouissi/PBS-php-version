<?php

declare(strict_types=1);

namespace App\Core\Http;

/**
 * Réponse de redirection.
 */
final class RedirectResponse extends Response
{
    public function __construct(string $url, int $status = 302)
    {
        parent::__construct('', $status, ['Location' => $url]);
    }

    /**
     * Flashe les saisies du formulaire pour ré-affichage après redirection.
     * @param array<string,mixed> $input
     */
    public function withInput(array $input): self
    {
        $_SESSION['_flash']['_old'] = array_diff_key($input, array_flip(['password', '_csrf']));
        return $this;
    }

    /**
     * @param array<string,string> $errors
     */
    public function withErrors(array $errors): self
    {
        $_SESSION['_flash']['errors'] = $errors;
        return $this;
    }
}
