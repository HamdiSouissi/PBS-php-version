<?php

declare(strict_types=1);

namespace Database\Seeds;

use App\Core\Container;
use App\Core\Database\Connection;

/**
 * Données de référence transversales : types de congés, exercice courant,
 * obligations de conformité, paramètres IA par défaut (désactivés).
 */
final class ReferenceSeeder
{
    public function __construct(private readonly Container $container)
    {
    }

    public function run(): void
    {
        $db = $this->container->get(Connection::class);

        $leaveTypes = [
            ['CP', 'Congés payés', true, '#0d6efd', 25],
            ['RTT', 'RTT', true, '#6f42c1', 10],
            ['MAL', 'Arrêt maladie', false, '#dc3545', 0],
            ['SS', 'Congé sans solde', false, '#6c757d', 0],
            ['FAM', 'Congé pour événement familial', true, '#fd7e14', 0],
            ['FORM', 'Congé formation', false, '#20c997', 0],
            ['MAT', 'Congé maternité / paternité', true, '#d63384', 0],
        ];
        foreach ($leaveTypes as [$code, $label, $paid, $color, $days]) {
            $db->execute(
                'INSERT INTO leave_types (code, label, is_paid, color, default_days)
                 VALUES (:c, :l, :p, :col, :d) ON CONFLICT (code) DO NOTHING',
                ['c' => $code, 'l' => $label, 'p' => $paid, 'col' => $color, 'd' => $days]
            );
        }

        // Exercice fiscal courant (année civile).
        $year = (int) date('Y');
        $db->execute(
            'INSERT INTO fiscal_years (label, starts_on, ends_on, status)
             VALUES (:l, :s, :e, \'open\') ON CONFLICT DO NOTHING',
            [
                'l' => "Exercice {$year}",
                's' => "{$year}-01-01",
                'e' => "{$year}-12-31",
            ]
        );

        $obligations = [
            ['DSN_MENSUELLE', 'DSN mensuelle (déclaration sociale nominative)', 'URSSAF', 'monthly', 'Mensuel, le 5 ou 15 selon échéancier'],
            ['TVA', 'Déclaration et paiement de la TVA', 'DGFIP', 'monthly', 'Mensuelle (ou trimestrielle selon régime)'],
            ['URSSAF_PAIE', 'Paiement des cotisations URSSAF', 'URSSAF', 'monthly', 'Échéance mensuelle DSN'],
            ['LIASSE_FISCALE', 'Liasse fiscale et déclaration de résultat', 'DGFIP', 'yearly', 'Dans les 3 mois suivant la clôture'],
            ['CFE', 'Cotisation foncière des entreprises', 'DGFIP', 'yearly', 'Déclaration annuelle puis avis d\'impôt'],
            ['CVAE', 'CVAE (entreprises concernées)', 'DGFIP', 'yearly', 'Déclaration de liquidation'],
            ['AG_ORDINAIRE', 'Assemblée générale ordinaire des associés', 'greffe', 'yearly', 'Dans les 6 mois après clôture'],
            ['DEPOT_COMPTES', 'Dépôt des comptes annuels au greffe', 'greffe', 'yearly', '2 mois après l\'AG'],
            ['MEDECINE_TRAVAIL', 'Visite d\'information et de prévention', 'medecine_travail', 'event', 'À l\'embauche puis périodique'],
            ['RGPD_REGISTRE', 'Tenue du registre des traitements de données', 'interne', 'continuous', 'Mise à jour continue'],
            ['DUERP', 'Document unique d\'évaluation des risques professionnels', 'interne', 'yearly', 'Mise à jour annuelle obligatoire'],
        ];
        foreach ($obligations as [$code, $title, $authority, $frequency, $note]) {
            $db->execute(
                'INSERT INTO compliance_obligations (code, title, authority, frequency, period_label, notes, status)
                 VALUES (:c, :t, :a, :f, :p, :n, \'pending\') ON CONFLICT (code) DO NOTHING',
                ['c' => $code, 't' => $title, 'a' => $authority, 'f' => $frequency, 'p' => '', 'n' => $note]
            );
        }

        // Paramètres IA par défaut : tout désactivé, l'app reste pleinement utilisable.
        $db->execute(
            'INSERT INTO ai_provider_settings (provider, is_enabled, url, model, temperature, timeout, system_prompt)
             VALUES (\'ollama\', FALSE, :u, \'llama3.1\', 0.70, 60, :prompt)
             ON CONFLICT (provider) DO NOTHING',
            [
                'u' => 'http://127.0.0.1:11434',
                'prompt' => 'Tu es un assistant de gestion d\'entreprise. Réponds de façon concise et en français.',
            ]
        );
        $db->execute(
            'INSERT INTO ai_provider_settings (provider, is_enabled, url, model, temperature, timeout, system_prompt)
             VALUES (\'codecraft\', FALSE, :u, \'\', 0.70, 30, :prompt)
             ON CONFLICT (provider) DO NOTHING',
            [
                'u' => 'https://codecraftapi.com/v1',
                'prompt' => 'Tu es un assistant de gestion d\'entreprise. Réponds de façon concise et en français.',
            ]
        );
    }
}
