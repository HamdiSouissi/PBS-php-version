<?php

declare(strict_types=1);

namespace Modules\HR\Services;

use App\Core\Database\Connection;
use App\Core\Exception\ValidationException;

/**
 * Paramètres RH stockés dans la table `settings`.
 *
 * Le taux d'acquisition mensuelle des congés payés est réglable. La valeur
 * par défaut correspond au standard français : 25 jours ouvrés par an
 * (5 semaines), soit 25/12 ≈ 2,0833 jours par mois de travail effectif.
 * Le cadre légal voisin des jours ouvrables (2,5 j/mois, 30 j/an) reste
 * accessible en modifiant ce réglage.
 */
final class LeaveSettingsService
{
    public const GROUP = 'hr';

    public const KEY_ACCRUAL = 'leave_monthly_accrual';

    /** Type de congé concerné par le calcul automatique (congés payés). */
    public const PAID_LEAVE_CODE = 'CP';

    /** 25 jours ouvrés annuels (droit commun français) rapportés au mois. */
    public const DEFAULT_MONTHLY_ACCRUAL = 25 / 12;

    public const MIN_MONTHLY_ACCRUAL = 0.5;

    public const MAX_MONTHLY_ACCRUAL = 5.0;

    public function __construct(private readonly Connection $db)
    {
    }

    /**
     * Nombre de jours de congés payés acquis par mois de travail effectif.
     */
    public function monthlyAccrual(): float
    {
        $value = $this->db->fetchScalar(
            'SELECT value FROM settings WHERE group_name = :g AND key_name = :k',
            ['g' => self::GROUP, 'k' => self::KEY_ACCRUAL]
        );
        if ($value === false || $value === null || (float) $value <= 0.0) {
            return self::DEFAULT_MONTHLY_ACCRUAL;
        }
        return (float) $value;
    }

    /**
     * Équivalent annuel théorique (12 mois d'activité).
     */
    public function annualEquivalent(): float
    {
        return round($this->monthlyAccrual() * 12, 2);
    }

    public function setMonthlyAccrual(float $daysPerMonth): void
    {
        if ($daysPerMonth < self::MIN_MONTHLY_ACCRUAL || $daysPerMonth > self::MAX_MONTHLY_ACCRUAL) {
            throw new ValidationException([
                'leave_monthly_accrual' => sprintf(
                    'Le taux mensuel doit être compris entre %s et %s jours.',
                    rtrim(rtrim(number_format(self::MIN_MONTHLY_ACCRUAL, 2, '.', ''), '0'), '.'),
                    rtrim(rtrim(number_format(self::MAX_MONTHLY_ACCRUAL, 2, '.', ''), '0'), '.')
                ),
            ]);
        }
        $this->db->upsert(
            'settings',
            [
                'group_name' => self::GROUP,
                'key_name' => self::KEY_ACCRUAL,
                'value' => number_format($daysPerMonth, 4, '.', ''),
                'is_encrypted' => 0,
            ],
            ['group_name', 'key_name'],
            ['value']
        );
    }

    /**
     * Garantit la présence du réglage par défaut (idempotent, utilisé par
     * les seeders).
     */
    public function seedDefault(): void
    {
        $exists = $this->db->fetchScalar(
            'SELECT id FROM settings WHERE group_name = :g AND key_name = :k',
            ['g' => self::GROUP, 'k' => self::KEY_ACCRUAL]
        );
        if ($exists !== false && $exists !== null) {
            return;
        }
        $this->db->insertIgnore(
            'settings',
            [
                'group_name' => self::GROUP,
                'key_name' => self::KEY_ACCRUAL,
                'value' => number_format(self::DEFAULT_MONTHLY_ACCRUAL, 4, '.', ''),
                'is_encrypted' => 0,
            ],
            ['group_name', 'key_name']
        );
    }
}
