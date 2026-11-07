<?php

declare(strict_types=1);

namespace Modules\Contracts\Services;

use App\Core\Database\Connection;
use App\Core\Exception\NotFoundException;
use App\Core\Exception\ValidationException;
use App\Core\Pdf\PdfManager;
use Modules\Authentication\Models\User;

/** Signature simple sur une copie figée, sans certificat ni vérification d'identité. */
final class ContractSignatureService
{
    public const PROVIDER_CONSENT = 'J’ai lu le dernier PDF, je l’accepte et je le signe pour le prestataire.';
    public const CLIENT_CONSENT = 'J’ai lu le contrat téléchargé, je l’accepte et je souhaite le signer électroniquement en tant que signataire indiqué.';
    public function __construct(private readonly Connection $db, private readonly PdfManager $pdf)
    {
    }

    public function history(int $contractId): array
    {
        return $this->db->fetchAll('SELECT * FROM contract_signatures WHERE contract_id = :id ORDER BY id DESC', ['id' => $contractId]);
    }

    /** @return array{id:int,token:string} */
    public function start(int $contractId, int $documentId, User $provider, string $clientName, string $clientEmail, string $ip, string $agent): array
    {
        $clientName = trim($clientName);
        $clientEmail = trim($clientEmail);
        if ($clientName === '' || mb_strlen($clientName) > 180 || mb_strlen($clientEmail) > 254 || !filter_var($clientEmail, FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException(['client' => 'Indiquez le nom et une adresse e-mail valide du signataire client.']);
        }
        $doc = $this->pdf->latestFor('client_contract', $contractId);
        if (!$doc || (int) $doc['id'] !== $documentId || $doc['disk'] !== 'exports' || !preg_match('#^contrat_client/[0-9]{4}/[a-zA-Z0-9-]+\.pdf$#', (string) $doc['path'])) {
            throw new ValidationException(['document' => 'Le dernier PDF a changé ou est indisponible. Relisez-le avant de signer.']);
        }
        $source = storage_path('exports/' . $doc['path']);
        $bytes = is_file($source) ? file_get_contents($source) : false;
        if ($bytes === false || !hash_equals((string) $doc['checksum'], hash('sha256', $bytes))) {
            throw new ValidationException(['document' => 'Le PDF est introuvable ou son empreinte ne correspond pas. Régénérez-le.']);
        }
        $directory = storage_path('contract-signatures');
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new \RuntimeException('Stockage des signatures inaccessible.');
        }
        $name = bin2hex(random_bytes(24)) . '.pdf';
        $file = $directory . '/' . $name;
        if (file_put_contents($file, $bytes, LOCK_EX) !== strlen($bytes)) {
            @unlink($file);
            throw new \RuntimeException('Impossible de conserver la copie du contrat.');
        }
        chmod($file, 0600);
        $token = 's' . bin2hex(random_bytes(32));
        $now = date('Y-m-d H:i:s');
        try {
            $id = $this->db->transaction(function () use ($contractId, $provider, $clientName, $clientEmail, $ip, $agent, $doc, $name, $bytes, $token, $now): int {
                // Verrou du contrat pour sérialiser les invitations concurrentes sur MySQL.
                $lock = $this->db->isSqlite() ? '' : ' FOR UPDATE';
                if (!$this->db->fetchOne('SELECT id FROM client_contracts WHERE id = :id' . $lock, ['id' => $contractId])) {
                    throw new NotFoundException('Contrat introuvable.');
                }
                $pending = $this->db->fetchOne('SELECT id FROM contract_signatures WHERE contract_id = :id AND client_signed_at IS NULL AND revoked_at IS NULL AND expires_at > :now', ['id' => $contractId, 'now' => $now]);
                if ($pending) {
                    throw new ValidationException(['signature' => 'Une invitation est déjà en cours ; révoquez-la avant d’en créer une autre.']);
                }
                $this->db->execute('INSERT INTO contract_signatures (contract_id, document_id, snapshot_name, document_sha256, token_hash, client_name, client_email, provider_user_id, provider_name, provider_email, provider_consent, provider_signed_at, provider_ip, provider_agent, expires_at) VALUES (:contract, :document, :snapshot, :sha, :token, :name, :email, :user, :provider, :provider_email, :provider_consent, :signed, :ip, :agent, :expires)', [
                    'contract' => $contractId, 'document' => $doc['id'], 'snapshot' => $name,
                    'sha' => hash('sha256', $bytes), 'token' => hash('sha256', $token),
                    'name' => $clientName, 'email' => $clientEmail, 'user' => $provider->id,
                    'provider' => $provider->fullName(), 'provider_email' => $provider->email, 'provider_consent' => self::PROVIDER_CONSENT,
                    'signed' => $now, 'ip' => mb_substr($ip, 0, 64), 'agent' => mb_substr($agent, 0, 500),
                    'expires' => date('Y-m-d H:i:s', time() + 7 * 86400),
                ]);
                return (int) $this->db->lastInsertId();
            });
        } catch (\Throwable $exception) {
            @unlink($file);
            throw $exception;
        }
        return ['id' => $id, 'token' => $token];
    }

