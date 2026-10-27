<?php

declare(strict_types=1);

namespace Modules\Mail\Mime;

/**
 * Construit un message RFC 822/MIME prêt pour SMTP : texte brut, alternative
 * HTML et pièces jointes binaires (base64), en-têtes UTF-8 codés RFC 2047.
 */
final class MimeBuilder
{
    /**
     * @param array{
     *   from:array{name?:string,email:string},
     *   to:list<array{name?:string,email:string}>,
     *   cc?:list<array{name?:string,email:string}>,
     *   subject:string,
     *   text_body:string,
     *   html_body?:string,
     *   attachments?:list<array{filename:string,mime_type:string,contents:string}>
     * } $message
     */
    public function build(array $message): string
    {
        $boundary = 'pbs-' . bin2hex(random_bytes(12));
        $headers = [
            'Date' => date('r'),
            'From' => HeaderCodec::encodeAddressList([$message['from']]),
            'To' => HeaderCodec::encodeAddressList($message['to']),
            'Message-ID' => '<' . bin2hex(random_bytes(16)) . '@' . $this->hostPart() . '>',
            'Subject' => HeaderCodec::encodeHeader($message['subject']),
            'MIME-Version' => '1.0',
        ];
        if (!empty($message['cc'])) {
            $headers['Cc'] = HeaderCodec::encodeAddressList($message['cc']);
        }

        $attachments = $message['attachments'] ?? [];
        if ($attachments === []) {
            $headers['Content-Type'] = $this->alternativeContentType($message, $boundary);
            return implode("\r\n", $this->lines($headers)) . "\r\n\r\n" . $this->buildBody($message, $boundary);
        }

        $headers['Content-Type'] = 'multipart/mixed; boundary="' . $boundary . '"';
        $innerBoundary = 'pbs-' . bin2hex(random_bytes(12));
        $output = implode("\r\n", $this->lines($headers)) . "\r\n\r\n";
        $output .= '--' . $boundary . "\r\n";
        $output .= 'Content-Type: ' . $this->alternativeContentType($message, $innerBoundary) . "\r\n\r\n";
        $output .= $this->buildBody($message, $innerBoundary) . "\r\n";
        foreach ($attachments as $attachment) {
            $output .= '--' . $boundary . "\r\n";
            $output .= $this->buildAttachment($attachment);
        }
        $output .= '--' . $boundary . "--\r\n";
        return $output;
    }

    /**
     * @param array<string,string> $headers
     * @return list<string>
     */
    private function lines(array $headers): array
    {
        $lines = [];
        foreach ($headers as $name => $value) {
            foreach (explode("\r\n", $name . ': ' . $value) as $line) {
                $lines[] = $line;
            }
        }
        return $lines;
    }

    /**
     * @param array<string,mixed> $message
     */
    private function alternativeContentType(array $message, string $boundary): string
    {
        return empty($message['html_body'])
            ? 'text/plain; charset=UTF-8'
            : 'multipart/alternative; boundary="' . $boundary . '"';
    }

    /**
     * @param array<string,mixed> $message
     */
    private function buildBody(array $message, string $boundary): string
    {
        $text = $this->normalize((string) ($message['text_body'] ?? ''));
        if (empty($message['html_body'])) {
            return "Content-Transfer-Encoding: 8bit\r\n\r\n" . $text . "\r\n";
        }
        $html = $this->normalize((string) $message['html_body']);
        $output = '--' . $boundary . "\r\n";
        $output .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $output .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $output .= $text . "\r\n";
        $output .= '--' . $boundary . "\r\n";
        $output .= "Content-Type: text/html; charset=UTF-8\r\n";
        $output .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $output .= $html . "\r\n";
        $output .= '--' . $boundary . "--\r\n";
        return $output;
    }

    /**
     * @param array{filename:string,mime_type:string,contents:string} $attachment
     */
    private function buildAttachment(array $attachment): string
    {
        $mimeType = $attachment['mime_type'] !== '' ? $attachment['mime_type'] : 'application/octet-stream';
        $filename = HeaderCodec::encodeHeader($attachment['filename']);
        $output = 'Content-Type: ' . $mimeType . '; name="' . $filename . '"' . "\r\n";
        $output .= 'Content-Transfer-Encoding: base64' . "\r\n";
        $output .= 'Content-Disposition: attachment; filename="' . $filename . '"' . "\r\n\r\n";
        $output .= chunk_split(base64_encode($attachment['contents']), 76, "\r\n");
        return $output;
    }

    private function normalize(string $body): string
    {
        return str_replace(["\r\n", "\r", "\n"], ["\n", "\n", "\r\n"], $body);
    }

    private function hostPart(): string
    {
        $host = (string) (gethostname() ?: 'localhost');
        return preg_replace('/[^a-z0-9.\-]/i', '', $host) ?: 'localhost';
    }
}
