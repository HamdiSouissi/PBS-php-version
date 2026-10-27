<?php

declare(strict_types=1);

namespace Modules\Mail\Services;

use App\Core\Exception\AppException;
use App\Core\Exception\ValidationException;
use Modules\Mail\Mime\MimeParser;
use Modules\Mail\Mime\MimeBuilder;
use Modules\Mail\Models\MailFolder;
use Modules\Mail\Models\MailMessage;
use Modules\Mail\Models\OutgoingMail;
use Modules\Mail\Protocols\ImapClient;
use Modules\Mail\Protocols\MailProtocolFactory;
use Modules\Mail\Protocols\SmtpClient;

/**
 * Orchestration des boîtes utilisateur : ouverture d'une connexion IMAP/SMTP
 * avec les secrets déchiffrés à la volée, lecture (dossiers, liste, détail,
 * recherche), actions sur les messages et envoi SMTP avec dépôt dans les
 * éléments envoyés. Aucun secret n'est jamais mis en cache ni renvoyé aux vues.
 */
final class MailboxService
{
    public function __construct(
        private readonly MailAccountService $accounts,
        private readonly MailProtocolFactory $factory
    ) {
    }

    /**
     * @return list<MailFolder>
     */
    public function folders(int $userId): array
    {
        [$client] = $this->openImap($userId);
        try {
            $folders = [];
            foreach ($client->listFolders() as $entry) {
                $stats = $client->select($entry['name']);
                $unseen = $stats['unseen_id'] === null ? 0 : count($client->search('UNSEEN'));
                $folders[] = new MailFolder($entry['name'], MailFolder::labelFor($entry['name']), $stats['exists'], $unseen);
            }
            return $folders;
        } finally {
            $client->logout();
        }
    }

    /**
     * Liste paginée et éventuellement filtrée (recherche plein texte IMAP).
     *
     * @return array{messages:list<MailMessage>,total:int,page:int,pages:int,folder:string}
     */
    public function messages(int $userId, string $folder, int $page, ?string $query = null): array
    {
        $page = max(1, $page);
        $pageSize = (int) config('mail.mailbox.page_size');
        [$client, $row] = $this->openImap($userId);
        try {
            $client->select($folder);
            $criteria = $query !== null && trim($query) !== ''
                ? 'TEXT ' . $this->quoteSearch(trim($query))
                : 'ALL';
            // SEARCH renvoie les numéros croissants : la liste affiche le plus récent en premier.
            $ids = array_reverse($client->search($criteria));
            $pageIds = array_slice($ids, ($page - 1) * $pageSize, $pageSize);
            $envelopes = $client->fetchEnvelopes($pageIds);
            usort($envelopes, static fn (array $a, array $b): int => $b['sequence'] <=> $a['sequence']);

            $messages = array_map(
                static fn (array $envelope): MailMessage => MailMessage::fromEnvelope($envelope, $folder),
                $envelopes
            );
            $this->accounts->touchSync((int) $row['id']);
            $total = count($ids);
            return [
                'messages' => $messages,
                'total' => $total,
                'page' => $page,
                'pages' => max(1, (int) ceil($total / $pageSize)),
                'folder' => $folder,
            ];
        } finally {
            $client->logout();
        }
    }

    public function message(int $userId, string $folder, int $sequence): MailMessage
    {
        [$client] = $this->openImap($userId);
        try {
            $client->select($folder);
            $envelopes = $client->fetchEnvelopes([$sequence]);
            if ($envelopes === []) {
                throw new AppException('Message introuvable dans ce dossier.');
            }
            $mime = (new MimeParser())->parse($client->fetchFullMessage($sequence));
            try {
                // L'ouverture marque le message comme lu, sans jamais bloquer la lecture.
                $client->markRead($sequence);
            } catch (\Throwable $exception) {
                logger()->warning('Marquage lu impossible : ' . $exception->getMessage());
            }
            return MailMessage::detail($envelopes[0], $folder, $mime);
        } finally {
            $client->logout();
        }
    }

    public function markRead(int $userId, string $folder, int $sequence, bool $read): void
    {
        [$client] = $this->openImap($userId);
        try {
            $client->select($folder);
            if ($read) {
                $client->markRead($sequence);
            } else {
                $client->markUnread($sequence);
            }
        } finally {
            $client->logout();
        }
    }

    public function flag(int $userId, string $folder, int $sequence, bool $flagged): void
    {
        [$client] = $this->openImap($userId);
        try {
            $client->select($folder);
            $client->flag($sequence, $flagged);
        } finally {
            $client->logout();
        }
    }

    public function delete(int $userId, string $folder, int $sequence): void
    {
        [$client] = $this->openImap($userId);
        try {
            $client->select($folder);
            $client->delete($sequence);
        } finally {
            $client->logout();
        }
    }

