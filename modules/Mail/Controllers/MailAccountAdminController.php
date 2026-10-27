<?php

declare(strict_types=1);

namespace Modules\Mail\Controllers;

use App\Core\Database\Connection;
use App\Core\Exception\AppException;
use App\Core\Exception\NotFoundException;
use App\Core\Exception\ValidationException;
use App\Core\Http\Controller;
use App\Core\Http\RedirectResponse;
use App\Core\Http\Request;
use App\Core\Http\Response;
use Modules\Mail\Services\MailAccountService;

/**
 * Administration des boîtes mail, réservée au gestionnaire de l'application
 * (permission mail.configure). Les mots de passe ne sont jamais renvoyés aux
 * vues : un champ vide à la mise à jour conserve le secret existant.
 */
final class MailAccountAdminController extends Controller
{
    /** Champs de formulaire conservés en ré-affichage, hors mots de passe. */
    private const SAFE_FIELDS = [
        'user_id', 'email', 'display_name',
        'imap_host', 'imap_port', 'imap_encryption', 'imap_username',
        'smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_username', 'is_active',
    ];

    public function __construct(
        private readonly MailAccountService $accounts,
        private readonly Connection $db
    ) {
    }

    public function index(): Response
    {
        $this->auth()->requirePermission('mail.configure');
        return $this->view('Mail::admin/accounts/index', [
            'title' => 'Comptes mail',
            'accounts' => $this->accounts->adminList(),
        ]);
    }

    public function create(): Response
    {
        $this->auth()->requirePermission('mail.configure');
        return $this->view('Mail::admin/accounts/form', [
            'title' => 'Nouvelle boîte mail',
            'account' => null,
            'availableUsers' => $this->availableUsers(),
            'defaults' => config('mail.mailbox'),
            'encryptions' => MailAccountService::ENCRYPTIONS,
        ]);
    }

    public function store(Request $request): Response
    {
        $this->auth()->requirePermission('mail.configure');
        try {
            $this->accounts->create($this->formData($request), (int) $this->auth()->id());
            flash('success', 'Boîte mail créée et affectée.');
            return new RedirectResponse('/mail/admin/accounts');
        } catch (ValidationException | AppException $exception) {
            return $this->formFailure($exception, '/mail/admin/accounts/new');
        }
    }

    public function edit(Request $request, int $id): Response
    {
        $this->auth()->requirePermission('mail.configure');
        try {
            $account = $this->accounts->requireRow($id);
        } catch (NotFoundException $exception) {
            flash('warning', $exception->getMessage());
            return new RedirectResponse('/mail/admin/accounts');
        }
        return $this->view('Mail::admin/accounts/form', [
            'title' => 'Modifier la boîte mail',
            'account' => $account,
            'availableUsers' => $this->availableUsers((int) $account['user_id']),
            'defaults' => config('mail.mailbox'),
            'encryptions' => MailAccountService::ENCRYPTIONS,
        ]);
    }

    public function update(Request $request, int $id): Response
    {
        $this->auth()->requirePermission('mail.configure');
        try {
            $this->accounts->update($id, $this->formData($request), (int) $this->auth()->id());
            flash('success', 'Boîte mail mise à jour.');
            return new RedirectResponse('/mail/admin/accounts');
        } catch (ValidationException | AppException $exception) {
            return $this->formFailure($exception, '/mail/admin/accounts/' . $id . '/edit');
        }
    }

    public function destroy(Request $request, int $id): Response
    {
        $this->auth()->requirePermission('mail.configure');
        try {
            $this->accounts->delete($id, (int) $this->auth()->id());
            flash('success', 'Boîte mail révoquée.');
        } catch (AppException $exception) {
            flash('danger', $exception->getMessage());
        }
        return new RedirectResponse('/mail/admin/accounts');
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function availableUsers(int $includeUserId = 0): array
    {
        return $this->db->fetchAll(
            'SELECT u.id, u.email, u.first_name, u.last_name
             FROM users u
             WHERE u.is_active = 1
               AND (u.id = :include OR u.id NOT IN (SELECT user_id FROM mail_accounts))
             ORDER BY u.last_name, u.first_name',
            ['include' => $includeUserId]
        );
    }

    /**
     * @return array<string,mixed>
     */
    private function formData(Request $request): array
    {
        $data = $request->only(self::SAFE_FIELDS);
        $data['is_active'] = $request->input('is_active') !== null ? 1 : 0;
        $data['imap_password'] = (string) $request->input('imap_password', '');
        $data['smtp_password'] = (string) $request->input('smtp_password', '');
        return $data;
    }

    private function formFailure(AppException $exception, string $target): RedirectResponse
    {
        $errors = $exception instanceof ValidationException
            ? $exception->errors()
            : ['form' => $exception->getMessage()];
        // Les mots de passe ne sont jamais conservés en session, même en cas d'erreur.
        $safeInput = array_diff_key($_POST, array_flip(['imap_password', 'smtp_password', '_csrf']));
        return (new RedirectResponse($target))->withErrors($errors)->withInput($safeInput);
    }
}
