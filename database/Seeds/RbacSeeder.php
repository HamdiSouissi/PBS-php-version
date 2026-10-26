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
        'contracts' => ['contracts.read', 'contracts.write'],
        'subscriptions' => ['subscriptions.read', 'subscriptions.write'],
        'expenses' => ['expenses.read', 'expenses.request', 'expenses.approve', 'expenses.pay'],
        'dividends' => ['dividends.read', 'dividends.write'],
        'compliance' => ['compliance.read', 'compliance.write'],
        'documents' => ['documents.read', 'documents.write'],
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
                $db->execute(
                    'INSERT INTO permissions (code, label, module)
                     VALUES (:code, :label, :module)
                     ON CONFLICT (code) DO NOTHING',
                    ['code' => $code, 'label' => ucfirst(str_replace('.', ' ', $code)), 'module' => $module]
                );
            }
        }
        // Permission super-administrateur.
        $db->execute(
            'INSERT INTO permissions (code, label, module) VALUES (\'*\', \'Toutes permissions\', \'system\')
             ON CONFLICT (code) DO NOTHING'
        );

        $roles = [
            'admin' => ['Administrateur', 'Accès complet à l\'application', ['*']],
            'rh' => ['Responsable RH', 'Employés, contrats, congés et documents RH', $this->modulePermissions(['organization','users','employees','hr','documents','portal'])],
            'paie' => ['Gestionnaire de paie', 'Moteur de paie, bulletins, DSN', $this->modulePermissions(['payroll','employees','hr','documents','portal'])],
            'comptable' => ['Comptable', 'Comptabilité, trésorerie, facturation, frais', $this->modulePermissions(['accounting','treasury','invoicing','expenses','contracts','subscriptions','dividends','compliance','documents','portal'])],
            'employe' => ['Salarié', 'Portail salarié en self-service', ['portal.self', 'hr.leaves.request', 'documents.read']],
        ];

        foreach ($roles as $code => [$label, $description, $permissions]) {
            $db->execute(
                'INSERT INTO roles (code, label, description) VALUES (:c, :l, :d)
                 ON CONFLICT (code) DO UPDATE SET label = EXCLUDED.label, description = EXCLUDED.description',
                ['c' => $code, 'l' => $label, 'd' => $description]
            );
            $roleId = (int) $db->fetchScalar('SELECT id FROM roles WHERE code = :c', ['c' => $code]);
            foreach ($permissions as $permissionCode) {
                $permissionId = $db->fetchScalar('SELECT id FROM permissions WHERE code = :c', ['c' => $permissionCode]);
                if ($permissionId === false || $permissionId === null) {
                    continue;
                }
                $db->execute(
                    'INSERT INTO role_permission (role_id, permission_id) VALUES (:r, :p)
                     ON CONFLICT DO NOTHING',
                    ['r' => $roleId, 'p' => (int) $permissionId]
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
            $db->execute(
                'INSERT INTO role_user (role_id, user_id) VALUES (:r, :u) ON CONFLICT DO NOTHING',
                ['r' => $adminRoleId, 'u' => $userId]
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
