<?php

declare(strict_types=1);

namespace Modules\Dividends\Controllers;

use App\Core\Database\Connection;
use App\Core\Exception\AppException;
use App\Core\Http\Controller;
use App\Core\Http\RedirectResponse;
use App\Core\Http\Request;
use App\Core\Http\Response;

/**
 * Associés et décisions de dividendes : création, décision et paiement.
 */
final class DividendController extends Controller
{
    public function __construct(private readonly Connection $db)
    {
    }

    public function index(Request $request): Response
    {
        $this->auth()->requirePermission('dividends.read');
        $name = static fn (array $r): string => $r['entity_name'] !== ''
            ? (string) $r['entity_name']
            : trim($r['first_name'] . ' ' . mb_strtoupper((string) $r['last_name']));
        return $this->view('Dividends::index', [
            'title' => 'Associés & dividendes',
            'shareholders' => array_map(
                static fn (array $r): array => $r + ['name' => $name($r)],
                $this->db->fetchAll('SELECT * FROM shareholders ORDER BY share_pct DESC')
            ),
            'dividends' => array_map(
                fn (array $r): array => $r + ['name' => $name($r)],
                $this->db->fetchAll(
                    'SELECT d.*, s.entity_name, s.first_name, s.last_name
                     FROM dividends d JOIN shareholders s ON s.id = d.shareholder_id
                     ORDER BY d.decided_on DESC, d.id DESC LIMIT 200'
                )
            ),
            'fiscalYears' => $this->db->fetchAll('SELECT id, label FROM fiscal_years ORDER BY id DESC'),
        ]);
    }

    public function store(Request $request): Response
    {
        $this->auth()->requirePermission('dividends.write');
        $data = $this->validator()->validate($request->all(), [
            'shareholder_id' => 'required|integer',
            'fiscal_year_id' => 'nullable|integer',
            'decided_on' => 'required|date',
            'gross_amount' => 'required|decimal:2',
            'social_rate' => 'nullable|decimal:2',
        ]);
        $gross = round((float) $data['gross_amount'], 2);
        if ($gross <= 0.0) {
            throw new AppException('Le montant brut doit être strictement positif.');
        }
        $socialRate = (float) ($data['social_rate'] ?? 17.2);
        $socialDeductions = round($gross * $socialRate / 100, 2);
        $companyId = (int) $this->db->fetchScalar('SELECT company_id FROM shareholders WHERE id = :id', ['id' => $data['shareholder_id']]);
        $this->db->execute(
            'INSERT INTO dividends
                (company_id, shareholder_id, fiscal_year_id, decided_on, gross_amount, flat_tax_rate, social_rate,
                 social_deductions, tax_option, net_amount, status)
             VALUES
                (:company, :shareholder, :fy, :decided, :gross, 30, :social_rate,
                 :social, \'pfuo\', :net, \'proposed\')',
            [
                'company' => $companyId,
                'shareholder' => (int) $data['shareholder_id'],
                'fy' => $data['fiscal_year_id'] ?: null,
                'decided' => $data['decided_on'],
                'gross' => $gross,
                'social_rate' => $socialRate,
                'social' => $socialDeductions,
                'net' => round($gross - $socialDeductions, 2),
            ]
        );
        $id = (int) $this->db->lastInsertId();
        audit('dividend.create', 'Dividend', $id);
        flash('success', 'Projet de distribution enregistré.');
        return new RedirectResponse('/dividends');
    }

    public function decide(Request $request, int $id): Response
    {
        $this->transition($id, ['proposed' => 'decided'], 'dividend.decide');
        flash('success', 'Dividende décidé en assemblée.');
        return new RedirectResponse('/dividends');
    }

    public function pay(Request $request, int $id): Response
    {
        $this->auth()->requirePermission('dividends.write');
        $dividend = $this->requireDividend($id);
        if ($dividend['status'] !== 'decided') {
            throw new AppException('Seul un dividende décidé peut être payé.');
        }
        $this->db->execute(
            "UPDATE dividends SET status = 'paid', paid_on = :paid WHERE id = :id",
            ['paid' => date('Y-m-d'), 'id' => $id]
        );
        audit('dividend.pay', 'Dividend', $id);
        flash('success', 'Paiement du dividende enregistré.');
        return new RedirectResponse('/dividends');
    }

    /** @param array<string,string> $allowed */
    private function transition(int $id, array $allowed, string $auditAction): void
    {
        $this->auth()->requirePermission('dividends.write');
        $dividend = $this->requireDividend($id);
        if (!isset($allowed[$dividend['status']])) {
            throw new AppException('Transition de statut impossible.');
        }
        $this->db->execute(
            'UPDATE dividends SET status = :status WHERE id = :id',
            ['status' => $allowed[$dividend['status']], 'id' => $id]
        );
        audit($auditAction, 'Dividend', $id);
    }

    /**
     * @return array<string,mixed>
     */
    private function requireDividend(int $id): array
    {
        $dividend = $this->db->fetchOne('SELECT * FROM dividends WHERE id = :id', ['id' => $id]);
        if ($dividend === null) {
            throw new AppException('Dividende introuvable.');
        }
        return $dividend;
    }
}
