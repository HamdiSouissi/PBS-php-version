<?php

declare(strict_types=1);

namespace Modules\Authentication\Controllers;

use App\Core\Http\Controller;
use App\Core\Http\RedirectResponse;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Session\FlashBag;
use Modules\Authentication\Services\AuthService;
use Modules\Authentication\Services\PasswordResetService;

/**
 * Connexion, déconnexion, double authentification et mot de passe oublié (web).
 */
final class AuthController extends Controller
{
    private const MAX_2FA_ATTEMPTS = 5;

    public function __construct(
        private readonly AuthService $authService,
        private readonly PasswordResetService $resetService,
        private readonly FlashBag $flash
    ) {
    }

    public function showLogin(Request $request): Response
    {
        return $this->view('Authentication::auth/login', [
            'title' => 'Connexion',
        ], 'layouts/minimal');
    }

    public function login(Request $request): Response
    {
        $data = $this->validator()->validate($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ], ['email' => 'adresse e-mail', 'password' => 'mot de passe']);

        try {
            $user = $this->authService->attempt((string) $data['email'], (string) $data['password'], $request->ip());
        } catch (\Throwable $exception) {
            flash('danger', $exception->getMessage());
            return (new RedirectResponse('/login'))->withInput($data);
        }

        $remember = (bool) $request->input('remember', false);

        if ($user->totpEnrolled) {
            $this->authService->prepareTwoFactor($user);
            $_SESSION['2fa_remember'] = $remember;
            $_SESSION['2fa_attempts'] = 0;
            return new RedirectResponse('/two-factor');
        }

        $this->authService->login($user, $remember, $request->ip(), $request->userAgent());
        flash('success', 'Bienvenue, ' . $user->fullName() . '.');
        return new RedirectResponse('/dashboard');
    }

    public function showTwoFactor(Request $request): Response
    {
        // CORRECTIF : l'ancienne condition « $this->auth()->guest() » est toujours vraie
        // pendant la 2FA (l'utilisateur n'est pas encore connecté) : la page
        // /two-factor redirigeait systématiquement vers /login.
        if (session_status() !== PHP_SESSION_ACTIVE || !isset($_SESSION['2fa_pending'])) {
            return new RedirectResponse('/login');
        }
        return $this->view('Authentication::auth/two-factor', ['title' => 'Double authentification'], 'layouts/minimal');
    }

    public function verifyTwoFactor(Request $request): Response
    {
        $pendingId = $_SESSION['2fa_pending'] ?? null;
        if ($pendingId === null) {
            return new RedirectResponse('/login');
        }
        $user = \App\Core\Application::container()
            ->get(\Modules\Authentication\Repositories\UserRepository::class)
            ->findWithRights((int) $pendingId);
        if ($user === null || !$this->authService->validateTwoFactor($user, (string) $request->input('code', ''))) {
            // Protection contre l'énumération des codes TOTP.
            $_SESSION['2fa_attempts'] = (int) ($_SESSION['2fa_attempts'] ?? 0) + 1;
            if ($_SESSION['2fa_attempts'] >= self::MAX_2FA_ATTEMPTS) {
                unset($_SESSION['2fa_pending'], $_SESSION['2fa_attempts'], $_SESSION['2fa_remember']);
                flash('danger', 'Trop de codes invalides. Reconnectez-vous.');
                return new RedirectResponse('/login');
            }
            flash('danger', 'Code TOTP invalide.');
            return new RedirectResponse('/two-factor');
        }
        $remember = (bool) ($_SESSION['2fa_remember'] ?? false);
        $this->authService->login($user, $remember, $request->ip(), $request->userAgent());
        unset($_SESSION['2fa_attempts'], $_SESSION['2fa_remember']);
        flash('success', 'Double authentification validée.');
        return new RedirectResponse('/dashboard');
    }

    public function logout(Request $request): Response
    {
        $this->authService->logout();
        flash('info', 'Vous êtes déconnecté.');
        return new RedirectResponse('/login');
    }

    public function showForgot(Request $request): Response
    {
        return $this->view('Authentication::auth/forgot', ['title' => 'Mot de passe oublié'], 'layouts/minimal');
    }

    public function sendResetLink(Request $request): Response
    {
        $data = $this->validator()->validate($request->all(), ['email' => 'required|email']);
        $token = $this->resetService->createResetToken((string) $data['email']);
        if ($token !== '') {
            $link = config('app.url') . '/reset-password?token=' . $token;
            \App\Core\Application::container()->get(\App\Services\MailerService::class)->send(
                (string) $data['email'],
                'Réinitialisation de votre mot de passe',
                '<p>Cliquez sur ce lien (valable 1 heure) :</p><p><a href="' . htmlspecialchars($link, ENT_QUOTES) . '">'
                . htmlspecialchars($link, ENT_QUOTES) . '</a></p>'
            );
        }
        // Message identique pour ne pas énumérer les comptes.
        flash('info', 'Si cette adresse existe, un e-mail de réinitialisation vient d\'être envoyé.');
        return new RedirectResponse('/login');
    }

    public function showReset(Request $request): Response
    {
        return $this->view('Authentication::auth/reset', [
            'title' => 'Nouveau mot de passe',
            'token' => (string) $request->query('token', ''),
        ], 'layouts/minimal');
    }

    public function resetPassword(Request $request): Response
    {
        $data = $this->validator()->validate($request->all(), [
            'token' => 'required|string',
            'password' => 'required|min:12|confirmed',
        ]);
        $this->resetService->resetWithToken((string) $data['token'], (string) $data['password']);
        flash('success', 'Mot de passe mis à jour. Connectez-vous.');
        return new RedirectResponse('/login');
    }
}
