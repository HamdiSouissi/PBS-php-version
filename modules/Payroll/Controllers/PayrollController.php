<?php

declare(strict_types=1);

namespace Modules\Payroll\Controllers;

use App\Core\Database\Connection;
use App\Core\Http\Controller;
use App\Core\Http\RedirectResponse;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Pdf\PdfManager;
use Modules\Payroll\Services\PayrollService;

/**
 * Paie : périodes, génération et cycle de vie des bulletins.
 */
final class PayrollController extends Controller
{
    public function __construct(
        private readonly Connection $db,
        private readonly PayrollService $payroll,
        private readonly PdfManager $pdfManager
    ) {
    }

    public function index(Request $request): Response
    {
        $this->auth()->requirePermission('payroll.read');
        return $this->view('Payroll::index', [
            'title' => 'Paie',
            'periods' => $this->db->fetchAll(
                'SELECT per.*,
                        (SELECT COUNT(*) FROM payslips p WHERE p.period_id = per.id) AS payslip_count,
                        (SELECT COUNT(*) FROM payslips p WHERE p.period_id = per.id AND p.status = \'draft\') AS draft_count
                 FROM payroll_periods per ORDER BY per.year DESC, per.month DESC LIMIT 24'
            ),
            'payslips' => $this->db->fetchAll(
                'SELECT * FROM v_payslip_summary ORDER BY year DESC, month DESC, last_name LIMIT 100'
            ),
            'rules' => $this->db->fetchAll(
                'SELECT code, label, rate_employee, rate_employer, ceiling_month, effective_from
                 FROM payroll_rule_versions ORDER BY code LIMIT 100'
            ),
        ]);
    }

    public function generateForm(Request $request): Response
    {
        $this->auth()->requirePermission('payroll.run');
        return $this->view('Payroll::generate', [
            'title' => 'Générer un bulletin',
            'periods' => $this->db->fetchAll(
                'SELECT * FROM payroll_periods WHERE is_locked = 0 ORDER BY year DESC, month DESC'
            ),
            'employees' => $this->db->fetchAll(
                'SELECT e.id, e.employee_number, e.first_name, e.last_name, c.monthly_gross, c.is_part_time, c.part_time_pct
                 FROM employees e
                 JOIN employment_contracts c ON c.employee_id = e.id AND c.status = \'active\'
                 WHERE e.termination_date IS NULL ORDER BY e.last_name, e.first_name'
            ),
        ]);
    }

    public function generate(Request $request): Response
    {
        $this->auth()->requirePermission('payroll.run');
        $data = $this->validator()->validate($request->all(), [
            'period_id' => 'required|integer',
            'employee_id' => 'required|integer',
            'bonus' => 'nullable|decimal:2',
        ]);
        $bonus = round((float) ($data['bonus'] ?? 0), 2);
        $payslipId = $this->payroll->generatePayslip(
            (int) $data['period_id'],
            (int) $data['employee_id'],
            $bonus,
            $this->auth()->id()
        );
        audit('payroll.payslip.generate', 'Payslip', $payslipId);
        flash('success', 'Bulletin généré en brouillon.');
        return new RedirectResponse('/payroll/' . $payslipId);
    }

    public function createPeriod(Request $request): Response
    {
        $this->auth()->requirePermission('payroll.run');
        $data = $this->validator()->validate($request->all(), [
            'year' => 'required|integer',
            'month' => 'required|integer',
        ]);
        $companyId = (int) ($this->db->fetchScalar('SELECT id FROM companies ORDER BY id LIMIT 1') ?? 0);
        $period = $this->payroll->ensurePeriod($companyId, (int) $data['year'], (int) $data['month']);
        flash('success', 'Période de paie prête.');
        return new RedirectResponse('/payroll?period=' . (int) $period['id']);
    }

    public function validatePayslip(Request $request, int $id): Response
    {
        $this->auth()->requirePermission('payroll.validate');
        $this->payroll->validate($id, (int) $this->auth()->id());
        flash('success', 'Bulletin validé ; le PDF a été généré.');
        return new RedirectResponse('/payroll/' . $id);
    }

    public function lockPeriod(Request $request, int $id): Response
    {
        $this->auth()->requirePermission('payroll.lock');
        $this->payroll->lockPeriod($id, (int) $this->auth()->id());
        flash('success', 'Période verrouillée.');
        return new RedirectResponse('/payroll');
    }

    public function markPaid(Request $request, int $id): Response
    {
        $this->auth()->requirePermission('payroll.validate');
        $this->payroll->markPaid($id);
        flash('success', 'Paiement du bulletin enregistré.');
        return new RedirectResponse('/payroll/' . $id);
    }

    public function regeneratePdf(Request $request, int $id): Response
    {
        $this->auth()->requirePermission('payroll.read');
        $payslip = $this->db->fetchOne('SELECT id FROM payslips WHERE id = :id', ['id' => $id]);
        if ($payslip === null) {
            flash('error', 'Bulletin introuvable.');
            return new RedirectResponse('/payroll');
        }
        try {
            $this->pdfManager->generate('payslip', $id, $this->auth()->id());
            flash('success', 'PDF du bulletin régénéré.');
        } catch (\Throwable $exception) {
            logger()->error('PDF bulletin échoué', ['id' => $id, 'error' => $exception->getMessage()]);
            flash('error', 'Génération PDF indisponible, réessayez.');
        }
        return new RedirectResponse('/payroll/' . $id);
    }

    public function show(Request $request, int $id): Response
    {
        $this->auth()->requirePermission('payroll.read');
        $payslip = $this->db->fetchOne(
            'SELECT p.*, per.year, per.month, per.starts_on, per.ends_on, per.is_locked,
                    e.employee_number, e.first_name, e.last_name, e.job_title
             FROM payslips p
             JOIN payroll_periods per ON per.id = p.period_id
             JOIN employees e ON e.id = p.employee_id
             WHERE p.id = :id',
            ['id' => $id]
        );
        if ($payslip === null) {
            flash('error', 'Bulletin introuvable.');
            return new RedirectResponse('/payroll');
        }
        $lines = $this->db->fetchAll(
            'SELECT * FROM payslip_lines WHERE payslip_id = :id ORDER BY sort_order, id',
            ['id' => $id]
        );
        $document = $this->db->fetchOne(
            "SELECT * FROM documents WHERE entity_type = 'payslip' AND entity_id = :id ORDER BY id DESC LIMIT 1",
            ['id' => $id]
        );
        return $this->view('Payroll::show', [
            'title' => 'Bulletin de paie',
            'payslip' => $payslip,
            'lines' => $lines,
            'document' => $document,
        ]);
    }
}
