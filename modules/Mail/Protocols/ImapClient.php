<?php

declare(strict_types=1);

namespace Modules\Mail\Protocols;

/**
 * Client IMAP minimal en PHP natif (RFC 3501 / RFC 9051), sans ext-imap :
 * authentification LOGIN, dossiers (LIST/SELECT), recherche, récupération
 * d'enveloppes et de messages complets, drapeaux, suppression, déplacement
 * et ajout aux messages envoyés (APPEND).
 *
 * Le client est mono-usage : une instance par connexion, fermée par logout().
 */
final class ImapClient
{
    private int $sequence = 0;

    private bool $loggedIn = false;

    public function __construct(private readonly MailTransport $transport)
    {
    }

    /**
     * @param array{host:string,port:int,encryption:string,timeout?:int} $server
     */
    public function connect(array $server, string $username, string $password): void
    {
        $this->transport->connect(
            $server['host'],
            (int) $server['port'],
            $server['encryption'],
            (int) ($server['timeout'] ?? 15)
        );
        $greeting = $this->transport->readLine();
        if (!str_starts_with($greeting, '* OK') && !str_starts_with($greeting, '* PREAUTH')) {
            throw new ProtocolException('Le serveur IMAP a refusé la connexion.');
        }
        if (($server['encryption'] ?? 'ssl') === 'tls') {
            $this->command('CAPABILITY');
            $this->command('STARTTLS');
            $this->transport->upgradeToTls();
        }
        $this->login($username, $password);
    }

    private function login(string $username, string $password): void
    {
        $this->command(
            'LOGIN ' . $this->quoteString($username) . ' ' . $this->quoteString($password)
        );
        $this->loggedIn = true;
    }

    /**
     * Liste les dossiers disponibles (nom brut UTF7-IMAP converti en UTF-8).
     *
     * @return list<array{name:string,flags:list<string>}>
     */
    public function listFolders(): array
    {
        [, , $raw] = $this->command('LIST "" "*"');
        $folders = [];
        foreach (preg_split('/\r\n/', trim($raw)) ?: [] as $line) {
            if (!str_starts_with($line, '* LIST')) {
                continue;
            }
            $flags = [];
            if (preg_match('/LIST \(([^)]*)\)/', $line, $flagMatch)) {
                $flags = array_values(array_filter(explode(' ', trim($flagMatch[1]))));
            }
            if (preg_match('/"([^"]*)" (.*)$/', $line, $nameMatch)) {
                $mailbox = $this->unquoteMailbox($nameMatch[2]);
            } else {
                continue;
            }
            $folders[] = [
                'name' => $this->decodeMailboxName($mailbox),
                'flags' => $flags,
            ];
        }
        return $folders;
    }

    /**
     * Sélectionne un dossier. Retourne le nombre de messages et le numéro
     * du premier message non lu (1-based), null si tous lus.
     *
     * @return array{exists:int,unseen_id:int|null}
     */
    public function select(string $folder): array
    {
        [, , $raw] = $this->command('SELECT ' . $this->quoteMailbox($folder));
        $exists = 0;
        if (preg_match('/\* (\d+) EXISTS/', $raw, $m)) {
            $exists = (int) $m[1];
        }
        $unseenId = null;
        if (preg_match('/\* OK \[UNSEEN (\d+)\]/', $raw, $m)) {
            $unseenId = (int) $m[1];
        }
        return ['exists' => $exists, 'unseen_id' => $unseenId];
    }

    /**
     * Recherche des numéros de séquence selon un critère IMAP (ALL, UNSEEN,
     * TEXT "mot", SINCE date...).
     *
     * @return list<int>
     */
    public function search(string $criteria): array
    {
        [, , $raw] = $this->command('SEARCH ' . $criteria);
        if (!preg_match('/\* SEARCH(.*)\r\n/', $raw, $m)) {
            return [];
        }
        return array_map('intval', array_values(array_filter(explode(' ', trim($m[1])))));
    }

    /**
     * Récupère les enveloppes (en-têtes utiles, drapeaux, taille) d'une
     * liste de numéros de séquence, dans l'ordre croissant.
     *
     * @param list<int> $ids
     * @return list<array<string,mixed>>
     */
    public function fetchEnvelopes(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $set = implode(',', $ids);
        [, , $raw] = $this->command(
            'FETCH ' . $set . ' (FLAGS INTERNALDATE RFC822.SIZE BODY.PEEK[HEADER.FIELDS (FROM TO CC SUBJECT DATE)])'
        );
        return $this->parseFetchBlocks($raw);
    }

