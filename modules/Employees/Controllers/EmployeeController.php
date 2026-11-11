<?php

declare(strict_types=1);

namespace Modules\Employees\Controllers;

use App\Core\Http\Controller;
use App\Core\Http\JsonResponse;
use App\Core\Http\RedirectResponse;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Pdf\PdfManager;
use Modules\Employees\Repositories\EmployeeRepository;
use Modules\Employees\Services\ContractService;
use Modules\Employees\Services\EmployeeService;
use Modules\Employees\Services\EmployeeAccountAdministrationService;
use Modules\Authentication\Services\PasswordResetMailService;
use App\Core\Exception\AppException;
use Modules\HR\Services\LeaveBalanceService;
use Modules\Organization\Services\OrganizationService;

final class EmployeeController extends Controller
{
    /** Colonnes DATE / entier clé étrangère : une chaîne vide doit devenir NULL. */
    private const NULLABLE_COLUMNS = ['birth_date', 'hire_date', 'end_date', 'department_id', 'establishment_id'];

    public function __construct(
        private readonly EmployeeService $employees,
        private readonly EmployeeRepository $repository,
        private readonly ContractService $contracts,
        private readonly OrganizationService $organization,
        private readonly PdfManager $pdf,
        private readonly LeaveBalanceService $leaveBalances
    ) {
    }

