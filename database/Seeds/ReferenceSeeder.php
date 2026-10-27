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
            $db->insertIgnore(
                'leave_types',
                ['code' => $code, 'label' => $label, 'is_paid' => $paid, 'color' => $color, 'default_days' => $days],
                ['code']
            );
        }

        // Exercice fiscal courant (année civile).
        $year = (int) date('Y');
        $db->insertIgnore(
            'fiscal_years',
            [
                'label' => "Exercice {$year}",
                'starts_on' => "{$year}-01-01",
                'ends_on' => "{$year}-12-31",
                'status' => 'open',
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
            $db->insertIgnore(
                'compliance_obligations',
                [
                    'code' => $code,
                    'title' => $title,
                    'authority' => $authority,
                    'frequency' => $frequency,
                    'period_label' => '',
                    'notes' => $note,
                    'status' => 'pending',
                ],
                ['code']
            );
        }

        // Paramètres IA par défaut : tout désactivé, l'app reste pleinement utilisable.
        $db->insertIgnore(
            'ai_provider_settings',
            [
                'provider' => 'ollama',
                'is_enabled' => false,
                'url' => 'http://127.0.0.1:11434',
                'model' => 'llama3.1',
                'temperature' => 0.70,
                'timeout' => 60,
                'system_prompt' => 'Tu es un assistant de gestion d\'entreprise. Réponds de façon concise et en français.',
                'extra' => '{}',
            ],
            ['provider']
        );
        $db->insertIgnore(
            'ai_provider_settings',
            [
                'provider' => 'codecraft',
                'is_enabled' => false,
                'url' => 'https://codecraftapi.com/v1',
                'model' => '',
                'temperature' => 0.70,
                'timeout' => 30,
                'system_prompt' => 'Tu es un assistant de gestion d\'entreprise. Réponds de façon concise et en français.',
                'extra' => '{}',
            ],
            ['provider']
        );
    }
}
