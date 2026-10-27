<?php

declare(strict_types=1);

namespace Modules\Mail\Models;

use App\Core\Exception\ValidationException;

/**
 * Message sortant saisi par l'utilisateur, validé et indépendant du
 * transport. Les adresses Bcc ne sont volontairement pas intégrées aux
 * en-têtes MIME : elles ne servent qu'à l'enveloppe SMTP.
 */
final class OutgoingMail
{
    /**
     * @param list<array{name?:string,email:string}> $to
     * @param list<array{name?:string,email:string}> $cc
     * @param list<array{name?:string,email:string}> $bcc
     * @param list<array{filename:string,mime_type:string,contents:string}> $attachments
     */
    private function __construct(
        public readonly array $to,
        public readonly array $cc,
        public readonly array $bcc,
        public readonly string $subject,
        public readonly string $textBody,
        public readonly string $htmlBody,
        public readonly array $attachments
    ) {
    }

    /**
     * @param array<string,mixed> $input
     */
    public static function fromInput(array $input): self
    {
        $errors = [];
        $to = self::parseAddressField($input['to'] ?? '', $errors, 'to');
        $cc = self::parseAddressField($input['cc'] ?? '', $errors, 'cc');
        $bcc = self::parseAddressField($input['bcc'] ?? '', $errors, 'bcc');

        if ($to === []) {
            $errors['to'] = 'Au moins un destinataire est requis.';
        }
        $subject = trim((string) ($input['subject'] ?? ''));
        if ($subject === '') {
            $errors['subject'] = 'Le sujet est requis.';
        }
        $textBody = trim((string) ($input['text_body'] ?? ''));
        $htmlBody = trim((string) ($input['html_body'] ?? ''));
        if ($textBody === '' && $htmlBody === '') {
            $errors['text_body'] = 'Le contenu du message est requis.';
        }
        $attachments = self::parseAttachments($input['attachments'] ?? [], $errors);

        if ($errors !== []) {
            throw new ValidationException($errors);
        }
        return new self($to, $cc, $bcc, $subject, $textBody, $htmlBody, $attachments);
    }

    /**
     * @return list<string>
     */
    public function recipientEmails(): array
    {
        $emails = [];
        foreach ([$this->to, $this->cc, $this->bcc] as $group) {
            foreach ($group as $address) {
                $emails[] = $address['email'];
            }
        }
        return array_values(array_unique($emails));
    }

    public function totalRecipients(): int
    {
        return count($this->recipientEmails());
    }

    public function attachmentsSize(): int
    {
        return array_sum(array_map(static fn (array $a): int => strlen($a['contents']), $this->attachments));
    }

    /**
     * @param mixed $value
     * @return list<array{email:string}>
     */
    private static function parseAddressField(mixed $value, array &$errors, string $field): array
    {
        $rawValues = is_array($value) ? $value : explode(',', (string) $value);
        $addresses = [];
        foreach ($rawValues as $raw) {
            $candidate = trim((string) $raw);
            if ($candidate === '') {
                continue;
            }
            if (!filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
                $errors[$field] = 'Adresse e-mail invalide : ' . $candidate;
                continue;
            }
            $addresses[] = ['email' => $candidate];
        }
        return $addresses;
    }

    /**
     * @param mixed $value
     * @return list<array{filename:string,mime_type:string,contents:string}>
     */
    private static function parseAttachments(mixed $value, array &$errors): array
    {
        if (!is_array($value)) {
            return [];
        }
        $attachments = [];
        foreach ($value as $item) {
            if (!is_array($item) || !isset($item['filename'], $item['mime_type'], $item['contents'])) {
                $errors['attachments'] = 'Une pièce jointe est mal formée.';
                continue;
            }
            $attachments[] = [
                'filename' => (string) $item['filename'],
                'mime_type' => (string) $item['mime_type'],
                'contents' => (string) $item['contents'],
            ];
        }
        return $attachments;
    }
}
