<?php

declare(strict_types=1);

namespace Modules\Mail\Tests\Functional;

use App\Core\Application;
use App\Core\Encryption\Crypto;
use App\Core\Exception\AppException;
use Modules\Mail\Services\MailAccountService;
use Tests\Core\DatabaseTestCase;

final class MailAccountServiceTest extends DatabaseTestCase
{
    private MailAccountService $service;
    private Crypto $crypto;
    private int $userId;

    protected function setUp(): void
    {
        // Clé de chiffrement propre au test, indépendante d'un .env machine.
        putenv('APP_KEY=' . Crypto::generateKey());
        $_ENV['APP_KEY'] = getenv('APP_KEY');
        parent::setUp();
        $this->service = Application::container()->get(MailAccountService::class);
        $this->crypto = Application::container()->get(Crypto::class);

        $this->db->execute(
            "INSERT INTO users (email, password_hash, first_name, last_name, is_active)
             VALUES ('jean.dupont@primevisionsolutions.fr', 'x', 'Jean', 'Dupont', 1)"
        );
        $this->userId = (int) $this->db->lastInsertId();
    }

    /**
     * @return array<string,mixed>
     */
    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'user_id' => $this->userId,
            'email' => 'jean.dupont@primevisionsolutions.fr',
            'display_name' => 'Jean Dupont',
            'imap_host' => 'imap.ionos.fr',
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'imap_username' => 'jean.dupont@primevisionsolutions.fr',
            'imap_password' => 'ionos-imap-secret',
            'smtp_host' => 'smtp.ionos.fr',
            'smtp_port' => 465,
            'smtp_encryption' => 'ssl',
            'smtp_username' => 'jean.dupont@primevisionsolutions.fr',
            'smtp_password' => 'ionos-smtp-secret',
            'is_active' => 1,
        ];
    }

    public function testCreateEncryptsSecretsAtRest(): void
    {
        $id = $this->service->create($this->payload(), 1);

        $row = $this->db->fetchOne('SELECT * FROM mail_accounts WHERE id = :id', ['id' => $id]);
        self::assertStringContainsString('enc::', (string) $row['imap_secret']);
        self::assertStringContainsString('enc::', (string) $row['smtp_secret']);
        // Le secret en clair ne doit jamais être stocké.
        self::assertFalse(str_contains((string) $row['imap_secret'], 'ionos-imap-secret'));
        self::assertFalse(str_contains((string) $row['smtp_secret'], 'ionos-smtp-secret'));

        // Les mots de passe sont déchiffrables et intacts.
        self::assertSame('ionos-imap-secret', $this->service->imapPassword($row));
        self::assertSame('ionos-smtp-secret', $this->service->smtpPassword($row));
    }

    public function testUserCanHaveOnlyOneMailbox(): void
    {
        $this->service->create($this->payload(), 1);
        $this->expectException(AppException::class);
        $this->service->create($this->payload(['email' => 'autre@primevisionsolutions.fr']), 1);
    }

    public function testEmailCannotBeConfiguredTwice(): void
    {
        $this->service->create($this->payload(), 1);
        $this->db->execute(
            "INSERT INTO users (email, password_hash, first_name, last_name)
             VALUES ('marie@primevisionsolutions.fr', 'x', 'Marie', 'Curie')"
        );
        $otherId = (int) $this->db->lastInsertId();

        $this->expectException(AppException::class);
        $this->service->create($this->payload(['user_id' => $otherId]), 1);
    }

    public function testCreateRequiresBothPasswords(): void
    {
        $this->expectException(AppException::class);
        $this->service->create($this->payload(['imap_password' => '']), 1);
    }

    public function testUpdateWithoutPasswordKeepsExistingSecret(): void
    {
        $id = $this->service->create($this->payload(), 1);
        $before = $this->db->fetchOne('SELECT imap_secret, smtp_secret FROM mail_accounts WHERE id = :id', ['id' => $id]);

        $this->service->update($id, $this->payload(['imap_password' => '', 'smtp_password' => '', 'display_name' => 'Jean D.']), 1);

        $after = $this->db->fetchOne('SELECT * FROM mail_accounts WHERE id = :id', ['id' => $id]);
        self::assertSame($before['imap_secret'], $after['imap_secret']);
        self::assertSame($before['smtp_secret'], $after['smtp_secret']);
        self::assertSame('Jean D.', $after['display_name']);
        self::assertSame('ionos-imap-secret', $this->service->imapPassword($after));
    }

    public function testUpdateRotatesProvidedSecret(): void
    {
        $id = $this->service->create($this->payload(), 1);
        $this->service->update($id, $this->payload(['imap_password' => 'nouveau-secret', 'smtp_password' => '']), 1);

        $after = $this->db->fetchOne('SELECT * FROM mail_accounts WHERE id = :id', ['id' => $id]);
        self::assertSame('nouveau-secret', $this->service->imapPassword($after));
        self::assertSame('ionos-smtp-secret', $this->service->smtpPassword($after));
    }

    public function testInvalidEncryptionIsRejected(): void
    {
        $this->expectException(AppException::class);
        $this->service->create($this->payload(['imap_encryption' => 'weird']), 1);
    }

    public function testAdminListNeverExposesSecrets(): void
    {
        $this->service->create($this->payload(), 1);
        $rows = $this->service->adminList();
        self::assertCount(1, $rows);
        self::assertArrayHasKey('user_email', $rows[0]);
        self::assertFalse(array_key_exists('imap_secret', $rows[0]));
        self::assertFalse(array_key_exists('smtp_secret', $rows[0]));
    }

    public function testDeleteRemovesAccount(): void
    {
        $id = $this->service->create($this->payload(), 1);
        $this->service->delete($id, 1);
        self::assertSame(0, (int) $this->db->fetchScalar('SELECT COUNT(*) FROM mail_accounts WHERE id = :id', ['id' => $id]));
    }

    public function testActiveModelAndRequireRowScopeByUser(): void
    {
        $id = $this->service->create($this->payload(), 1);
        $model = $this->service->activeModelForUser($this->userId);
        self::assertNotNull($model);
        self::assertSame($id, $model->id);
        self::assertSame('imap.ionos.fr', $model->imapHost);
        self::assertSame('Jean Dupont <jean.dupont@primevisionsolutions.fr>', $model->senderLabel());

        $row = $this->service->requireActiveRowForUser($this->userId);
        self::assertSame($id, (int) $row['id']);

        $this->expectException(AppException::class);
        $this->service->requireActiveRowForUser(999999);
    }
}
