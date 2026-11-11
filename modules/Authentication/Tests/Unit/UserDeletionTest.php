<?php

declare(strict_types=1);

namespace Modules\Authentication\Tests\Unit;

use App\Core\Application;
use App\Core\Exception\AppException;
use App\Core\Exception\AuthorizationException;
use App\Core\Exception\NotFoundException;
use Modules\Authentication\Repositories\UserRepository;
use Modules\Authentication\Services\UserService;
use Tests\Core\DatabaseTestCase;

final class UserDeletionTest extends DatabaseTestCase
{
    public function testDeletionRevokesAccessAndPreservesHistory(): void
    {
        $admin = (int) $this->db->fetchScalar("SELECT id FROM users WHERE email = 'admin@prime-vision-solutions.fr'");
        $this->db->execute("INSERT INTO users (email, password_hash, first_name, last_name, is_active, totp_secret, totp_enrolled) VALUES ('delete@example.test', 'old-hash', 'Jean', 'Test', 1, 'SECRET', 1)");
        $id = (int) $this->db->lastInsertId();
        $role = (int) $this->db->fetchScalar("SELECT id FROM roles WHERE code = 'employe'");
        $this->db->execute('INSERT INTO role_user (role_id, user_id) VALUES (:role, :id)', ['role' => $role, 'id' => $id]);
        $this->db->execute("INSERT INTO remember_tokens (user_id, token_hash, expires_at) VALUES (:id, 'remember', '2099-01-01')", ['id' => $id]);
        $this->db->execute("INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (:id, 'reset', '2099-01-01')", ['id' => $id]);
        $this->db->execute("INSERT INTO api_two_factor_challenges (user_id, token_hash, password_fingerprint, expires_at) VALUES (:id, 'challenge', 'fingerprint', '2099-01-01')", ['id' => $id]);
        $this->db->execute("INSERT INTO mail_accounts (user_id, email, imap_host, imap_username, imap_secret, smtp_host, smtp_username, smtp_secret) VALUES (:id, 'delete@example.test', 'imap.test', 'login', 'secret', 'smtp.test', 'login', 'secret')", ['id' => $id]);
        $this->db->execute("INSERT INTO companies (legal_name, siren, siret, legal_form, city, postal_code) VALUES ('Test SAS', '123456789', '12345678901234', 'SAS', 'Paris', '75001')");
        $company = (int) $this->db->lastInsertId();
        $this->db->execute("INSERT INTO employees (company_id, employee_number, last_name, first_name, user_id) VALUES (:company, 'EMP-DEL', 'Test', 'Jean', :id)", ['company' => $company, 'id' => $id]);
        $this->db->execute("INSERT INTO chat_conversations (type, name, created_by) VALUES ('group', 'Historique', :id)", ['id' => $id]);
        $conversation = (int) $this->db->lastInsertId();
        $this->db->execute("INSERT INTO chat_messages (conversation_id, sender_id, body) VALUES (:conversation, :id, 'Message conservé')", ['conversation' => $conversation, 'id' => $id]);
        $service = Application::container()->get(UserService::class);
        $service->deleteUser($id, $admin);
        $row = $this->db->fetchOne('SELECT * FROM users WHERE id = :id', ['id' => $id]);
        self::assertNotNull($row['deleted_at']);
        self::assertSame(0, (int) $row['is_active']);
        self::assertNotSame('delete@example.test', $row['email']);
        self::assertNotSame('old-hash', $row['password_hash']);
        self::assertNull($row['totp_secret']);
        foreach (['role_user', 'remember_tokens', 'password_resets', 'api_two_factor_challenges', 'mail_accounts'] as $table) {
            self::assertSame(0, (int) $this->db->fetchScalar('SELECT COUNT(*) FROM ' . $table . ' WHERE user_id = :id', ['id' => $id]));
        }
        self::assertSame(1, (int) $this->db->fetchScalar('SELECT COUNT(*) FROM chat_messages WHERE sender_id = :id', ['id' => $id]));
        self::assertSame(1, (int) $this->db->fetchScalar('SELECT COUNT(*) FROM employees WHERE employee_number = :number AND user_id IS NULL', ['number' => 'EMP-DEL']));
        $repo = Application::container()->get(UserRepository::class);
        self::assertNull($repo->findWithRights($id));
        self::assertNull($repo->findByEmail('delete@example.test'));
        $this->db->execute("INSERT INTO users (email, password_hash, first_name, last_name) VALUES ('delete@example.test', 'new-hash', 'Nouveau', 'Compte')");
        self::assertNotNull($repo->findByEmail('delete@example.test'));
        $this->expectException(NotFoundException::class);
        $service->updateUser($id, ['is_active' => 1], [$role]);
    }

    public function testCannotDeleteOwnAccount(): void
    {
        $admin = (int) $this->db->fetchScalar("SELECT id FROM users WHERE email = 'admin@prime-vision-solutions.fr'");
        $this->expectException(AppException::class);
        Application::container()->get(UserService::class)->deleteUser($admin, $admin);
    }

    public function testNonAdminCannotDeleteAccount(): void
    {
        $admin = (int) $this->db->fetchScalar("SELECT id FROM users WHERE email = 'admin@prime-vision-solutions.fr'");
        $this->db->execute("INSERT INTO users (email, password_hash) VALUES ('other@example.test', 'hash')");
        $id = (int) $this->db->lastInsertId();
        $this->expectException(AuthorizationException::class);
        Application::container()->get(UserService::class)->deleteUser($admin, $id);
    }
}
