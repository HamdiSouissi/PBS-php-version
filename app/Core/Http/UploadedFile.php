<?php

declare(strict_types=1);

namespace App\Core\Http;

use App\Core\Exception\ValidationException;

/**
 * Fichier uploadé via le formulaire, avec validation sécurisée.
 */
final class UploadedFile
{
    private const UPLOAD_ERRORS = [
        UPLOAD_ERR_INI_SIZE => 'Fichier trop volumineux (limite PHP ini).',
        UPLOAD_ERR_FORM_SIZE => 'Fichier trop volumineux (limite formulaire).',
        UPLOAD_ERR_PARTIAL => 'Téléversement interrompu.',
        UPLOAD_ERR_NO_FILE => 'Aucun fichier envoyé.',
        UPLOAD_ERR_NO_TMP_DIR => 'Dossier temporaire absent.',
        UPLOAD_ERR_CANT_WRITE => 'Impossible d\'écrire le fichier.',
        UPLOAD_ERR_EXTENSION => 'Téléversement bloqué par une extension PHP.',
    ];

    /** @param array<string,mixed> $file */
    public function __construct(private readonly array $file)
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new ValidationException([
                'file' => self::UPLOAD_ERRORS[$file['error'] ?? UPLOAD_ERR_NO_FILE] ?? 'Erreur de téléversement.',
            ]);
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            throw new ValidationException(['file' => 'Fichier non issu d\'un téléversement valide.']);
        }
    }

    public function originalName(): string
    {
        return (string) $this->file['name'];
    }

    public function mime(): string
    {
        return (string) ($this->file['type'] ?? '');
    }

    public function size(): int
    {
        return (int) $this->file['size'];
    }

    public function extension(): string
    {
        return strtolower(pathinfo($this->originalName(), PATHINFO_EXTENSION));
    }

    public function safeName(): string
    {
        return bin2hex(random_bytes(16)) . '.' . $this->extension();
    }

    public function moveTo(string $targetDirectory, ?string $name = null): string
    {
        if (!is_dir($targetDirectory) && !mkdir($targetDirectory, 0775, true) && !is_dir($targetDirectory)) {
            throw new \RuntimeException("Création du dossier impossible : {$targetDirectory}");
        }
        $name ??= $this->safeName();
        $destination = rtrim($targetDirectory, '/\\') . DIRECTORY_SEPARATOR . $name;
        if (!move_uploaded_file($this->file['tmp_name'], $destination)) {
            throw new \RuntimeException('Impossible de déplacer le fichier téléversé.');
        }
        chmod($destination, 0640);
        return $destination;
    }
}
