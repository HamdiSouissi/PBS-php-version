<?php

declare(strict_types=1);

namespace Modules\Payroll\Services;

use App\Core\Database\Connection;
use App\Core\Event\EventDispatcher;
use App\Core\Exception\AppException;
use Modules\Payroll\Events\PayslipValidated;

/**
 * Moteur de paie français simplifié mais réel :
 * rémunération brute du contrat actif, cotisations URSSAF/Agirc-Arrco
 * versionnées, CSG/CRDS, nets et lignes de bulletin détaillées.
 *
 * Limites assumées (aucune fausse complétude) :
 *  - réduction générale de cotisations (Fillon) non calculée (Smec/Smic requis) ;
 *  - PAS (impôt à la source) non retenu sur le net à payer ;
 *  - primes traitées comme un brut imposable unique ;
 *  - les taux et plafonds restent ceux des règles versionnées en base,
 *    qui doivent être validés par un spécialiste paie.
 */
final class PayrollService
{
    /** Assiette forfaitaire CSG : 98,25 % de la rémunération brute. */
    private const CSG_BASE_RATIO = 0.9825;

    /** Durée légale mensuelle moyenne pour 35 h hebdomadaires. */
    private const LEGAL_MONTHLY_HOURS = 151.67;

    public function __construct(
        private readonly Connection $db,
        private readonly EventDispatcher $events
    ) {
    }

