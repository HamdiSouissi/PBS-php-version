<?php

declare(strict_types=1);

namespace Database\Seeds;

use App\Core\Container;
use App\Core\Database\Connection;
use App\Core\Security\Password;

/**
 * Rôles, permissions granulaires (RBAC) et administrateur initial.
 */
final class RbacSeeder
{
    /**
     * Matrice des permissions par module.
     */
    public const PERMISSIONS = [
        'organization' => ['organization.read', 'organization.write'],
        'users' => ['users.read', 'users.write'],
        'employees' => ['employees.read', 'employees.write', 'employees.delete'],
        'hr' => ['hr.leaves.read', 'hr.leaves.request', 'hr.leaves.approve'],
        'payroll' => ['payroll.read', 'payroll.run', 'payroll.validate', 'payroll.lock'],
        'accounting' => ['accounting.read', 'accounting.write', 'accounting.close'],
        'treasury' => ['treasury.read', 'treasury.write'],
        'invoicing' => ['invoicing.read', 'invoicing.write', 'invoicing.validate', 'invoicing.payments', 'invoicing.einvoice'],
        'clients' => ['clients.read', 'clients.write'],
        'contracts' => ['contracts.read', 'contracts.write'],
        'subscriptions' => ['subscriptions.read', 'subscriptions.write'],
        'expenses' => ['expenses.read', 'expenses.request', 'expenses.approve', 'expenses.pay'],
        'dividends' => ['dividends.read', 'dividends.write'],
        'compliance' => ['compliance.read', 'compliance.write'],
        'documents' => ['documents.read', 'documents.write'],
        'mail' => ['mail.read', 'mail.configure'],
        'chat' => ['chat.use'],
        'ai' => ['ai.use', 'ai.settings'],
        'system' => ['settings.manage', 'audit.read', 'backups.manage'],
        'portal' => ['portal.self'],
    ];

    public function __construct(private readonly Container $container)
    {
    }

    public function run(): void
    {
        $db = $this->container->get(Connection::class);

        // Permissions.
        foreach (self::PERMISSIONS as $module => $codes) {
            foreach ($codes as $code) {
                $db->insertIgnore(
                    'permissions',
                    ['code' => $code, 'label' => ucfirst(str_replace('.', ' ', $code)), 'module' => $module],
                    ['code']
                );
            }
        }
        // Permission super-administrateur.
        $db->insertIgnore(
            'permissions',
            ['code' => '*', 'label' => 'Toutes permissions', 'module' => 'system'],
            ['code']
        );

        $roles = [
            'admin' => ['Administrateur', 'Accès complet à l\'application', ['*']],
            'rh' => ['Responsable RH', 'Employés, contrats, congés et documents RH', array_merge($this->modulePermissions(['organization','users','employees','hr','documents','portal']), ['mail.read', 'chat.use'])],
            'paie' => ['Gestionnaire de paie', 'Moteur de paie, bulletins, DSN', array_merge($this->modulePermissions(['payroll','employees','hr','documents','portal']), ['mail.read', 'chat.use'])],
            'comptable' => ['Comptable', 'Comptabilité, trésorerie, facturation, frais', array_merge($this->modulePermissions(['accounting','treasury','invoicing','clients','expenses','contracts','subscriptions','dividends','compliance','documents','portal']), ['mail.read', 'chat.use'])],
            'employe' => ['Salarié', 'Portail salarié en self-service', ['portal.self', 'hr.leaves.request', 'documents.read', 'mail.read', 'chat.use']],
        ];

        foreach ($roles as $code => [$label, $description, $permissions]) {
            // Idempotent : la ligne est créée si absente, ses libellés mis à jour sinon.
            $db->upsert(
                'roles',
                ['code' => $code, 'label' => $label, 'description' => $description],
                ['code'],
                ['label', 'description']
            );
            $roleId = (int) $db->fetchScalar('SELECT id FROM roles WHERE code = :c', ['c' => $code]);
            foreach ($permissions as $permissionCode) {
                $permissionId = $db->fetchScalar('SELECT id FROM permissions WHERE code = :c', ['c' => $permissionCode]);
                if ($permissionId === false || $permissionId === null) {
                    continue;
                }
                $db->insertIgnore(
                    'role_permission',
                    ['role_id' => $roleId, 'permission_id' => (int) $permissionId],
                    ['role_id', 'permission_id']
                );
            }
        }

        // Administrateur initial (mot de passe forcé au changement à la première connexion).
        $email = 'admin@prime-vision-solutions.fr';
        $existing = $db->fetchScalar('SELECT id FROM users WHERE email = :e', ['e' => $email]);
        if ($existing === false || $existing === null) {
            $hasher = $this->container->get(Password::class);
            $userId = $db->lastInsertId();
            $db->execute(
                'INSERT INTO users (email, password_hash, first_name, last_name, is_active, must_change_password)
                 VALUES (:e, :p, :f, :l, TRUE, TRUE)',
                [
                    'e' => $email,
                    'p' => $hasher->hash('PBS@2026!admin'),
                    'f' => 'Admin',
                    'l' => 'PBS',
                ]
            );
            $userId = (int) $db->lastInsertId();
            $adminRoleId = (int) $db->fetchScalar('SELECT id FROM roles WHERE code = \'admin\'');
            $db->insertIgnore(
                'role_user',
                ['role_id' => $adminRoleId, 'user_id' => $userId],
                ['role_id', 'user_id']
            );
        }
    }

    /**
     * @param list<string> $modules
     * @return list<string>
     */
    private function modulePermissions(array $modules): array
    {
        $result = [];
        foreach ($modules as $module) {
            foreach (self::PERMISSIONS[$module] ?? [] as $code) {
                $result[] = $code;
            }
        }
        return $result;
    }
}
