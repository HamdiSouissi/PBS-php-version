<?php

declare(strict_types=1);

namespace Modules\Mail\Controllers;

use App\Core\Exception\AppException;
use App\Core\Exception\NotFoundException;
use App\Core\Exception\ValidationException;
use App\Core\Http\Controller;
use App\Core\Http\RedirectResponse;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Http\UploadedFile;
use Modules\Mail\Models\OutgoingMail;
use Modules\Mail\Services\MailAccountService;
use Modules\Mail\Services\MailboxService;

/**
 * Boîte mail personnelle : toutes les actions sont bornées à l'utilisateur
 * connecté, dont la boîte est résolue depuis la base sans aucune saisie
 * d'identifiants côté écran.
 */
final class MailboxController extends Controller
{
    public function __construct(
        private readonly MailboxService $mailbox,
        private readonly MailAccountService $accounts
    ) {
    }

    public function index(Request $request): Response
    {
        $userId = (int) $this->auth()->id();
        $folder = $this->folderFromQuery($request);
        $query = trim((string) $request->query('q', ''));
        $page = max(1, (int) $request->query('page', 1));

        $folders = [];
        $result = ['messages' => [], 'total' => 0, 'page' => 1, 'pages' => 1, 'folder' => $folder];
        try {
            $folders = $this->mailbox->folders($userId);
            $result = $this->mailbox->messages($userId, $folder, $page, $query !== '' ? $query : null);
        } catch (AppException $exception) {
            flash('danger', $exception->getMessage());
        }

        return $this->view('Mail::mailbox/index', [
            'title' => 'Boîte mail',
            'folders' => $folders,
            'result' => $result,
            'folder' => $folder,
            'query' => $query,
            'page' => $page,
            'hasMailbox' => $this->accounts->activeModelForUser($userId) !== null,
        ]);
    }

    public function show(Request $request): Response
    {
        $userId = (int) $this->auth()->id();
        $folder = $this->folderFromQuery($request);
        $id = (int) $request->query('id', 0);

        try {
            $message = $this->mailbox->message($userId, $folder, $id);
            $folders = $this->mailbox->folders($userId);
        } catch (NotFoundException $exception) {
            flash('warning', $exception->getMessage());
            return new RedirectResponse('/mail?folder=' . urlencode($folder));
        } catch (AppException $exception) {
            flash('danger', $exception->getMessage());
            return new RedirectResponse('/mail?folder=' . urlencode($folder));
        }

        return $this->view('Mail::mailbox/show', [
            'title' => $message->subject,
            'message' => $message,
            'folders' => $folders,
            'folder' => $folder,
        ]);
    }

    public function compose(Request $request): Response
    {
        return $this->view('Mail::mailbox/compose', [
            'title' => 'Nouveau message',
            'prefillTo' => (string) $request->query('to', ''),
            'prefillSubject' => (string) $request->query('subject', ''),
            'maxSizeMb' => round((int) config('mail.mailbox.max_attachments_size') / 1048576, 1),
            'maxRecipients' => (int) config('mail.mailbox.max_recipients'),
        ]);
    }

    public function send(Request $request): Response
    {
        try {
            $input = [
                'to' => (string) $request->input('to', ''),
                'cc' => (string) $request->input('cc', ''),
                'bcc' => (string) $request->input('bcc', ''),
                'subject' => (string) $request->input('subject', ''),
                'text_body' => (string) $request->input('text_body', ''),
                'attachments' => $this->attachmentsFromUpload(),
            ];
            $mail = OutgoingMail::fromInput($input);
            $this->mailbox->send((int) $this->auth()->id(), $mail);
            flash('success', 'Message envoyé.');
            return new RedirectResponse('/mail');
        } catch (ValidationException $exception) {
            return (new RedirectResponse('/mail/compose'))
                ->withErrors($exception->errors())
                ->withInput($request->only(['to', 'cc', 'bcc', 'subject', 'text_body']));
        } catch (AppException $exception) {
            flash('danger', $exception->getMessage());
            return (new RedirectResponse('/mail/compose'))
                ->withInput($request->only(['to', 'cc', 'bcc', 'subject', 'text_body']));
        }
    }

    public function mark(Request $request): Response
    {
        $folder = (string) $request->input('folder', 'INBOX');
        $this->mailbox->markRead(
            (int) $this->auth()->id(),
            $folder,
            (int) $request->input('id', 0),
            ((string) $request->input('read', '1')) === '1'
        );
        return new RedirectResponse('/mail?folder=' . urlencode($folder));
    }

    public function flag(Request $request): Response
    {
        $folder = (string) $request->input('folder', 'INBOX');
        $this->mailbox->flag(
            (int) $this->auth()->id(),
            $folder,
            (int) $request->input('id', 0),
            ((string) $request->input('flagged', '1')) === '1'
        );
        return new RedirectResponse('/mail?folder=' . urlencode($folder));
    }

    public function delete(Request $request): Response
    {
        $folder = (string) $request->input('folder', 'INBOX');
        $this->mailbox->delete((int) $this->auth()->id(), $folder, (int) $request->input('id', 0));
        flash('success', 'Message supprimé.');
        return new RedirectResponse('/mail?folder=' . urlencode($folder));
    }

    public function move(Request $request): Response
    {
        $folder = (string) $request->input('folder', 'INBOX');
        try {
            $this->mailbox->move(
                (int) $this->auth()->id(),
                $folder,
                (int) $request->input('id', 0),
                (string) $request->input('destination', '')
            );
            flash('success', 'Message déplacé.');
        } catch (AppException $exception) {
            flash('danger', $exception->getMessage());
        }
        return new RedirectResponse('/mail?folder=' . urlencode($folder));
    }

    public function attachment(Request $request): Response
    {
        $folder = $this->folderFromQuery($request);
        $attachment = $this->mailbox->attachment(
            (int) $this->auth()->id(),
            $folder,
            (int) $request->query('id', 0),
            (int) $request->query('pos', 0)
        );
        $filename = preg_replace('/[^\w.\- ]+/u', '_', $attachment->filename) ?: 'piece-jointe';
        return new Response($attachment->contents, 200, [
            'Content-Type' => $attachment->mimeType,
            'Content-Length' => (string) $attachment->size,
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function folderFromQuery(Request $request): string
    {
        $folder = trim((string) $request->query('folder', 'INBOX'));
        return $folder !== '' ? $folder : 'INBOX';
    }

    /**
     * Normalise les fichiers du champ multiple « attachments » en entrées
     * validées par UploadedFile (is_uploaded_file, erreurs de téléversement).
     *
     * @return list<array{filename:string,mime_type:string,contents:string}>
     */
    private function attachmentsFromUpload(): array
    {
        if (empty($_FILES['attachments']) || !is_array($_FILES['attachments']['name'])) {
            return [];
        }
        $items = [];
        $count = count($_FILES['attachments']['name']);
        for ($i = 0; $i < $count; $i++) {
            if (($_FILES['attachments']['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $file = new UploadedFile([
                'name' => $_FILES['attachments']['name'][$i],
                'type' => $_FILES['attachments']['type'][$i],
                'tmp_name' => $_FILES['attachments']['tmp_name'][$i],
                'error' => $_FILES['attachments']['error'][$i],
                'size' => $_FILES['attachments']['size'][$i],
            ]);
            $items[] = [
                'filename' => $file->originalName(),
                'mime_type' => $file->mime() !== '' ? $file->mime() : 'application/octet-stream',
                'contents' => $file->contents(),
            ];
        }
        return $items;
    }
}