    /**
     * Crée la période de paie (idempotent) et la retourne.
     *
     * @return array<string,mixed>
     */
    public function ensurePeriod(int $companyId, int $year, int $month): array
    {
        if ($month < 1 || $month > 12) {
            throw new AppException('Mois invalide.');
        }
        $period = $this->db->fetchOne(
            'SELECT * FROM payroll_periods WHERE company_id = :company AND year = :year AND month = :month',
            ['company' => $companyId, 'year' => $year, 'month' => $month]
        );
        if ($period !== null) {
            return $period;
        }
        $start = new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
        $this->db->execute(
            'INSERT INTO payroll_periods (company_id, year, month, starts_on, ends_on, status, is_locked)
             VALUES (:company, :year, :month, :start, :end, \'open\', 0)',
            [
                'company' => $companyId,
                'year' => $year,
                'month' => $month,
                'start' => $start->format('Y-m-d'),
                'end' => $start->modify('last day of this month')->format('Y-m-d'),
            ]
        );
        return $this->db->fetchOne(
            'SELECT * FROM payroll_periods WHERE id = :id',
            ['id' => (int) $this->db->lastInsertId()]
        ) ?? throw new AppException('Création de la période de paie impossible.');
    }

    /**
     * Génère (ou recalcule un brouillon) le bulletin d'un salarié.
     *
     * @return int identifiant du bulletin
     */
    public function generatePayslip(int $periodId, int $employeeId, float $bonus, ?int $userId): int
    {
        $period = $this->requirePeriod($periodId);
        if ((int) $period['is_locked'] === 1) {
            throw new AppException('La période est verrouillée, le bulletin ne peut plus être modifié.');
        }
        $contract = $this->db->fetchOne(
            'SELECT * FROM employment_contracts
             WHERE employee_id = :employee AND status = \'active\'
             ORDER BY start_date DESC, id DESC LIMIT 1',
            ['employee' => $employeeId]
        );
        if ($contract === null) {
            throw new AppException('Aucun contrat de travail actif pour ce salarié.');
        }
        $partTimeRatio = (int) $contract['is_part_time'] === 1
            ? max(0.0, (float) $contract['part_time_pct']) / 100.0
            : 1.0;
        $gross = round((float) $contract['monthly_gross'] * $partTimeRatio + round($bonus, 2), 2);
        if ($gross <= 0.0) {
            throw new AppException('La rémunération brute doit être strictement positive.');
        }
        $hours = round(self::LEGAL_MONTHLY_HOURS * $partTimeRatio, 2);
        $rules = $this->db->fetchAll(
            'SELECT * FROM payroll_rule_versions
             WHERE effective_from <= :ends AND (effective_to IS NULL OR effective_to >= :starts)
             ORDER BY id',
            ['ends' => $period['ends_on'], 'starts' => $period['starts_on']]
        );
        if ($rules === []) {
            throw new AppException('Aucune règle de cotisations applicable à la période.');
        }

        return (int) $this->db->transaction(function (Connection $db) use (
            $period, $contract, $employeeId, $gross, $hours, $partTimeRatio, $rules, $userId
        ): int {
            $existing = $db->fetchOne(
                'SELECT * FROM payslips WHERE period_id = :period AND employee_id = :employee',
                ['period' => (int) $period['id'], 'employee' => $employeeId]
            );
            if ($existing !== null && $existing['status'] !== 'draft') {
                throw new AppException('Un bulletin déjà validé existe pour cette période.');
            }

            [$lines, $totalEmployee, $totalEmployer, $deductibleEmployee] = $this->buildLines(
                $rules,
                $gross,
                $partTimeRatio
            );
            $netPayable = round($gross - $totalEmployee, 2);
            $netTaxable = round($gross - $deductibleEmployee, 2);

            if ($existing === null) {
                $db->execute(
                    'INSERT INTO payslips
                        (period_id, employee_id, contract_id, status, hours_worked, gross_salary,
                         total_employee, total_employer, net_social, net_taxable, net_payable,
                         general_reduction, generated_by)
                     VALUES
                        (:period, :employee, :contract, \'draft\', :hours, :gross,
                         :te, :tp, :net_social, :net_taxable, :net, 0, :user)',
                    [
                        'period' => (int) $period['id'],
                        'employee' => $employeeId,
                        'contract' => (int) $contract['id'],
                        'hours' => $hours,
                        'gross' => $gross,
                        'te' => $totalEmployee,
                        'tp' => $totalEmployer,
                        'net_social' => $netPayable,
                        'net_taxable' => $netTaxable,
                        'net' => $netPayable,
                        'user' => $userId,
                    ]
                );
                $payslipId = (int) $db->lastInsertId();
            } else {
                $payslipId = (int) $existing['id'];
                $db->execute('DELETE FROM payslip_lines WHERE payslip_id = :id', ['id' => $payslipId]);
                $db->execute(
                    'UPDATE payslips SET contract_id = :contract, hours_worked = :hours, gross_salary = :gross,
                        total_employee = :te, total_employer = :tp, net_social = :net_social,
                        net_taxable = :net_taxable, net_payable = :net, general_reduction = 0,
                        generated_by = :user, updated_at = ' . $db->nowExpression() . '
                     WHERE id = :id',
                    [
                        'contract' => (int) $contract['id'],
                        'hours' => $hours,
                        'gross' => $gross,
                        'te' => $totalEmployee,
                        'tp' => $totalEmployer,
                        'net_social' => $netPayable,
                        'net_taxable' => $netTaxable,
                        'net' => $netPayable,
                        'user' => $userId,
                        'id' => $payslipId,
                    ]
                );
            }

            $sort = 0;
            foreach ($lines as $line) {
                $db->execute(
                    'INSERT INTO payslip_lines
                        (payslip_id, rule_code, label, base_amount, rate_employee, rate_employer,
                         amount_employee, amount_employer, sort_order)
                     VALUES
                        (:payslip, :code, :label, :base, :re, :rp, :ae, :ap, :sort)',
                    [
                        'payslip' => $payslipId,
                        'code' => $line['code'],
                        'label' => $line['label'],
                        'base' => $line['base'],
                        're' => $line['rate_employee'],
                        'rp' => $line['rate_employer'],
                        'ae' => $line['amount_employee'],
                        'ap' => $line['amount_employer'],
                        'sort' => $sort++,
                    ]
                );
            }
            return $payslipId;
        });
    }

    /**
     * Valide un bulletin brouillon et déclenche la génération du PDF.
     */
    public function validate(int $payslipId, int $userId): void
    {
        $payslip = $this->requirePayslip($payslipId);
        if ($payslip['status'] !== 'draft') {
            throw new AppException('Seul un bulletin brouillon peut être validé.');
        }
        $this->db->execute(
            'UPDATE payslips SET status = \'validated\', validated_by = :user,
                validated_at = :at, updated_at = ' . $this->db->nowExpression() . ' WHERE id = :id',
            ['user' => $userId, 'at' => date('Y-m-d H:i:s'), 'id' => $payslipId]
        );
        $this->events->dispatch(new PayslipValidated(
            $payslipId,
            (int) $payslip['employee_id'],
            (int) $payslip['period_id']
        ));
    }

    /**
     * Verrouille la période : tous les bulletins doivent être validés.
     */
    public function lockPeriod(int $periodId, int $userId): void
    {
        $period = $this->requirePeriod($periodId);
        if ((int) $period['is_locked'] === 1) {
            return;
        }
        $drafts = (int) $this->db->fetchScalar(
            'SELECT COUNT(*) FROM payslips WHERE period_id = :period AND status = \'draft\'',
            ['period' => $periodId]
        );
        if ($drafts > 0) {
            throw new AppException("{$drafts} bulletin(s) brouillon(s) doivent être validés avant verrouillage.");
        }
        $this->db->execute(
            "UPDATE payslips SET status = 'locked', updated_at = " . $this->db->nowExpression()
            . ' WHERE period_id = :period AND status = \'validated\'',
            ['period' => $periodId]
        );
        $this->db->execute(
            "UPDATE payroll_periods SET status = 'closed', is_locked = 1, closed_at = :at WHERE id = :id",
            ['at' => date('Y-m-d H:i:s'), 'id' => $periodId]
        );
        audit('payroll.period.lock', 'PayrollPeriod', $periodId);
    }

    /**
     * Enregistre le paiement d'un bulletin validé ou verrouillé.
     */
    public function markPaid(int $payslipId): void
    {
        $payslip = $this->requirePayslip($payslipId);
        if (!in_array($payslip['status'], ['validated', 'locked'], true)) {
            throw new AppException('Le bulletin doit être validé avant paiement.');
        }
        $this->db->execute(
            'UPDATE payslips SET is_paid = 1, payment_date = :date, updated_at = ' . $this->db->nowExpression()
            . ' WHERE id = :id',
            ['date' => date('Y-m-d'), 'id' => $payslipId]
        );
        audit('payroll.payslip.pay', 'Payslip', $payslipId);
    }

    /**
     * Calcule les lignes de cotisation à partir des règles versionnées.
     *
     * @param list<array<string,mixed>> $rules
     * @return array{0:list<array<string,mixed>>,1:float,2:float,3:float}
     */
    private function buildLines(array $rules, float $gross, float $partTimeRatio): array
    {
        $lines = [];
        $totalEmployee = 0.0;
        $totalEmployer = 0.0;
        $deductibleEmployee = 0.0;
        foreach ($rules as $rule) {
            $base = $this->assessmentBase($rule, $gross, $partTimeRatio);
            $rateEmployee = (float) $rule['rate_employee'];
            $rateEmployer = (float) $rule['rate_employer'];
            $amountEmployee = round($base * $rateEmployee / 100, 2);
            $amountEmployer = round($base * $rateEmployer / 100, 2);
            $totalEmployee += $amountEmployee;
            $totalEmployer += $amountEmployer;
            if ((int) $rule['is_deductible'] === 1) {
                $deductibleEmployee += $amountEmployee;
            }
            $lines[] = [
                'code' => (string) $rule['code'],
                'label' => (string) $rule['label'],
                'base' => round($base, 2),
                'rate_employee' => $rateEmployee,
                'rate_employer' => $rateEmployer,
                'amount_employee' => $amountEmployee,
                'amount_employer' => $amountEmployer,
            ];
        }
        return [$lines, round($totalEmployee, 2), round($totalEmployer, 2), round($deductibleEmployee, 2)];
    }

    /**
     * Détermine l'assiette d'une règle selon son type.
     *
     * @param array<string,mixed> $rule
     */
    private function assessmentBase(array $rule, float $gross, float $partTimeRatio): float
    {
        $ceiling = $rule['ceiling_month'] !== null ? (float) $rule['ceiling_month'] : null;
        return match ((string) $rule['base_type']) {
            'brut' => $gross,
            'plafond' => min($gross, ($ceiling ?? $gross) * $partTimeRatio),
            'brut_et_plafond' => min($gross, $ceiling ?? $gross),
            'csg' => round($gross * self::CSG_BASE_RATIO, 2),
            default => 0.0,
        };
    }

    /**
     * @return array<string,mixed>
     */
    private function requirePeriod(int $id): array
    {
        $period = $this->db->fetchOne('SELECT * FROM payroll_periods WHERE id = :id', ['id' => $id]);
        if ($period === null) {
            throw new AppException('Période de paie introuvable.');
        }
        return $period;
    }

    /**
     * @return array<string,mixed>
     */
    private function requirePayslip(int $id): array
    {
        $payslip = $this->db->fetchOne('SELECT * FROM payslips WHERE id = :id', ['id' => $id]);
        if ($payslip === null) {
            throw new AppException('Bulletin de paie introuvable.');
        }
        return $payslip;
    }
}
