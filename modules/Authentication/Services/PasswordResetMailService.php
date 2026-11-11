<?php

declare(strict_types=1);

namespace Modules\Authentication\Services;

use App\Services\MailerService;

/** Prépare et transmet les liens sans jamais journaliser le jeton. */
final class PasswordResetMailService
{
    public function __construct(
        private readonly PasswordResetService $resets,
        private readonly MailerService $mailer
    ) {
    }

    public function sendForLogin(string $email): void
    {
        $email = mb_strtolower(trim($email));
        $token = $this->resets->createResetToken($email);
        if ($token !== '') {
            $this->send($email, $token, false);
        }
    }

    /** @param array<string,mixed> $employee @return array{sent:int,total:int} */
    public function sendForEmployee(int $userId, array $employee): array
    {
        $addresses = [];
        foreach (['personal_email', 'professional_email'] as $key) {
            $email = mb_strtolower(trim((string) ($employee[$key] ?? '')));
            if (filter_var($email, FILTER_VALIDATE_EMAIL) !== false) {
                $addresses[$email] = $email;
            }
        }
        if ($addresses === []) {
            return ['sent' => 0, 'total' => 0];
        }
        $token = $this->resets->createForUser($userId);
        if ($token === '') {
            return ['sent' => 0, 'total' => count($addresses)];
        }
        $sent = 0;
        foreach ($addresses as $email) {
            if ($this->send($email, $token, true)) {
                $sent++;
            }
        }
        return ['sent' => $sent, 'total' => count($addresses)];
    }

    private function send(string $email, string $token, bool $requestedByAdmin): bool
    {
        $url = rtrim((string) config('app.url'), '/') . '/reset-password?token=' . rawurlencode($token);
        $safeUrl = htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $context = $requestedByAdmin
            ? 'Votre administrateur a demandé la mise à jour de votre mot de passe.'
            : 'Nous avons reçu une demande de réinitialisation de votre mot de passe.';
        $html = '<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>'
            . '<body style="margin:0;padding:0;background:#f2f5fa;font-family:Arial,Helvetica,sans-serif;color:#172a46;">'
            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f2f5fa;"><tr><td align="center" style="padding:36px 16px;">'
            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:600px;background:#ffffff;border:1px solid #e2e9f2;border-radius:16px;">'
            . '<tr><td style="padding:30px 36px;background:#102a50;border-radius:15px 15px 0 0;color:#ffffff;">'
            . '<div style="font-size:12px;letter-spacing:2px;font-weight:bold;color:#a9c9f8;">PRIME VISION SOLUTIONS</div>'
            . '<div style="margin-top:10px;font-size:21px;font-weight:bold;">Prime Business Suite</div></td></tr>'
            . '<tr><td style="padding:42px 36px 36px;"><div style="font-size:11px;font-weight:bold;letter-spacing:1.5px;color:#3675c8;">SÉCURITÉ DU COMPTE</div>'
            . '<h1 style="font-size:26px;line-height:1.25;margin:16px 0 20px;color:#102a50;">Votre mot de passe, en toute sécurité</h1>'
            . '<p style="font-size:15px;line-height:1.7;color:#41536d;">Bonjour,<br>' . $context . ' Choisissez un nouveau mot de passe personnel pour accéder à votre espace.</p>'
            . '<table role="presentation" cellspacing="0" cellpadding="0" style="margin:28px 0;"><tr><td style="background:#2867ba;border-radius:8px;">'
            . '<a href="' . $safeUrl . '" style="display:inline-block;padding:15px 24px;color:#ffffff;text-decoration:none;font-size:14px;font-weight:bold;">Créer mon nouveau mot de passe</a></td></tr></table>'
            . '<p style="font-size:13px;line-height:1.7;color:#55667d;">Ce lien est personnel, utilisable une seule fois et valable pendant <strong>1 heure</strong>. Ne le transmettez à personne.</p>'
            . '<p style="font-size:13px;line-height:1.7;color:#55667d;">Si vous n’êtes pas à l’origine de cette demande, ignorez ce message ou contactez votre administrateur. Votre mot de passe reste inchangé.</p>'
            . '<p style="font-size:12px;line-height:1.6;color:#74839a;word-break:break-all;">Si le bouton ne fonctionne pas, copiez ce lien :<br><a style="color:#2867ba;" href="' . $safeUrl . '">' . $safeUrl . '</a></p>'
            . '</td></tr><tr><td style="border-top:1px solid #e8eef5;padding:22px 36px;color:#7a899c;font-size:12px;line-height:1.6;">Prime Business Suite · Notification de sécurité<br>Ce message est envoyé automatiquement. Ne communiquez jamais votre mot de passe.</td></tr>'
            . '</table></td></tr></table></body></html>';
        $text = "Prime Business Suite — Sécurité du compte\n\nBonjour,\n{$context}\n\nCréez votre nouveau mot de passe : {$url}\n\nCe lien est personnel, utilisable une seule fois et valable 1 heure. Si vous n’êtes pas à l’origine de cette demande, ignorez ce message ou contactez votre administrateur.\n";
        return $this->mailer->send($email, 'Prime Business Suite | Réinitialisation de votre mot de passe', $html, $text);
    }
}
