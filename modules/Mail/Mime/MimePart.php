<?php

declare(strict_types=1);

namespace Modules\Mail\Mime;

/**
 * Nœud d'un message MIME (RFC 2045/2046) : en-têtes, corps brut, sous-parties
 * pour les contenus multipart. Fournit les accès pratiques utilisés par
 * l'affichage (texte, HTML, pièces jointes).
 */
final class MimePart
{
    /**
     * @param array<string,string> $headers en-têtes normalisés (clés minuscules)
     * @param list<MimePart> $children
     */
    public function __construct(
        private array $headers,
        private string $body,
        private array $children = []
    ) {
    }

    /**
     * @return array<string,string>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    public function header(string $name, string $default = ''): string
    {
        return $this->headers[strtolower($name)] ?? $default;
    }

    public function contentType(): string
    {
        $type = $this->header('content-type', 'text/plain');
        return trim(strtolower(explode(';', $type)[0]));
    }

    public function charset(): string
    {
        return $this->contentTypeParameter('charset') ?: 'UTF-8';
    }

    public function boundary(): ?string
    {
        $boundary = $this->contentTypeParameter('boundary');
        return $boundary !== '' ? $boundary : null;
    }

    public function isMultipart(): bool
    {
        return str_starts_with($this->contentType(), 'multipart/');
    }

    public function disposition(): string
    {
        $disposition = $this->header('content-disposition');
        return $disposition === '' ? '' : trim(strtolower(explode(';', $disposition)[0]));
    }

    public function filename(): string
    {
        $disposition = $this->header('content-disposition');
        if (preg_match("/filename\*=([^']*)''([^;]+)/i", $disposition, $m)) {
            return rawurldecode($m[2]);
        }
        if (preg_match('/filename="?([^";]+)"?/i', $disposition, $m)) {
            return HeaderCodec::decodeHeader(trim($m[1]));
        }
        if (preg_match('/name="?([^";]+)"?/i', $this->header('content-type'), $m)) {
            return HeaderCodec::decodeHeader(trim($m[1]));
        }
        return '';
    }

    public function contentId(): string
    {
        return trim($this->header('content-id'), '<> ');
    }

    public function isAttachment(): bool
    {
        return $this->disposition() === 'attachment'
            || ($this->disposition() === 'inline' && $this->filename() !== '');
    }

    /**
     * Corps décodé selon Content-Transfer-Encoding, converti en UTF-8.
     */
    public function decodedBody(): string
    {
        $encoding = strtolower(trim(explode(';', $this->header('content-transfer-encoding', '7bit'))[0]));
        $body = match ($encoding) {
            'base64' => base64_decode(preg_replace('/\s+/', '', $this->body) ?? '', true) ?: '',
            'quoted-printable' => quoted_printable_decode($this->body),
            default => $this->body,
        };
        $charset = strtoupper($this->charset());
        if ($charset !== '' && $charset !== 'UTF-8' && mb_check_encoding($body, $charset)) {
            $converted = mb_convert_encoding($body, 'UTF-8', $charset);
            if ($converted !== false) {
                return $converted;
            }
        }
        return $body;
    }

    /**
     * @return list<MimePart>
     */
    public function children(): array
    {
        return $this->children;
    }

    public function textBody(): string
    {
        if (!$this->isMultipart()) {
            return $this->contentType() === 'text/plain' ? $this->decodedBody() : '';
        }
        foreach ($this->children as $child) {
            $text = $child->textBody();
            if ($text !== '') {
                return $text;
            }
        }
        return '';
    }

    public function htmlBody(): string
    {
        if (!$this->isMultipart()) {
            return $this->contentType() === 'text/html' ? $this->decodedBody() : '';
        }
        foreach ($this->children as $child) {
            $html = $child->htmlBody();
            if ($html !== '') {
                return $html;
            }
        }
        return '';
    }

    /**
     * @return list<MimePart>
     */
    public function attachments(): array
    {
        if (!$this->isMultipart()) {
            return $this->isAttachment() ? [$this] : [];
        }
        $attachments = [];
        foreach ($this->children as $child) {
            array_push($attachments, ...$child->attachments());
        }
        return $attachments;
    }

    private function contentTypeParameter(string $parameter): string
    {
        $header = $this->header('content-type');
        if (preg_match('/' . preg_quote($parameter, '/') . '="?([^";]+)"?/i', $header, $m)) {
            return trim($m[1]);
        }
        return '';
    }
}