    public function index(Request $request): Response
    {
        $this->auth()->requirePermission('employees.read');
        $company = $this->organization->defaultCompany();
        $items = $company === null ? [] : $this->repository->search(
            (int) $company['id'],
            (string) $request->query('q', ''),
            (string) $request->query('status', '')
        );
        return $this->view('Employees::index', [
            'title' => 'Employés',
            'employees' => $items,
            'q' => $request->query('q', ''),
            'status' => $request->query('status', ''),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->auth()->requirePermission('employees.write');
        $company = $this->organization->defaultCompany();
        return $this->view('Employees::form', [
            'title' => 'Nouvel employé',
            'employee' => null,
            'departments' => $company ? $this->organization->departments((int) $company['id']) : [],
            'establishments' => $company ? $this->organization->establishments((int) $company['id']) : [],
        ]);
    }

    public function store(Request $request): Response
    {
        $this->auth()->requirePermission('employees.write');
        $data = $this->validateEmployee($request, true);
        $company = $this->organization->defaultCompany();
        $id = $this->employees->create((int) $company['id'], $data);
        flash('success', 'Salarié créé. Un administrateur peut créer son compte depuis sa fiche et lui envoyer une invitation.');
        return new RedirectResponse('/employees/' . $id);
    }

    public function show(Request $request, int $id): Response
    {
        $this->auth()->requirePermission('employees.read');
        $employee = $this->employees->getOrFail($id);
        $contracts = $this->repository->contracts($id);
        // Dernier PDF généré pour chaque contrat (aperçu/téléchargement).
        $contractPdfs = [];
        foreach ($contracts as $contract) {
            $contractPdfs[(int) $contract['id']] = $this->pdf->latestFor('employee_contract', (int) $contract['id']);
        }
        $canCreateAccount = $this->auth()->hasRole('admin') && empty($employee['user_id']);
        $accountAdministration = \App\Core\Application::container()->get(EmployeeAccountAdministrationService::class);
        $existingAccount = null;
        if ($canCreateAccount && $request->query('account_exists') === '1') {
            try {
                $existingAccount = $accountAdministration->existing($id);
            } catch (AppException $exception) {
                flash('warning', $exception->getMessage());
            }
        }
        return $this->view('Employees::show', [
            'title' => $employee['first_name'] . ' ' . $employee['last_name'],
            'employee' => $employee,
            'contracts' => $contracts,
            'activeContract' => $this->repository->activeContract($id),
            // CORRECTIF : $this->db() n'existe pas dans Controller (erreur fatale) ;
            // on utilise le helper global db().
            'documents' => db()->fetchAll(
                'SELECT * FROM employee_documents WHERE employee_id = :e ORDER BY created_at DESC',
                ['e' => $id]
            ),
            'contractPdfs' => $contractPdfs,
            'sheetPdf' => $this->pdf->latestFor('employee_sheet', $id),
            'generatedPdfs' => db()->fetchAll(
                'SELECT * FROM documents
                 WHERE employee_id = :e
                 ORDER BY id DESC LIMIT 20',
                ['e' => $id]
            ),
            // Compte de connexion rattaché (e-mail + état du changement de mot de passe).
            'account' => $employee['user_id']
                ? db()->fetchOne('SELECT id, email, must_change_password FROM users WHERE id = :u', ['u' => $employee['user_id']])
                : null,
            'leaveSummary' => $this->leaveBalances->summary($id),
            'canEditContact' => $this->canEditContact() && ($this->auth()->hasRole('admin') || !$this->isAdminAccount($employee)),
            'canSendReset' => $this->auth()->hasRole('admin') && !empty($employee['user_id']),
            'canCreateAccount' => $canCreateAccount,
            'accountRoles' => $canCreateAccount ? $accountAdministration->roles() : [],
            'existingAccount' => $existingAccount,
        ]);
    }

    private function canEditContact(): bool
    {
        return $this->auth()->hasPermission('employees.write')
            && ($this->auth()->hasRole('admin') || $this->auth()->hasRole('rh'));
    }

    private function requireContactEditor(): void
    {
        if (!$this->canEditContact()) {
            throw new \App\Core\Exception\AuthorizationException('Réservé aux administrateurs et RH.');
        }
    }

    public function edit(Request $request, int $id): Response
    {
        $this->requireContactEditor();
        $employee = $this->employees->getOrFail($id);
        $this->protectAdminAccount($employee);
        return $this->view('Employees::form', [
            'title' => 'Modifier le salarié',
            'employee' => $employee,
            'departments' => $this->organization->departments((int) $employee['company_id']),
            'establishments' => $this->organization->establishments((int) $employee['company_id']),
        ]);
    }

    private function isAdminAccount(array $employee): bool
    {
        return !empty($employee['user_id']) && db()->fetchOne(
            "SELECT ru.user_id FROM role_user ru JOIN roles r ON r.id = ru.role_id WHERE ru.user_id = :id AND r.code = 'admin'",
            ['id' => $employee['user_id']]
        ) !== null;
    }

    private function protectAdminAccount(array $employee): void
    {
        if (!$this->auth()->hasRole('admin') && $this->isAdminAccount($employee)) {
            throw new \App\Core\Exception\AuthorizationException('Seul un administrateur peut modifier la fiche liée à un compte administrateur.');
        }
    }

    // Compatibilité avec le formulaire de coordonnées précédemment publié.
    public function editContact(Request $request, int $id): Response
    {
        $this->requireContactEditor();
        $this->protectAdminAccount($this->employees->getOrFail($id));
        return new RedirectResponse('/employees/' . $id . '/edit');
    }

    public function updateContact(Request $request, int $id): Response
    {
        $this->requireContactEditor();
        $this->protectAdminAccount($this->employees->getOrFail($id));
        $data = $this->validator()->validate($request->all(), [
            'personal_email' => 'nullable|string|email|max:180',
            'professional_email' => 'nullable|string|email|max:180',
            'phone' => 'nullable|string|max:40',
            'address' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'city' => 'nullable|string|max:120',
            'iban' => 'nullable|string|iban',
            'bic' => 'nullable|string|max:11',
        ]);
        $this->employees->update($id, array_map(static fn ($value) => $value ?? '', $data));
        flash('success', 'Coordonnées mises à jour.');
        return new RedirectResponse('/employees/' . $id);
    }

    public function sendPasswordReset(Request $request, int $id): Response
    {
        if (!$this->auth()->hasRole('admin')) {
            throw new \App\Core\Exception\AuthorizationException('Réservé aux administrateurs.');
        }
        $employee = $this->employees->getOrFail($id);
        if (empty($employee['user_id'])) {
            flash('danger', 'Ce salarié ne possède pas de compte de connexion.');
            return new RedirectResponse('/employees/' . $id);
        }
        $service = \App\Core\Application::container()->get(\Modules\Authentication\Services\PasswordResetMailService::class);
        $result = $service->sendForEmployee((int) $employee['user_id'], $employee);
        if ($result['sent'] === 0) {
            flash('danger', 'Aucun e-mail n’a été envoyé. Vérifiez le compte, les adresses et la configuration SMTP.');
        } elseif ($result['sent'] < $result['total']) {
            flash('warning', 'Le lien a été envoyé à une seule adresse. Vérifiez la configuration SMTP et les adresses de la fiche.');
        } else {
            flash('success', 'Le lien de réinitialisation a été envoyé aux adresses disponibles du salarié.');
        }
        audit('employee.password_reset_requested', 'Employee', $id, ['sent' => $result['sent'], 'total' => $result['total']]);
        return new RedirectResponse('/employees/' . $id);
    }

    private function requireAccountAdministrator(): void
    {
        if (!$this->auth()->hasRole('admin')) {
            throw new \App\Core\Exception\AuthorizationException('Réservé aux administrateurs.');
        }
    }

    public function createAccount(Request $request, int $id): Response
    {
        $this->requireAccountAdministrator();
        $data = $this->validator()->validate($request->all(), ['role_id' => 'required|integer']);
        $accounts = \App\Core\Application::container()->get(EmployeeAccountAdministrationService::class);
        try {
            $result = $accounts->create($id, (int) $data['role_id']);
        } catch (AppException $exception) {
            flash('danger', $exception->getMessage());
            return new RedirectResponse('/employees/' . $id);
        }
        if ($result['status'] === 'exists') {
            return new RedirectResponse('/employees/' . $id . '?account_exists=1');
        }
        $email = (string) $result['employee']['professional_email'];
        $sent = \App\Core\Application::container()->get(PasswordResetMailService::class)
            ->sendToAccount((int) $result['user_id'], $email, true);
        flash($sent ? 'success' : 'warning', $sent
            ? 'Compte créé. Un e-mail contenant l’identifiant et le lien de création du mot de passe a été envoyé à l’adresse professionnelle.'
            : 'Compte créé, mais l’e-mail n’a pas pu être envoyé. Vérifiez le SMTP puis utilisez le bouton de réinitialisation.');
        return new RedirectResponse('/employees/' . $id);
    }

    public function resetExistingAccount(Request $request, int $id): Response
    {
        $this->requireAccountAdministrator();
        $accounts = \App\Core\Application::container()->get(EmployeeAccountAdministrationService::class);
        try {
            $existing = $accounts->existing($id);
            if ($existing['linked_elsewhere']) {
                throw new AppException('Ce compte est déjà rattaché à un autre salarié. Vérifiez la fiche avant de continuer.');
            }
            if (!$existing['user']->isActive) {
                throw new AppException('Ce compte est inactif. Réactivez-le avant de demander un lien.');
            }
            $sent = \App\Core\Application::container()->get(PasswordResetMailService::class)
                ->sendToAccount($existing['user']->id, $existing['user']->email);
            audit('employee.existing_account_reset_requested', 'Employee', $id, ['user_id' => $existing['user']->id, 'sent' => $sent]);
            flash($sent ? 'success' : 'danger', $sent
                ? 'Lien de réinitialisation envoyé à l’adresse de connexion professionnelle. Le compte n’a pas été rattaché automatiquement.'
                : 'L’envoi a échoué. Vérifiez la configuration SMTP.');
        } catch (AppException $exception) {
            flash('danger', $exception->getMessage());
        }
        return new RedirectResponse('/employees/' . $id);
    }

    public function linkAccount(Request $request, int $id): Response
    {
        $this->requireAccountAdministrator();
        try {
            \App\Core\Application::container()->get(EmployeeAccountAdministrationService::class)->linkExisting($id);
            flash('success', 'Compte existant rattaché. Ses rôles et son mot de passe restent inchangés.');
        } catch (AppException $exception) {
            flash('danger', $exception->getMessage());
        }
        return new RedirectResponse('/employees/' . $id);
    }

    public function update(Request $request, int $id): Response
    {
        $this->auth()->requirePermission('employees.write');
        $this->requireContactEditor();
        $this->protectAdminAccount($this->employees->getOrFail($id));
        $data = $this->validateEmployee($request);
        $this->employees->update($id, $data);
        flash('success', 'Salarié mis à jour.');
        return new RedirectResponse('/employees/' . $id);
    }

    public function terminate(Request $request, int $id): Response
    {
        $this->auth()->requirePermission('employees.write');
        $data = $this->validator()->validate($request->all(), [
            'termination_date' => 'required|date',
            'termination_reason' => 'required|string|max:120',
        ]);
        $this->employees->terminate($id, (string) $data['termination_date'], (string) $data['termination_reason']);
        flash('warning', 'Sortie enregistrée : contrat clôturé et compte lié désactivé immédiatement.');
        return new RedirectResponse('/employees/' . $id);
    }

    public function enablePortal(Request $request, int $id): JsonResponse
    {
        $this->auth()->requirePermission('employees.write');
        $token = $this->employees->enablePortal($id);
        return json_response(['success' => true, 'portal_token' => $token]);
    }

    public function storeContract(Request $request, int $id): Response
    {
        $this->auth()->requirePermission('employees.write');
        $data = $this->validator()->validate($request->all(), [
            'type' => 'required|in:CDI,CDD,apprentissage,professionnalisation,stage,alternance,interim',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date',
            'monthly_gross' => 'required|decimal:2',
            'weekly_hours' => 'required|decimal:2',
            'status' => 'required|in:draft,active',
            // CORRECTIF : ces champs du formulaire étaient ignorés (absents des règles).
            'position' => 'nullable|string|max:120',
            'classification' => 'nullable|string|max:120',
        ]);
        // CORRECTIF : « end_date » vide (CDI) était envoyé comme '' à une colonne DATE (erreur SQL).
        $data = $this->nullifyEmpty($data);
        $contractId = $this->contracts->create($id, $data, $this->auth()->id());
        $this->safePdf('employee_contract', $contractId, 'Contrat de travail');
        flash('success', 'Contrat enregistré et document PDF généré.');
        return new RedirectResponse('/employees/' . $id);
    }

    /** Régénère le PDF d'un contrat de travail. */
    public function regenerateContractPdf(Request $request, int $id, int $contractId): Response
    {
        $this->auth()->requirePermission('employees.write');
        $this->safePdf('employee_contract', $contractId, 'Contrat de travail');
        return new RedirectResponse('/employees/' . $id);
    }

    /** Génère la fiche synthétique PDF du salarié. */
    public function employeeSheetPdf(Request $request, int $id): Response
    {
        $this->auth()->requirePermission('employees.read');
        $this->safePdf('employee_sheet', $id, 'Fiche salarié');
        return new RedirectResponse('/employees/' . $id);
    }

    /** Génération PDF tolérante : une anomalie ne casse pas l'action métier. */
    private function safePdf(string $kind, int $entityId, string $label): void
    {
        try {
            $this->pdf->generate($kind, $entityId, $this->auth()->id());
            flash('success', $label . ' PDF disponible dans le centre documentaire.');
        } catch (\Throwable $exception) {
            logger()->error('Génération PDF impossible', ['kind' => $kind, 'id' => $entityId, 'error' => $exception->getMessage()]);
            flash('error', $label . ' : génération PDF indisponible, réessayez.');
        }
    }

    public function activateContract(Request $request, int $id, int $contractId): Response
    {
        $this->auth()->requirePermission('employees.write');
        $this->contracts->activate($contractId);
        $this->safePdf('employee_contract', $contractId, 'Contrat de travail');
        flash('success', 'Contrat activé (les autres contrats actifs sont clôturés).');
        return new RedirectResponse('/employees/' . $id);
    }

    /**
     * @return array<string,mixed>
     */
    private function validateEmployee(Request $request, bool $emailRequired = false): array
    {
        $data = $this->validator()->validate($request->all(), [
            'last_name' => 'required|string|max:100',
            'first_name' => 'required|string|max:100',
            'birth_date' => 'nullable|date',
            'birth_place' => 'nullable|string|max:120',
            'gender' => 'nullable|in:m,f,non_renseigne',
            'nationality' => 'nullable|string|max:60',
            'personal_email' => 'nullable|string|email|max:180',
            // Obligatoire à l'embauche : il sert d'identifiant au compte créé.
            'professional_email' => ($emailRequired ? 'required' : 'nullable') . '|string|email|max:180',
            'phone' => 'nullable|string|max:40',
            'social_security_number' => 'nullable|string|max:15',
            'job_title' => 'nullable|string|max:120',
            'qualification' => 'nullable|string|max:120',
            'coefficient' => 'nullable|string|max:30',
            'hire_date' => 'nullable|date',
            'status' => 'nullable|in:candidate,active,on_leave,suspended,terminated',
            'department_id' => 'nullable|integer',
            'establishment_id' => 'nullable|integer',
            'address' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'city' => 'nullable|string|max:120',
            'iban' => 'nullable|iban',
            'bic' => 'nullable|string|max:11',
        ], ['last_name' => 'nom', 'first_name' => 'prénom']);

        $data = $this->nullifyEmpty($data);

        // Colonnes texte NOT NULL : jamais de NULL. Colonnes à valeur par défaut : on laisse la valeur par défaut.
        foreach ($data as $field => $value) {
            if ($value === null && !in_array($field, self::NULLABLE_COLUMNS, true)) {
                if (in_array($field, ['status', 'gender'], true)) {
                    unset($data[$field]);
                } else {
                    $data[$field] = '';
                }
            }
        }
        return $data;
    }

    /**
     * Transforme les chaînes vides en NULL pour les colonnes DATE et clés étrangères.
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function nullifyEmpty(array $data): array
    {
        foreach (self::NULLABLE_COLUMNS as $field) {
            if (array_key_exists($field, $data) && ($data[$field] === '' || $data[$field] === null)) {
                $data[$field] = null;
            }
        }
        return $data;
    }
}