    /**
     * Récupère le contenu RFC 822 complet d'un message.
     */
    public function fetchFullMessage(int $id): string
    {
        [, , $raw] = $this->command('FETCH ' . $id . ' (BODY.PEEK[])');
        if (!preg_match('/BODY\[\] \{\d+\}\r\n/', $raw, $m, PREG_OFFSET_CAPTURE)) {
            throw new ProtocolException('Format de message IMAP inattendu.');
        }
        $start = $m[0][1] + strlen($m[0][0]);
        // Le littéral se termine par le CRLF qui précède la parenthèse de fin.
        $end = strrpos($raw, "\r\n)");
        if ($end === false || $end < $start) {
            throw new ProtocolException('Message IMAP tronqué.');
        }
        return substr($raw, $start, $end - $start);
    }

    public function markRead(int $id): void
    {
        $this->storeFlags($id, '+FLAGS', ['\\Seen']);
    }

    public function markUnread(int $id): void
    {
        $this->storeFlags($id, '-FLAGS', ['\\Seen']);
    }

    public function flag(int $id, bool $flagged): void
    {
        $this->storeFlags($id, $flagged ? '+FLAGS' : '-FLAGS', ['\\Flagged']);
    }

    /**
     * Supprime un message (drapeau \Deleted puis EXPUNGE).
     */
    public function delete(int $id): void
    {
        $this->storeFlags($id, '+FLAGS', ['\\Deleted']);
        $this->command('EXPUNGE');
    }

    /**
     * Déplace un message vers un autre dossier (MOVE RFC 6851 avec
     * repli COPY + \Deleted + EXPUNGE sur les serveurs plus anciens).
     */
    public function move(int $id, string $folder): void
    {
        [$status, $raw] = $this->commandQuiet('MOVE ' . $id . ' ' . $this->quoteMailbox($folder));
        if ($status === 'OK') {
            return;
        }
        $this->command('COPY ' . $id . ' ' . $this->quoteMailbox($folder));
        $this->storeFlags($id, '+FLAGS', ['\\Deleted']);
        $this->command('EXPUNGE');
    }

    /**
     * Ajoute un message envoyé dans un dossier (Sent), avec drapeau \Seen.
     */
    public function append(string $folder, string $rfc822Message): void
    {
        $tag = $this->nextTag();
        $this->transport->write(
            $tag . ' APPEND ' . $this->quoteMailbox($folder) . ' (\Seen) {' . strlen($rfc822Message) . "}\r\n"
        );
        $continuation = $this->transport->readLine();
        if (!str_starts_with($continuation, '+')) {
            throw new ProtocolException('Le serveur IMAP refuse le dépôt du message envoyé.');
        }
        $this->transport->write($rfc822Message . "\r\n");
        [$status, $raw] = $this->readTagged($tag);
        if ($status !== 'OK') {
            throw new ProtocolException('Le dépôt IMAP du message envoyé a échoué : ' . $this->firstLine($raw));
        }
    }

    public function logout(): void
    {
        if (!$this->transport->isConnected()) {
            return;
        }
        try {
            $tag = $this->nextTag();
            $this->transport->write($tag . " LOGOUT\r\n");
            $this->readTagged($tag);
        } catch (ProtocolException) {
            // La fermeture reste garantie même si le serveur clôture brutalement.
        } finally {
            $this->transport->close();
            $this->loggedIn = false;
        }
    }

    public function isLoggedIn(): bool
    {
        return $this->loggedIn;
    }

    /**
     * @param list<string> $flags
     */
    private function storeFlags(int $id, string $operation, array $flags): void
    {
        $this->command('STORE ' . $id . ' ' . $operation . ' (' . implode(' ', $flags) . ')');
    }

    /**
     * @return array{0:string,1:string,2:string} tag, statut, réponse brute
     */
    private function command(string $command): array
    {
        [$tag, $status, $raw] = $this->commandQuiet($command);
        if ($status !== 'OK') {
            throw new ProtocolException('Le serveur de messagerie a rejeté la commande : ' . $this->firstLine($raw));
        }
        return [$tag, $status, $raw];
    }

    /**
     * Variante ne levant pas d'exception (utile pour tester MOVE avec repli).
     *
     * @return array{0:string,1:string,2:string}
     */
    private function commandQuiet(string $command): array
    {
        $tag = $this->nextTag();
        $this->transport->write($tag . ' ' . $command . "\r\n");
        [$status, $raw] = $this->readTagged($tag);
        return [$tag, $status, $raw];
    }