    public function move(int $userId, string $folder, int $sequence, string $destination): void
    {
        if (trim($destination) === '') {
            throw new ValidationException(['destination' => 'Sélectionnez un dossier de destination.']);
        }
        [$client] = $this->openImap($userId);
        try {
            $client->select($folder);
            $client->move($sequence, $destination);
        } finally {
            $client->logout();
        }
    }

    public function send(int $userId, OutgoingMail $mail): void
    {
        $this->assertWithinLimits($mail);
        $row = $this->accounts->requireActiveRowForUser($userId);

        $from = ['email' => (string) $row['email'], 'name' => (string) $row['display_name']];
        $rfc822 = (new MimeBuilder())->build([
            'from' => $from,
            'to' => $mail->to,
            'cc' => $mail->cc,
            'subject' => $mail->subject,
            'text_body' => $mail->textBody,
            'html_body' => $mail->htmlBody,
            'attachments' => $mail->attachments,
        ]);

        $smtp = $this->factory->connectSmtp(
            $this->smtpServer($row),
            (string) $row['smtp_username'],
            $this->accounts->smtpPassword($row)
        );
        try {
            $smtp->send((string) $row['email'], $mail->recipientEmails(), $rfc822);
        } finally {
            $smtp->quit();
        }

        $this->archiveSentMessage($row, $rfc822);
        audit('mail.send', 'MailMessage', null, [
            'from' => $row['email'],
            'recipients' => $mail->totalRecipients(),
            'attachments' => count($mail->attachments),
            'by' => $userId,
        ]);
    }

    /**
     * @return array{0:ImapClient,1:array<string,mixed>}
     */
    private function openImap(int $userId): array
    {
        $row = $this->accounts->requireActiveRowForUser($userId);
        $client = $this->factory->connectImap(
            $this->imapServer($row),
            (string) $row['imap_username'],
            $this->accounts->imapPassword($row)
        );
        return [$client, $row];
    }

    private function assertWithinLimits(OutgoingMail $mail): void
    {
        $errors = [];
        $maxRecipients = (int) config('mail.mailbox.max_recipients');
        if ($mail->totalRecipients() > $maxRecipients) {
            $errors['to'] = "Nombre maximal de destinataires dépassé ({$maxRecipients}).";
        }
        $maxSize = (int) config('mail.mailbox.max_attachments_size');
        if ($mail->attachmentsSize() > $maxSize) {
            $errors['attachments'] = 'Taille cumulée des pièces jointes trop importante ('
                . number_format($maxSize / (1024 * 1024), 1) . ' Mo maximum).';
        }
        if ($errors !== []) {
            throw new ValidationException($errors);
        }
    }

    /**
     * Dépose le message envoyé dans le dossier « envoyés » quand le serveur
     * ne le fait pas lui-même. Tout échec est tracé mais ne casse pas l'envoi.
     *
     * @param array<string,mixed> $row
     */
    private function archiveSentMessage(array $row, string $rfc822): void
    {
        try {
            $client = $this->factory->connectImap(
                $this->imapServer($row),
                (string) $row['imap_username'],
                $this->accounts->imapPassword($row)
            );
            try {
                $sentFolder = $this->findSentFolder($client);
                if ($sentFolder !== null) {
                    $client->append($sentFolder, $rfc822);
                }
            } finally {
                $client->logout();
            }
        } catch (\Throwable $exception) {
            logger()->warning('Archivage IMAP du message envoyé impossible : ' . $exception->getMessage());
        }
    }

    private function findSentFolder(ImapClient $client): ?string
    {
        foreach ($client->listFolders() as $folder) {
            $label = MailFolder::labelFor($folder['name']);
            if ($label === 'Éléments envoyés') {
                return $folder['name'];
            }
        }
        return null;
    }

    /**
     * Échappe une valeur de recherche dans une chaîne IMAP quotée.
     */
    private function quoteSearch(string $value): string
    {
        return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
    }

    /**
     * @param array<string,mixed> $row
     * @return array{host:string,port:int,encryption:string,timeout:int}
     */
    private function imapServer(array $row): array
    {
        return [
            'host' => (string) $row['imap_host'],
            'port' => (int) $row['imap_port'],
            'encryption' => (string) $row['imap_encryption'],
            'timeout' => (int) config('mail.mailbox.timeout'),
        ];
    }

    /**
     * @param array<string,mixed> $row
     * @return array{host:string,port:int,encryption:string,timeout:int}
     */
    private function smtpServer(array $row): array
    {
        return [
            'host' => (string) $row['smtp_host'],
            'port' => (int) $row['smtp_port'],
            'encryption' => (string) $row['smtp_encryption'],
            'timeout' => (int) config('mail.mailbox.timeout'),
        ];
    }
}
