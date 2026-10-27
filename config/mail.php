<?php

declare(strict_types=1);

return [
    // Messagerie transactionnelle (réinitialisation de mot de passe, notifications).
    'host' => env('MAIL_HOST', 'localhost'),
    'port' => (int) env('MAIL_PORT', '1025'),
    'secure' => env('MAIL_SECURE', ''),
    'username' => env('MAIL_USERNAME', ''),
    'password' => env('MAIL_PASSWORD', ''),
    'from_address' => env('MAIL_FROM_ADDRESS', 'no-reply@example.fr'),
    'from_name' => env('MAIL_FROM_NAME', 'Prime Business Suite'),
    'debug_log' => storage_path('logs/mail.log'),

    // Boîtes mail d'entreprise accessibles depuis la plateforme (IMAP/SMTP).
    // Valeurs par défaut IONOS, reprises et surchargeables au niveau de chaque
    // compte configuré par l'administrateur.
    'mailbox' => [
        'provider' => 'IONOS',
        'imap' => [
            'host' => env('MAILBOX_IMAP_HOST', 'imap.ionos.fr'),
            'port' => (int) env('MAILBOX_IMAP_PORT', '993'),
            'encryption' => env('MAILBOX_IMAP_ENCRYPTION', 'ssl'), // ssl | tls (STARTTLS) | none
        ],
        'smtp' => [
            'host' => env('MAILBOX_SMTP_HOST', 'smtp.ionos.fr'),
            'port' => (int) env('MAILBOX_SMTP_PORT', '465'),
            'encryption' => env('MAILBOX_SMTP_ENCRYPTION', 'ssl'), // ssl (465) | tls/STARTTLS (587) | none
        ],
        'timeout' => (int) env('MAILBOX_TIMEOUT', '15'),
        'page_size' => (int) env('MAILBOX_PAGE_SIZE', '20'),
        // Taille cumulée maximale des pièces jointes d'un message envoyé (octets).
        'max_attachments_size' => (int) env('MAILBOX_MAX_ATTACHMENTS_SIZE', (string) (10 * 1024 * 1024)),
        'max_recipients' => (int) env('MAILBOX_MAX_RECIPIENTS', '50'),
    ],
];
