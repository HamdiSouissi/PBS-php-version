<?php

declare(strict_types=1);

namespace App\Core\Pdf;

/**
 * Libellés français de présentation des codes métier apparaissant dans les
 * documents PDF. Centralisés ici pour garantir la même terminologie sur tous
 * les documents ; une valeur inconnue est restituée telle quelle, sans
 * jamais masquer une donnée.
 */
final class PdfLabels
{
    public static function invoiceStatus(string $status): string
    {
        return match ($status) {
            'draft' => 'Brouillon',
            'issued' => 'Émise',
            'partial' => 'Paiement partiel',
            'paid' => 'Payée',
            'overdue' => 'En retard',
            'cancelled' => 'Annulée',
            default => $status,
        };
    }

    public static function quoteStatus(string $status): string
    {
        return match ($status) {
            'draft' => 'Brouillon',
            'sent' => 'Envoyé',
            'accepted' => 'Accepté',
            'rejected' => 'Refusé',
            'invoiced' => 'Facturé',
            'expired' => 'Expiré',
            default => $status,
        };
    }

    public static function contractStatus(string $status): string
    {
        return match ($status) {
            'draft' => 'Brouillon',
            'active' => 'Actif',
            'terminated' => 'Résilié',
            'expired' => 'Expiré',
            default => $status,
        };
    }

    public static function payslipStatus(string $status): string
    {
        return match ($status) {
            'draft' => 'Brouillon',
            'locked' => 'Clôturé',
            default => $status,
        };
    }

    public static function employeeStatus(string $status): string
    {
        return match ($status) {
            'active' => 'Actif',
            'on_leave' => 'En congé',
            '' => '—',
            default => $status,
        };
    }

    public static function subscriptionStatus(string $status): string
    {
        return match ($status) {
            'active' => 'Actif',
            'paused' => 'En pause',
            'cancelled' => 'Annulé',
            default => $status,
        };
    }

    public static function expenseStatus(string $status): string
    {
        return match ($status) {
            'draft' => 'Brouillon',
            'submitted' => 'Soumise',
            'approved' => 'Validée',
            'rejected' => 'Rejetée',
            'paid' => 'Payée',
            default => $status,
        };
    }

    public static function expenseCategory(string $category): string
    {
        return match ($category) {
            'transport' => 'Transport',
            'meal' => 'Repas',
            'accommodation' => 'Hébergement',
            'fuel' => 'Carburant',
            'kilometric' => 'Frais kilométriques',
            'supplies' => 'Fournitures',
            default => 'Autre',
        };
    }

    public static function billingCycle(string $cycle): string
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

    public static function paymentMethod(string $method): string
    {
        return match ($method) {
            'virement' => 'Virement',
            'prelevement' => 'Prélèvement',
            'cheque' => 'Chèque',
            'carte' => 'Carte',
            '' => '—',
            default => $method,
        };
    }
}
