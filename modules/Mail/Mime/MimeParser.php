<?php

declare(strict_types=1);

namespace Modules\Mail\Mime;

/**
 * Parseur MIME maison (RFC 2045/2046/2047) : transforme un message RFC 822
 * brut en arbre de MimePart, sans extension imap ni bibliothèque externe.
 */
final class MimeParser
{
    public function parse(string $raw): MimePart
    {
        $raw = str_replace("\r\n", "\n", $raw);
        $separator = strpos($raw, "\n\n");
        if ($separator === false) {
            return new MimePart([], $raw);
        }
        $headerBlock = substr($raw, 0, $separator);
        $body = substr($raw, $separator + 2);
        $headers = $this->parseHeaders($headerBlock);

        $part = new MimePart($headers, $body);
        if ($part->isMultipart() && $part->boundary() !== null) {
            return new MimePart($headers, $body, $this->parseChildren($body, $part->boundary()));
        }
        return $part;
    }

    /**
     * @return list<MimePart>
     */
    private function parseChildren(string $body, string $boundary): array
    {
        $parts = explode('--' . $boundary, $body);
        $children = [];
        foreach ($parts as $index => $chunk) {
            // Préambule avant la première limite et épilogue après la dernière.
            if ($index === 0 || trim($chunk) === '--' || trim($chunk) === '') {
                continue;
            }
            $chunk = ltrim($chunk, "\r\n");
            $separator = strpos($chunk, "\n\n");
            if ($separator === false) {
                continue;
            }
            $children[] = $this->parse(str_replace("\n", "\r\n", $chunk));
        }
        return $children;
    }

    /**
     * @return array<string,string>
     */
    private function parseHeaders(string $block): array
    {
        // Dé-repliement des en-têtes sur plusieurs lignes.
        $unfolded = preg_replace("/\n[ \t]+/", ' ', $block) ?? '';
        $headers = [];
        foreach (explode("\n", $unfolded) as $line) {
            $position = strpos($line, ':');
            if ($position === false) {
                continue;
            }
            $name = strtolower(substr($line, 0, $position));
            $value = HeaderCodec::decodeHeader(trim(substr($line, $position + 1)));
            $headers[$name] = $value;
        }
        return $headers;
    }
}
