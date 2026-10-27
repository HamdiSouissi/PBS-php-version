<?php

declare(strict_types=1);

namespace Modules\Mail\Models;

use Modules\Mail\Mime\HeaderCodec;
use Modules\Mail\Mime\MimePart;

/**
 * Message présenté à l'utilisateur. Utilisé en liste (enveloppes seules)
 * comme en lecture détaillée (corps et pièces jointes alors renseignés).
 */
final class MailMessage
{
    /**
     * @param list<array{name:string,email:string}> $to
     * @param list<MailAttachment> $attachments
     */
    public function __construct(
        public readonly int $sequence,
        public readonly string $folder,
        public readonly string $fromName,
        public readonly string $fromEmail,
        public readonly array $to,
        public readonly string $subject,
        public readonly ?string $dateText,
        public readonly int $size,
        public readonly bool $seen,
        public readonly bool $flagged,
        public readonly string $textBody = '',
        public readonly string $htmlBody = '',
        public readonly array $attachments = []
    ) {
    }

    /**
     * Construit une entrée de liste depuis une enveloppe IMAP parsée.
     *
     * @param array<string,mixed> $envelope
     */
    public static function fromEnvelope(array $envelope, string $folder): self
    {
        $headers = $envelope['headers'] ?? [];
        $from = HeaderCodec::decodeAddressList((string) ($headers['from'] ?? ''));
        $to = HeaderCodec::decodeAddressList((string) ($headers['to'] ?? ''));
        $subject = trim((string) ($headers['subject'] ?? '')) !== ''
            ? (string) $headers['subject']
            : '(sans objet)';

        return new self(
            (int) $envelope['sequence'],
            $folder,
            $from[0]['name'] ?? '',
            $from[0]['email'] ?? ($headers['from'] ?? ''),
            $to,
            $subject,
            self::normalizeDate((string) ($headers['date'] ?? '')),
            (int) ($envelope['size'] ?? 0),
            (bool) ($envelope['seen'] ?? false),
            (bool) ($envelope['flagged'] ?? false)
        );
    }

    /**
     * Construit le détail complet d'un message à partir de son contenu MIME.
     *
     * @param array<string,mixed> $envelope
     */
    public static function detail(array $envelope, string $folder, MimePart $mime): self
    {
        $base = self::fromEnvelope($envelope, $folder);
        $attachments = [];
        foreach ($mime->attachments() as $part) {
            $contents = $part->decodedBody();
            $attachments[] = new MailAttachment(
                $part->filename() !== '' ? $part->filename() : 'piece-jointe',
                $part->contentType(),
                strlen($contents),
                $part->contentId(),
                $contents
            );
        }
        return new self(
            $base->sequence,
            $base->folder,
            $base->fromName,
            $base->fromEmail,
            $base->to,
            $base->subject,
            $base->dateText,
            $base->size,
            true,
            $base->flagged,
            $mime->textBody(),
            $mime->htmlBody(),
            $attachments
        );
    }

    public function hasAttachments(): bool
    {
        return $this->attachments !== [];
    }

    private static function normalizeDate(string $rawDate): ?string
    {
        if (trim($rawDate) === '') {
            return null;
        }
        try {
            return (new \DateTimeImmutable($rawDate))->format('d/m/Y H:i');
        } catch (\Exception) {
            return $rawDate;
        }
    }
}
