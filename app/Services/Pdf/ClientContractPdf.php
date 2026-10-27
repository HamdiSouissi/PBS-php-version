<?php

declare(strict_types=1);

namespace App\Services\Pdf;

use App\Core\Database\Connection;
use App\Core\Exception\NotFoundException;
use App\Core\Pdf\PdfDocument;

/**
 * Contrat commercial client PDF (prestations, cycle de facturation,
 * renouvellement et préavis).
 */
final class ClientContractPdf implements PdfTemplate
{
    public function __construct(private readonly Connection $db)
    {
    }

    public function render(PdfDocument $pdf, int $entityId): string
    {
        $contract = $this->db->fetchOne(
            'SELECT c.*, t.name AS client_name, t.address AS client_address, t.postal_code AS client_pc,
                    t.city AS client_city, t.siret AS client_siret, co.legal_name, co.address AS company_address,
                    co.postal_code AS company_pc, co.city AS company_city, co.siret AS company_siret
             FROM client_contracts c
             JOIN third_parties t ON t.id = c.third_party_id
             JOIN companies co ON co.id = (SELECT id FROM companies ORDER BY is_default DESC, id ASC LIMIT 1)
             WHERE c.id = :id',
            ['id' => $entityId]
        );
        if ($contract === null) {
            throw new NotFoundException('Contrat client introuvable.');
        }

        $title = 'Contrat client ' . $contract['contract_number'];
        $pdf->setDocumentTitle($title)->addPage();
        $pdf->h1('CONTRAT DE PRESTATIONS');

        $pdf->keyValueTable([
            ['Référence', (string) $contract['contract_number']],
            ['Intitulé', (string) $contract['title']],
            ['Date de signature', $contract['signed_on'] ? date('d/m/Y', strtotime((string) $contract['signed_on'])) : '—'],
            ['Date d\'effet', date('d/m/Y', strtotime((string) $contract['starts_on']))],
            ['Date de fin', $contract['ends_on'] ? date('d/m/Y', strtotime((string) $contract['ends_on'])) : 'Reconduction'],
            ['Statut', $this->statusLabel((string) $contract['status'])],
        ]);

        $pdf->h2('1. Parties');
        $pdf->paragraph(
            "Le prestataire : {$contract['legal_name']}, {$contract['company_address']}, "
            . trim($contract['company_pc'] . ' ' . $contract['company_city'])
            . ", SIRET {$contract['company_siret']},\n"
            . "Le client : {$contract['client_name']}, "
            . implode(', ', array_filter([
                $contract['client_address'],
                trim($contract['client_pc'] . ' ' . $contract['client_city']),
                $contract['client_siret'] ? 'SIRET ' . $contract['client_siret'] : '',
            ])) . '.'
        );

        $pdf->h2('2. Objet du contrat');
        $pdf->paragraph("Le présent contrat porte sur la prestation suivante : {$contract['title']}. Les conditions particulières de réalisation sont définies d'un commun accord entre les parties et formalisées par les bons de commande et devis annexés.");

        $pdf->h2('3. Conditions financières');
        $pdf->keyValueTable([
            ['Montant annuel HT', money_format((float) $contract['amount_ht'])],
            ['TVA', 'Taux en vigueur applicable au jour de la facturation'],
            ['Cycle de facturation', $this->cycleLabel((string) $contract['billing_cycle'])],
            ['Modalités de règlement', '30 jours nets à réception de facture, par virement bancaire'],
        ]);

        $pdf->h2('4. Durée, renouvellement et préavis');
        $renewal = match ((string) $contract['renewal_mode']) {
            'tacit' => 'reconduction tacite aux mêmes conditions',
            'express' => 'renouvellement exprès requis par avenant signé',
            default => 'pas de reconduction automatique',
        };
        $pdf->paragraph(
            "Le contrat prend effet le " . date('d/m/Y', strtotime((string) $contract['starts_on'])) . '. '
            . "Le mode de renouvellement est le suivant : {$renewal}. "
            . "Chaque partie peut mettre fin au contrat sous réserve d'un préavis de "
            . (int) $contract['notice_days'] . " jours, notifié par lettre ou e-mail avec accusé de réception."
        );

        if (($contract['notes'] ?? '') !== '') {
            $pdf->h2('5. Notes et conditions particulières');
            $pdf->paragraph((string) $contract['notes']);
        }

        $pdf->h2('Signatures');
        $pdf->signatureBlock("Pour le prestataire\n{$contract['legal_name']}", "Pour le client\n{$contract['client_name']}");

        return $title;
    }

    private function cycleLabel(string $cycle): string
    {
        return match ($cycle) {
            'monthly' => 'Mensuel',
            'quarterly' => 'Trimestriel',
            'half_yearly' => 'Semestriel',
            'yearly' => 'Annuel',
            'one_shot' => 'Ponctuel',
            default => $cycle,
        };
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'draft' => 'Brouillon',
            'active' => 'Actif',
            'terminated' => 'Résilié',
            'expired' => 'Expiré',
            default => $status,
        };
    }
}
