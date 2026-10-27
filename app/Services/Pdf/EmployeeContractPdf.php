<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use App\Core\Database\Connection;
use App\Core\Exception\NotFoundException;
use App\Core\Pdf\PdfDocument;

/**
 * Contrat de travail PDF (CDI, CDD, apprentissage, stage…). Le document
 * reprend les clauses légales essentielles et les données réelles du
 * salarié et de son contrat.
 */
final class EmployeeContractPdf implements PdfTemplate
{
    public function __construct(private readonly Connection $db)
    {
    }

    public function render(PdfDocument $pdf, int $entityId): string
    {
        $contract = $this->db->fetchOne(
            'SELECT c.*, e.employee_number, e.first_name, e.last_name, e.birth_date, e.birth_place,
                    e.personal_email, e.address AS employee_address, e.postal_code AS employee_pc,
                    e.city AS employee_city, e.social_security_number, e.job_title, co.legal_name,
                    co.address AS company_address, co.postal_code AS company_pc, co.city AS company_city,
                    co.siret AS company_siret, co.collective_agreement, co.iban AS company_iban
             FROM employment_contracts c
             JOIN employees e ON e.id = c.employee_id
             JOIN companies co ON co.id = e.company_id
             WHERE c.id = :id',
            ['id' => $entityId]
        );
        if ($contract === null) {
            throw new NotFoundException('Contrat de travail introuvable.');
        }

        $typeLabel = $this->typeLabel((string) $contract['type']);
        $title = 'Contrat ' . mb_strtolower($typeLabel) . ' — ' . $contract['last_name'] . ' ' . $contract['first_name'];
        $pdf->setDocumentTitle($title)->addPage();
        $pdf->h1('CONTRAT DE TRAVAIL — ' . mb_strtoupper($typeLabel));

        $employeeName = $contract['first_name'] . ' ' . mb_strtoupper((string) $contract['last_name']);
        $companyName = (string) $contract['legal_name'];
        $start = date('d/m/Y', strtotime((string) $contract['start_date']));
        $end = $contract['end_date'] ? date('d/m/Y', strtotime((string) $contract['end_date'])) : null;

        $pdf->h2('1. Parties');
        $pdf->paragraph(
            "Le présent contrat est conclu entre :\n"
            . "L'employeur : {$companyName}, " . $this->address($contract, 'company')
            . ", SIRET {$contract['company_siret']},\n"
            . "Ci-après « l'Employeur », d'une part,\n\n"
            . "Et : {$employeeName}" . ($contract['birth_date'] ? ', né(e) le ' . date('d/m/Y', strtotime((string) $contract['birth_date'])) : '')
            . ($contract['birth_place'] ? ' à ' . $contract['birth_place'] : '')
            . ", demeurant " . $this->address($contract, 'employee')
            . ",\nCi-après « le Salarié », d'autre part."
        );

        $pdf->h2('2. Objet et nature du contrat');
        $nature = $contract['type'] === 'CDI'
            ? "Il est conclu un contrat de travail à durée indéterminée."
            : "Il est conclu un contrat de travail à durée déterminée prenant effet le {$start}"
            . ($end ? " et prenant fin le {$end}." : '.');
        $pdf->paragraph($nature);
        if ($contract['type'] !== 'CDI') {
            $pdf->paragraph("Motif et conditions de rupture : application des dispositions légales et conventionnelles propres aux contrats {$typeLabel}.");
        }

        $pdf->h2('3. Fonction et classification');
        $pdf->keyValueTable([
            ['Poste', (string) ($contract['position'] ?: $contract['job_title'] ?: '—')],
            ['Qualification', (string) ($contract['qualification'] ?: '—')],
            ['Classification', (string) ($contract['classification'] ?: '—')],
            ['Coefficient', (string) ($contract['coefficient'] ?: '—')],
            ['Convention collective', (string) ($contract['collective_agreement'] ?: '—')],
        ]);

        $pdf->h2('4. Durée et temps de travail');
        $partTime = (int) $contract['is_part_time'] === 1;
        $pdf->keyValueTable([
            ['Date de prise d\'effet', $start],
            ['Date de fin', $end ?? 'Indéterminée'],
            ['Durée hebdomadaire', rtrim(rtrim(number_format((float) $contract['weekly_hours'], 2, ',', ' '), '0'), ',') . ' heures'],
            ['Temps partiel', $partTime ? 'Oui (' . number_format((float) $contract['part_time_pct'], 1, ',', ' ') . ' %)' : 'Non (temps plein)'],
            ['Période d\'essai', $contract['trial_period_end'] ? 'Jusqu\'au ' . date('d/m/Y', strtotime((string) $contract['trial_period_end'])) : 'Non prévue'],
        ]);

        $pdf->h2('5. Rémunération');
        $pdf->keyValueTable([
            ['Salaire brut mensuel', money_format((float) $contract['monthly_gross'])],
            ['Taux horaire brut', money_format((float) $contract['hourly_rate']) . ' / heure'],
            ['Paiement', 'Par virement bancaire, le mois échu, sur le compte désigné par le Salarié.'],
            ['IBAN employeur', (string) ($contract['company_iban'] ?: '—')],
        ]);
        $pdf->paragraph('La rémunération évolue selon les dispositions légales et conventionnelles applicables. Les cotisations sociales obligatoires sont retenues dans les conditions prévues par la réglementation.');

        $pdf->h2('6. Obligations des parties');
        $pdf->paragraph(
            "Le Salarié s'engage à exercer sa fonction avec diligence et loyauté, à respecter le règlement intérieur "
            . "et les règles de confidentialité de l'entreprise. L'Employeur s'engage à fournir le travail convenu, "
            . "à verser la rémunération et à souscrire les déclarations sociales obligatoires (DSN, URSSAF, mutuelle)."
        );

        $pdf->h2('7. Signatures');
        $pdf->paragraph('Fait en deux exemplaires originaux remis à chacune des parties.');
        $pdf->signatureBlock("Pour l'Employeur\n{$companyName}\nLe " . date('d/m/Y'), "Le Salarié\n{$employeeName}\n(mention manuscrite « Lu et approuvé »)");

        return $title;
    }

    private function typeLabel(string $type): string
    {
        return match ($type) {
            'CDI' => 'CDI',
            'CDD' => 'CDD',
            'apprentissage' => "Contrat d'apprentissage",
            'professionnalisation' => 'Contrat de professionnalisation',
            'stage' => 'Convention de stage',
            'alternance' => "Contrat d'alternance",
            'interim' => 'Contrat de mission intérimaire',
            default => $type,
        };
    }

    /** @param array<string,mixed> $row */
    private function address(array $row, string $prefix): string
    {
        return implode(', ', array_filter([
            $row[$prefix . '_address'],
            trim(($row[$prefix . '_pc'] ?? '') . ' ' . ($row[$prefix . '_city'] ?? '')),
        ]));
    }
}
