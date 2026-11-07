<?php

declare(strict_types=1);

namespace Modules\Contracts\Services;

/** Invitation HTML autonome, avec styles en ligne compatibles avec les clients mail. */
final class SignatureInvitationEmail
{
    public static function html(string $url, string $clientName, string $reference): string
    {
        $safeUrl = htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeName = htmlspecialchars($clientName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $safeReference = htmlspecialchars($reference, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return <<<HTML
<!doctype html>
<html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Invitation à signer votre contrat</title></head>
<body style="margin:0;padding:0;background-color:#f2f5fa;font-family:Arial,Helvetica,sans-serif;color:#1c2c44;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background-color:#f2f5fa;"><tr><td align="center" style="padding:32px 16px;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="max-width:600px;background-color:#ffffff;border:1px solid #e0e8f1;border-radius:12px;">
<tr><td style="padding:28px 32px;background-color:#123b70;color:#ffffff;border-radius:11px 11px 0 0;">
<p style="margin:0 0 7px;font-size:12px;font-weight:bold;letter-spacing:1.2px;text-transform:uppercase;color:#c8ddf7;">Prime Business Suite</p>
<p style="margin:0;font-size:22px;font-weight:bold;line-height:1.3;">Votre contrat est prêt à être signé</p>
</td></tr>
<tr><td style="padding:30px 32px 12px;font-size:15px;line-height:1.65;">
<p style="margin:0 0 16px;">Bonjour {$safeName},</p>
<p style="margin:0 0 16px;">Le contrat <strong>{$safeReference}</strong> a été signé par le prestataire et vous attend pour signature électronique simple.</p>
<p style="margin:0 0 22px;">Vous pourrez consulter et télécharger le document avant de confirmer votre accord.</p>
<table role="presentation" cellspacing="0" cellpadding="0" border="0"><tr><td align="center" bgcolor="#0b55bd" style="border-radius:6px;"><a href="{$safeUrl}" style="display:inline-block;padding:14px 24px;color:#ffffff;font-size:15px;font-weight:bold;text-decoration:none;">Consulter et signer le contrat</a></td></tr></table>
<p style="margin:24px 0 0;color:#556579;font-size:13px;">Ce lien est personnel et valable 7 jours. Ne le transférez pas. Si vous n’êtes pas le destinataire prévu, contactez le prestataire sans utiliser ce lien.</p>
</td></tr>
<tr><td style="padding:20px 32px 30px;color:#6b798b;font-size:12px;line-height:1.6;">Si le bouton ne fonctionne pas, copiez ce lien dans votre navigateur :<br><a href="{$safeUrl}" style="color:#0b55bd;word-break:break-all;">{$safeUrl}</a></td></tr>
<tr><td style="padding:18px 32px;border-top:1px solid #e5ebf2;color:#718096;font-size:12px;line-height:1.5;">Invitation à la signature électronique simple · Prime Business Suite</td></tr>
</table></td></tr></table></body></html>
HTML;
    }

    public static function text(string $url, string $clientName, string $reference): string
    {
        return "Bonjour {$clientName},\n\nLe contrat {$reference} a été signé par le prestataire et vous attend pour signature électronique simple. Vous pourrez le consulter avant de confirmer votre accord.\n\nConsulter et signer : {$url}\n\nCe lien est personnel, valable 7 jours ; ne le transférez pas. Si vous n’êtes pas le destinataire prévu, contactez le prestataire sans utiliser ce lien.";
    }
}
