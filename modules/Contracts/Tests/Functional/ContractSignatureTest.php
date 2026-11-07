<?php

declare(strict_types=1);

namespace Modules\Contracts\Tests\Functional;

use App\Core\Application;
use App\Core\Exception\NotFoundException;
use App\Core\Exception\ValidationException;
use App\Core\Pdf\PdfManager;
use Modules\Authentication\Repositories\UserRepository;
use Modules\Contracts\Services\ContractSignatureService;
use Tests\Core\DatabaseTestCase;

final class ContractSignatureTest extends DatabaseTestCase
{
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }
    }

    private function setupContract(): array
    {
        $this->db->execute("INSERT INTO third_parties (type, code, name) VALUES ('client','SIGN','Client')");
        $party = (int) $this->db->lastInsertId();
        $this->db->execute("INSERT INTO client_contracts (third_party_id, contract_number, title, starts_on) VALUES (:party, 'SIG-1', 'Prestation', '2026-01-01')", ['party' => $party]);
        $id = (int) $this->db->lastInsertId();
        $dir = storage_path('exports/contrat_client/2026');
        if (!is_dir($dir)) { mkdir($dir, 0775, true); }
        $path = $dir . '/test-signature-' . bin2hex(random_bytes(6)) . '.pdf';
        file_put_contents($path, '%PDF-1.4 contrat original');
        $this->files[] = $path;
        $this->db->execute("INSERT INTO documents (name, original_name, disk, path, mime, size, category, entity_type, entity_id, checksum, visibility) VALUES ('Contrat','test.pdf','exports',:path,'application/pdf',25,'contrat_client','client_contract',:id,:sha,'company')", ['path' => 'contrat_client/2026/' . basename($path), 'id' => $id, 'sha' => hash_file('sha256', $path)]);
        $doc = (int) $this->db->lastInsertId();
        $provider = Application::container()->get(UserRepository::class)->findWithRights((int) $this->db->fetchScalar("SELECT id FROM users WHERE email = 'admin@prime-vision-solutions.fr'"));
        return [$id, $doc, $provider, $path];
    }

    private function service(): ContractSignatureService
    {
        return new ContractSignatureService($this->db, new PdfManager($this->db));
    }

    private function start(): array
    {
        [$id, $doc, $provider, $path] = $this->setupContract();
        $result = $this->service()->start($id, $doc, $provider, 'Client Exemple', 'client@example.org', '127.0.0.1', 'test');
        $row = $this->service()->byToken($result['token']);
        $this->files[] = storage_path('contract-signatures/' . $row['snapshot_name']);
        return [$id, $doc, $provider, $path, $result, $row];
    }

    public function testSigningAndOneTimeConsent(): void
    {
        [$id, $doc, $provider, $path, $result, $row] = $this->start();
        self::assertNotSame($result['token'], $row['token_hash']);
        self::assertSame(hash('sha256', $result['token']), $row['token_hash']);
        file_put_contents($path, 'fichier source modifié');
        self::assertSame('%PDF-1.4 contrat original', $this->service()->snapshot($row));
        $this->service()->signClient($result['token'], '127.0.0.2', 'client-test');
        self::assertNotNull($this->service()->byToken($result['token'])['client_signed_at']);
        $this->expectException(ValidationException::class);
        $this->service()->signClient($result['token'], '127.0.0.2', 'client-test');
    }

    public function testRevocationInvalidatesToken(): void
    {
        [$id, $doc, $provider, $path, $result] = $this->start();
        $this->service()->revoke($id, $result['id']);
        $this->expectException(NotFoundException::class);
        $this->service()->byToken($result['token']);
    }

    public function testExpiredTokenFails(): void
    {
        [$id, $doc, $provider, $path, $result] = $this->start();
        $this->db->execute("UPDATE contract_signatures SET expires_at = '2000-01-01 00:00:00' WHERE id = :id", ['id' => $result['id']]);
        $this->expectException(NotFoundException::class);
        $this->service()->byToken($result['token']);
    }

    public function testTamperingPreventsSignature(): void
    {
        [$id, $doc, $provider, $path, $result, $row] = $this->start();
        file_put_contents(storage_path('contract-signatures/' . $row['snapshot_name']), 'modifié');
        $this->expectException(\RuntimeException::class);
        $this->service()->signClient($result['token'], '127.0.0.1', 'test');
    }

    public function testDuplicatePendingAndStaleDocumentRejected(): void
    {
        [$id, $doc, $provider] = $this->start();
        $this->expectException(ValidationException::class);
        $this->service()->start($id, $doc, $provider, 'Autre', 'autre@example.org', '127.0.0.1', 'test');
    }

    public function testStaleDocumentRejected(): void
    {
        [$id, $doc, $provider] = $this->setupContract();
        $this->expectException(ValidationException::class);
        $this->service()->start($id, $doc + 1, $provider, 'Client', 'client@example.org', '127.0.0.1', 'test');
    }
}
