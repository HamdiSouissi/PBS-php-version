<?php

declare(strict_types=1);

use App\Core\Http\Request;
use App\Core\Router\Router;

return static function (Router $router): void {
    $router->group(['auth'], '/payroll', static function (Router $router): void {
        $router->get('', static function (Request $request) {
            $db = db();
            $periods = array_map(
                static fn (array $r): array => $r + [
                    'label' => sprintf('%02d/%d', (int) $r['month'], (int) $r['year']),
                    'locked' => (int) $r['is_locked'] === 1 ? 'Oui' : 'Non',
                ],
                $db->fetchAll('SELECT year, month, starts_on, ends_on, status, is_locked FROM payroll_periods ORDER BY year DESC, month DESC LIMIT 24')
            );
            $payslips = array_map(
                static fn (array $r): array => $r + [
                    'period' => sprintf('%02d/%d', (int) $r['month'], (int) $r['year']),
                    'employee' => mb_strtoupper((string) $r['last_name']) . ' ' . $r['first_name'],
                    'paid' => (int) $r['is_paid'] === 1 ? 'Oui' : 'Non',
                ],
                $db->fetchAll('SELECT * FROM v_payslip_summary ORDER BY year DESC, month DESC, last_name LIMIT 100')
            );
            $rules = $db->fetchAll('SELECT code, label, rate_employee, rate_employer, ceiling_month, effective_from FROM payroll_rule_versions ORDER BY code LIMIT 100');

            return view('modules/list', [
                'title' => 'Paie',
                'heading' => 'Paie',
                'intro' => 'Périodes, bulletins et règles de cotisations versionnées.',
                'notice' => 'Le moteur de calcul des bulletins et la DSN ne sont pas encore implémentés. Les taux doivent être validés par un spécialiste paie.',
                'sections' => [
                    ['title' => 'Périodes de paie', 'rows' => $periods, 'empty' => 'Aucune période.', 'columns' => [
                        'label' => ['Période'], 'starts_on' => ['Début', 'date'], 'ends_on' => ['Fin', 'date'], 'status' => ['Statut', 'badge'], 'locked' => ['Verrouillée'],
                    ]],
                    ['title' => 'Bulletins', 'rows' => $payslips, 'empty' => 'Aucun bulletin.', 'columns' => [
                        'period' => ['Période'], 'employee_number' => ['Matricule'], 'employee' => ['Salarié'], 'status' => ['Statut', 'badge'],
                        'gross_salary' => ['Brut', 'money'], 'net_payable' => ['Net à payer', 'money'], 'paid' => ['Payé'],
                    ]],
                    ['title' => 'Règles de cotisations', 'rows' => $rules, 'columns' => [
                        'code' => ['Code'], 'label' => ['Libellé'], 'rate_employee' => ['Taux salarié %'], 'rate_employer' => ['Taux employeur %'],
                        'ceiling_month' => ['Plafond mensuel', 'money'], 'effective_from' => ['Effet', 'date'],
                    ]],
                ],
            ]);
        })->middleware('permission:payroll.read')->name('payroll.index');
    });
};
