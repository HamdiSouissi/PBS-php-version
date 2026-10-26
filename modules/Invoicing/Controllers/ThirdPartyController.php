<?php

declare(strict_types=1);

namespace Modules\Invoicing\Controllers;

use App\Core\Database\Connection;
use App\Core\Http\Controller;
use App\Core\Http\RedirectResponse;
use App\Core\Http\Request;
use App\Core\Http\Response;

/**
 * Gestion des tiers (clients / fournisseurs).
 */
final class ThirdPartyController extends Controller
{
    public function __construct(private readonly Connection $db)
    {
    }

    public function index(Request $request): Response
    {
        $this->auth()->requirePermission('invoicing.read');
        $type = (string) $request->query('type', 'client');
        $search = trim((string) $request->query('q', ''));
        $sql = "SELECT * FROM third_parties WHERE 1=1";
        $params = [];
        if ($type !== '') {
            $sql .= ' AND (type = :type OR type = :both)';
            $params['type'] = $type;
            $params['both'] = 'both';
        }
        if ($search !== '') {
            $sql .= ' AND (name ILIKE :q OR code ILIKE :q OR siret ILIKE :q)';
            $params['q'] = '%' . $search . '%';
        }
        $sql .= ' ORDER BY name LIMIT 200';
        return $this->view('Invoicing::third_parties/index', [
            'title' => 'Tiers',
            'parties' => $this->db->fetchAll($sql, $params),
            'type' => $type,
            'q' => $search,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->auth()->requirePermission('invoicing.write');
        return $this->view('Invoicing::third_parties/form', [
            'title' => 'Nouveau tiers',
            'party' => null,
        ]);
    }

    public function store(Request $request): Response
    {
        $this->auth()->requirePermission('invoicing.write');
        $data = $this->validator()->validate($request->all(), [
            'type' => 'required|in:client,supplier,both',
            'name' => 'required|string|max:180',
            'legal_form' => 'nullable|string|max:40',
            'siren' => 'nullable|sirene',
            'siret' => 'nullable|siret',
            'vat_number' => 'nullable|string|max:20',
            'address' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'city' => 'nullable|string|max:120',
            'email' => 'nullable|email',
            'phone' => 'nullable|string|max:40',
            'payment_terms_days' => 'nullable|integer',
            'payment_method' => 'nullable|string|max:40',
        ]);

        $code = $this->nextCode((string) $data['type']);
        $this->db->execute(
            'INSERT INTO third_parties (type, code, name, legal_form, siren, siret, vat_number, address, postal_code,
                                        city, email, phone, payment_terms_days, payment_method)
             VALUES (:type, :code, :name, :legal_form, :siren, :siret, :vat, :address, :pc, :city,
                     :email, :phone, :terms, :method)',
            [
                'type' => $data['type'],
                'code' => $code,
                'name' => $data['name'],
                'legal_form' => $data['legal_form'] ?? '',
                'siren' => $data['siren'] ?? '',
                'siret' => $data['siret'] ?? '',
                'vat' => $data['vat_number'] ?? '',
                'address' => $data['address'] ?? '',
                'pc' => $data['postal_code'] ?? '',
                'city' => $data['city'] ?? '',
                'email' => $data['email'] ?? '',
                'phone' => $data['phone'] ?? '',
                'terms' => (int) ($data['payment_terms_days'] ?? 30),
                'method' => $data['payment_method'] ?? 'virement',
            ]
        );
        $id = (int) $this->db->lastInsertId();
        audit('third_party.create', 'ThirdParty', $id, ['name' => $data['name']]);
        flash('success', 'Tiers enregistré.');
        return new RedirectResponse('/invoicing/third-parties');
    }

    private function nextCode(string $type): string
    {
        $prefix = match ($type) {
            'supplier' => 'FRS',
            'both' => 'TIE',
            default => 'CLI',
        };
        $count = (int) $this->db->fetchScalar(
            'SELECT COUNT(*) FROM third_parties WHERE code LIKE :prefix',
            ['prefix' => $prefix . '-%']
        );
        return sprintf('%s-%04d', $prefix, $count + 1);
    }
}
