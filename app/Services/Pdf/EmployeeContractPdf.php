<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use App\Core\Database\Connection;
use App\Core\Exception\NotFoundException;
use App\Core\Pdf\PdfDocument;

/**
 * Contrat de travail PDF (CDI, CDD, apprentissage, stage…). Le document
 * reprend les clauses essentielles du droit du travail français et les
 * données réelles du salarié et de son contrat.
 *
 * Il s'agit d'un modèle type : une mention visible oblige à une relecture
 * juridique avant toute signature.
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

        $pdf->noticeBox(
            "DOCUMENT TYPE — RELECTURE JURIDIQUE OBLIGATOIRE.\n"
            . "Ce contrat est généré à partir des données enregistrées dans l'application. "
            . "Il doit être relu, vérifié et adapté par un juriste ou un conseil en droit social "
            . "avant toute signature. Il ne se substitue pas aux conventions collective et d'entreprise applicables."
        );

        $employeeName = $contract['first_name'] . ' ' . mb_strtoupper((string) $contract['last_name']);
        $companyName = (string) $contract['legal_name'];
        $start = date('d/m/Y', strtotime((string) $contract['start_date']));
        $end = $contract['end_date'] ? date('d/m/Y', strtotime((string) $contract['end_date'])) : null;

        $pdf->article('1', 'Parties',
            "Le présent contrat est conclu entre :\n"
            . "L'Employeur : {$companyName}, " . $this->address($contract, 'company')
            . ", SIRET {$contract['company_siret']},\n"
            . "Ci-après « l'Employeur », d'une part,\n\n"
            . "Et : {$employeeName}" . ($contract['birth_date'] ? ', né(e) le ' . date('d/m/Y', strtotime((string) $contract['birth_date'])) : '')
            . ($contract['birth_place'] ? ' à ' . $contract['birth_place'] : '')
            . ", demeurant " . $this->address($contract, 'employee')
            . ",\nCi-après « le Salarié », d'autre part."
        );

        $nature = $contract['type'] === 'CDI'
            ? "Il est conclu un contrat de travail à durée indéterminée régi par le Code du travail et la convention collective applicable."
            : "Il est conclu un contrat de travail à durée déterminée prenant effet le {$start}"
            . ($end ? " et prenant fin le {$end}." : '.')
            . " Les dispositions légales et conventionnelles propres aux contrats {$typeLabel} s'appliquent, "
            . "notamment s'agissant du motif de recours, de la rupture anticipée et de l'indemnité de fin de contrat.";
        $pdf->article('2', 'Objet et nature du contrat', $nature);

        $pdf->article('3', 'Fonction et classification',
            'Le Salarié occupe les fonctions décrites ci-dessous, correspondant à la classification '
            . 'de la convention collective applicable.');
        $pdf->keyValueTable([
            ['Poste', (string) ($contract['position'] ?: $contract['job_title'] ?: '—')],
            ['Qualification', (string) ($contract['qualification'] ?: '—')],
            ['Classification', (string) ($contract['classification'] ?: '—')],
            ['Coefficient', (string) ($contract['coefficient'] ?: '—')],
            ['Convention collective', (string) ($contract['collective_agreement'] ?: '—')],
        ]);

        $partTime = (int) $contract['is_part_time'] === 1;
        $pdf->article('4', 'Durée, essai et temps de travail',
            "Les dates d'effet, la durée du travail et la période d'essai sont les suivantes. "
            . "Le renouvellement éventuel de la période d'essai n'est possible que dans les conditions "
            . "prévues par la loi et la convention collective.");
        $pdf->keyValueTable([
            ['Date de prise d\'effet', $start],
            ['Date de fin', $end ?? 'Indéterminée (CDI)'],
            ['Durée hebdomadaire', rtrim(rtrim(number_format((float) $contract['weekly_hours'], 2, ',', ' '), '0'), ',') . ' heures'],
            ['Temps partiel', $partTime ? 'Oui (' . number_format((float) $contract['part_time_pct'], 1, ',', ' ') . ' %)' : 'Non (temps plein)'],
            ['Période d\'essai', $contract['trial_period_end'] ? 'Jusqu\'au ' . date('d/m/Y', strtotime((string) $contract['trial_period_end'])) : 'Non prévue'],
        ]);

        $pdf->article('5', 'Rémunération et modalités de paiement',
            "En contrepartie de son travail, le Salarié perçoit la rémunération ci-dessous, versée "
            . "par virement bancaire le mois échu, sur le compte désigné par le Salarié. Le bulletin de paie "
            . "est remis à chaque échéance. Tout retard de paiement du salaire ouvre droit aux intérêts "
            . "prévus par l'article L. 3242-2 du Code du travail, sans qu'une mise en demeure soit nécessaire.");
        $pdf->keyValueTable([
            ['Salaire brut mensuel', money_format((float) $contract['monthly_gross'])],
            ['Taux horaire brut', money_format((float) $contract['hourly_rate']) . ' / heure'],
            ['Paiement', 'Par virement bancaire, le mois échu'],
            ['IBAN employeur', (string) ($contract['company_iban'] ?: '—')],
        ]);
        $pdf->paragraph('La rémunération évolue selon les dispositions légales et conventionnelles applicables. Les cotisations sociales obligatoires sont retenues dans les conditions prévues par la réglementation.');

        $pdf->article('6', 'Obligations des parties',
            "Le Salarié s'engage à exercer sa fonction avec diligence et loyauté, à respecter le règlement "
            . "intérieur, les consignes de sécurité et les règles de l'entreprise. L'Employeur s'engage à "
            . "fournir le travail convenu, à verser la rémunération et à souscrire les déclarations sociales "
            . "obligatoires (DSN, URSSAF, mutuelle) ainsi qu'à garantir la santé et la sécurité au travail.");

        $pdf->article('7', 'Protection sociale et cotisations',
            "Le Salarié bénéficie du régime de sécurité sociale français. Les cotisations salariales et "
            . "patronales sont précomptées et déclarées conformément à la réglementation en vigueur. "
            . "Les garanties de prévoyance et de frais de santé applicables sont celles prévues par les "
            . "accords collectifs et les contrats souscrits par l'Employeur.");

        $pdf->article('8', 'Propriété intellectuelle',
            "Toutes les créations, logiciels, documentations, études et œuvres réalisés par le Salarié "
            . "dans le cadre de ses fonctions ou avec les moyens de l'entreprise appartiennent à l'Employeur, "
            . "dans les conditions des articles L. 113-9 du Code de la propriété intellectuelle et du régime "
            . "des inventions de salarié (livre VI du même code). Le Salarié signe, à première demande, tout "
            . "acte confirmant cette titularité.");

        $pdf->article('9', 'Confidentialité',
            "Le Salarié tient pour strictement confidentielles toutes les informations commerciales, "
            . "techniques, financières et organisationnelles dont il a connaissance à l'occasion de ses "
            . "fonctions. Cette obligation se poursuit après la fin du contrat, sans limitation de durée "
            . "pour les secrets d'affaires, dans la limite des libertés fondamentales du Salarié.");

        $pdf->article('10', 'Données personnelles (RGPD)',
            "Les données personnelles du Salarié sont traitées par l'Employeur aux fins de gestion du "
            . "personnel et de la paie, conformément au règlement (UE) 2016/679 (RGPD) et à la loi "
            . "Informatique et Libertés. Le Salarié dispose d'un droit d'accès, de rectification, "
            . "d'effacement et d'opposition, exercé auprès de l'Employeur. Les données sont conservées "
            . "pendant la durée légale applicable et ne font l'objet d'aucun transfert hors de l'Union "
            . "européenne sans garanties appropriées.");

        $pdf->article('11', 'Responsabilité et assurance',
            "Chaque partie assume la responsabilité prévue par la loi et les conventions applicables. "
            . "L'Employeur souscrit les assurances obligatoires, notamment la couverture des accidents "
            . "du travail et des maladies professionnelles. La responsabilité du Salarié ne peut être "
            . "recherchée qu'en cas de faute lourde ou intentionnelle, dans les limites légales.");

        $pdf->article('12', 'Force majeure',
            "En cas de force majeure au sens de l'article 1218 du Code civil et de la jurisprudence, "
            . "l'exécution des obligations est suspendue. La partie empêchée informe l'autre sans délai "
            . "et met en œuvre tous les moyens pour limiter les effets de l'événement.");

        $pdf->article('13', 'Rupture du contrat',
            "La rupture s'opère selon les formes légales applicables : démission, licenciement, prise "
            . "d'acte, rupture conventionnelle homologuée ou, pour les contrats à durée déterminée, "
            . "rupture anticipée dans les seuls cas prévus par la loi. Le préavis, les indemnités et les "
            . "documents de fin de contrat (certificat de travail, solde de tout compte, attestation Pôle "
            . "emploi) sont remis conformément au Code du travail.");

        $pdf->article('14', 'Litiges et droit applicable',
            "Le présent contrat est soumis au droit français. Tout litige né de sa formation, de son "
            . "exécution ou de sa rupture relève de la compétence du conseil de prud'hommes du lieu de "
            . "travail ou du domicile du Salarié, après tentative de conciliation.");

        $pdf->article('15', 'Dispositions générales',
            "Si une clause devenait nulle ou inapplicable, les autres clauses conserveraient leurs effets. "
            . "Toute modification du contrat fait l'objet d'un avenant signé. Les courriels professionnels "
            . "et les notifications dématérialisées valent échange entre les parties. Le contrat est établi "
            . "en deux exemplaires originaux, un remis à chaque partie.");

        $pdf->h2('Signatures');
        $pdf->paragraph('Fait en deux exemplaires originaux remis à chacune des parties.');
        $pdf->signatureBlock(
            "Pour l'Employeur\n{$companyName}\nNom et prénom : ………………………………\nQualité : ………………………………\nFait à ……………………, le ……………………\nSignature (mention « Lu et approuvé »)",
            "Le Salarié\n{$employeeName}\nNom et prénom : ………………………………\nFait à ……………………, le ……………………\nSignature précédée de la mention manuscrite « Lu et approuvé »"
        );

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