    public function byToken(string $token): array
    {
        if (!preg_match('/^s[0-9a-f]{64}$/D', $token)) {
            throw new NotFoundException('Lien de signature invalide ou expiré.');
        }
        $row = $this->db->fetchOne('SELECT s.*, c.contract_number, c.title FROM contract_signatures s JOIN client_contracts c ON c.id = s.contract_id WHERE s.token_hash = :hash AND s.revoked_at IS NULL AND s.expires_at > :now', ['hash' => hash('sha256', $token), 'now' => date('Y-m-d H:i:s')]);
        if (!$row) {
            throw new NotFoundException('Lien de signature invalide ou expiré.');
        }
        return $row;
    }

    public function snapshot(array $signature): string
    {
        $name = (string) $signature['snapshot_name'];
        if (!preg_match('/^[0-9a-f]{48}\.pdf$/D', $name)) {
            throw new \RuntimeException('Copie de contrat invalide.');
        }
        $file = storage_path('contract-signatures/' . $name);
        $bytes = is_file($file) ? file_get_contents($file) : false;
        if ($bytes === false || !hash_equals((string) $signature['document_sha256'], hash('sha256', $bytes))) {
            throw new \RuntimeException('Intégrité du contrat non vérifiée ; signature impossible.');
        }
        return $bytes;
    }

    public function signClient(string $token, string $ip, string $agent): void
    {
        $row = $this->byToken($token);
        $this->snapshot($row);
        $now = date('Y-m-d H:i:s');
        $count = $this->db->execute('UPDATE contract_signatures SET client_signed_at = :signed, client_ip = :ip, client_agent = :agent, client_consent = :consent WHERE id = :id AND client_signed_at IS NULL AND revoked_at IS NULL AND expires_at > :now', [
            'signed' => $now, 'ip' => mb_substr($ip, 0, 64), 'agent' => mb_substr($agent, 0, 500), 'consent' => self::CLIENT_CONSENT, 'id' => $row['id'], 'now' => $now,
        ]);
        if ($count !== 1) {
            throw new ValidationException(['signature' => 'Ce lien a déjà été utilisé ou n’est plus valide.']);
        }
    }

    public function revoke(int $contractId, int $signatureId): void
    {
        $count = $this->db->execute('UPDATE contract_signatures SET revoked_at = :now WHERE id = :id AND contract_id = :contract AND client_signed_at IS NULL AND revoked_at IS NULL', ['now' => date('Y-m-d H:i:s'), 'id' => $signatureId, 'contract' => $contractId]);
        if ($count !== 1) {
            throw new ValidationException(['signature' => 'Cette invitation ne peut pas être révoquée.']);
        }
    }
}
