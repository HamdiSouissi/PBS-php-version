<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Logger\Logger;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Envoi des e-mails via PHPMailer (SMTP configurable).
 * En l'absence de SMTP joignable, le message est journalisé et l'application
 * continue de fonctionner (mode développement / démonstration).
 */
final class MailerService
{
    public function __construct(private readonly Logger $logger)
    {
    }

    public function send(string $to, string $subject, string $htmlBody, ?string $textBody = null): bool
    {
        $config = config('mail');
        if (!class_exists(PHPMailer::class)) {
            $this->logger->warning('PHPMailer absent, e-mail journalisé', ['to' => $to, 'subject' => $subject]);
            return false;
        }

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = (string) $config['host'];
            $mail->Port = (int) $config['port'];
            $mail->CharSet = PHPMailer::CHARSET_UTF8;
            if ($config['secure'] !== '') {
                $mail->SMTPSecure = $config['secure'];
            }
            if ($config['username'] !== '') {
                $mail->SMTPAuth = true;
                $mail->Username = (string) $config['username'];
                $mail->Password = (string) $config['password'];
            }
            $mail->setFrom((string) $config['from_address'], (string) $config['from_name']);
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = $textBody ?? strip_tags($htmlBody);
            $mail->send();
            return true;
        } catch (\Throwable $exception) {
            $this->logger->error('Échec envoi e-mail : ' . $mail->ErrorInfo . ' | ' . $exception->getMessage(), [
                'to' => $to,
                'subject' => $subject,
            ]);
            return false;
        }
    }
}