    /**
     * Lit la réponse jusqu'à la ligne taggée, en reconstituant les
     * littéraux {n} annoncés par le serveur.
     *
     * @return array{0:string,1:string} statut, réponse brute
     */
    private function readTagged(string $tag): array
    {
        $buffer = '';
        $lastLine = '';
        while (true) {
            $line = $this->transport->readLine();
            if (preg_match('/\{(\d+)\+?\}\r\n$/', $line, $literal)) {
                $buffer .= $line;
                $buffer .= $this->transport->readBytes((int) $literal[1]);
                $buffer .= $this->transport->readLine();
                continue;
            }
            $buffer .= $line;
            $lastLine = $line;
            if (str_starts_with($line, $tag . ' ')) {
                break;
            }
        }
        $status = 'BAD';
        if (str_starts_with($lastLine, $tag . ' OK')) {
            $status = 'OK';
        } elseif (str_starts_with($lastLine, $tag . ' NO')) {
            $status = 'NO';
        }
        return [$status, $buffer];
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function parseFetchBlocks(string $raw): array
    {
        $blocks = preg_split('/(?=^\* \d+ FETCH)/m', $raw) ?: [];
        $messages = [];
        foreach ($blocks as $block) {
            if (!str_starts_with(ltrim($block), '*')) {
                continue;
            }
            $entry = ['flags' => [], 'seen' => false, 'flagged' => false, 'size' => 0, 'headers' => []];
            if (preg_match('/^\* (\d+) FETCH/', $block, $idMatch)) {
                $entry['sequence'] = (int) $idMatch[1];
            }
            if (preg_match('/FLAGS \(([^)]*)\)/', $block, $flagMatch)) {
                $entry['flags'] = array_values(array_filter(explode(' ', trim($flagMatch[1]))));
                $entry['seen'] = in_array('\\Seen', $entry['flags'], true);
                $entry['flagged'] = in_array('\\Flagged', $entry['flags'], true);
            }
            if (preg_match('/RFC822\.SIZE (\d+)/', $block, $sizeMatch)) {
                $entry['size'] = (int) $sizeMatch[1];
            }
            if (preg_match('/HEADER\.FIELDS[^]]*\] \{\d+\}\r\n/', $block, $headerMarker, PREG_OFFSET_CAPTURE)) {
                $start = $headerMarker[0][1] + strlen($headerMarker[0][0]);
                $end = strrpos($block, "\r\n)");
                $headerBlock = $end !== false && $end >= $start ? substr($block, $start, $end - $start) : '';
                $entry['headers'] = $this->parseHeaders($headerBlock);
            }
            $messages[] = $entry;
        }
        return $messages;
    }

    /**
     * @return array<string,string>
     */
    private function parseHeaders(string $block): array
    {
        $folded = preg_replace("/\r\n[ \t]+/", ' ', $block) ?? '';
        $headers = [];
        foreach (preg_split('/\r\n/', $folded) ?: [] as $line) {
            $position = strpos($line, ':');
            if ($position === false) {
                continue;
            }
            $name = strtolower(substr($line, 0, $position));
            $headers[$name] = \Modules\Mail\Mime\HeaderCodec::decodeHeader(trim(substr($line, $position + 1)));
        }
        return $headers;
    }

    private function quoteString(string $value): string
    {
        return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
    }

    private function quoteMailbox(string $folder): string
    {
        $encoded = $this->encodeMailboxName($folder);
        return $this->quoteString($encoded);
    }

    private function unquoteMailbox(string $value): string
    {
        $value = trim($value);
        if (str_starts_with($value, '"') && str_ends_with($value, '"')) {
            return str_replace(['\\"', '\\\\'], ['"', '\\'], substr($value, 1, -1));
        }
        return $value;
    }

    private function encodeMailboxName(string $folder): string
    {
        // Les dossiers IMAP utilisent le modifié UTF-7 (RFC 3501).
        return mb_convert_encoding($folder, 'UTF7-IMAP', 'UTF-8');
    }

    private function decodeMailboxName(string $folder): string
    {
        return mb_convert_encoding($folder, 'UTF-8', 'UTF7-IMAP');
    }

    private function firstLine(string $raw): string
    {
        $line = strtok($raw, "\r\n") ?: '';
        return mb_substr($line, 0, 180);
    }

    private function nextTag(): string
    {
        return 'A' . str_pad((string) (++$this->sequence), 4, '0', STR_PAD_LEFT);
    }
}
