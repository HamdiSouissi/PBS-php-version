<?php

declare(strict_types=1);

namespace Modules\Contracts\Controllers;

use App\Core\Exception\NotFoundException;
use App\Core\Exception\ValidationException;
use App\Core\Http\Controller;
use App\Core\Http\RedirectResponse;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Pdf\PdfDocument;
use App\Services\MailerService;
use Modules\Contracts\Services\ClientContractService;
use Modules\Contracts\Services\ContractSignatureService;
use Modules\Contracts\Services\SignatureInvitationEmail;

final class ContractSignatureController extends Controller
{
    public function __construct(
        private readonly ContractSignatureService $signatures,
        private readonly ClientContractService $contracts,
        private readonly MailerService $mailer
    ) {
    }

    public function start(Request $request, int $id): Response
    {
        $this->auth()->requirePermission('contracts.write');
        $contract = $this->contracts->getOrFail($id);
        try {
            if ($request->input('consent') !== '1') {
                throw new ValidationException(['consent' => 'Confirmez votre signature du PDF avant de continuer.']);
            }
            $mode = (string) $request->input('delivery', '');
            if (!in_array($mode, ['link', 'email'], true)) {
                throw new ValidationException(['delivery' => 'Choisissez un mode de transmission.']);
            }
            $base = rtrim((string) config('app.url'), '/');
            if (!filter_var($base, FILTER_VALIDATE_URL) || (config('app.env') === 'production' && parse_url($base, PHP_URL_SCHEME) !== 'https')) {
                throw new ValidationException(['url' => 'Configurez APP_URL avec l’adresse HTTPS publique avant la signature.']);
            }
            $result = $this->signatures->start($id, (int) $request->input('document_id', 0), $this->auth()->user(), (string) $request->input('client_name', ''), (string) $request->input('client_email', ''), $request->ip(), $request->userAgent());
            $url = $base . '/signature/' . $result['token'];
            // Le lien n'est conservé en clair que dans la session de l'émetteur, pour un seul affichage.
            $_SESSION['_contract_signature_link_' . $id] = $url;
            if ($mode === 'email') {
                $name = trim((string) $request->input('client_name'));
                $reference = (string) $contract['contract_number'];
                $sent = $this->mailer->send(
                    trim((string) $request->input('client_email')),
                    'Invitation à signer un contrat',
                    SignatureInvitationEmail::html($url, $name, $reference),
                    SignatureInvitationEmail::text($url, $name, $reference)
                );
                flash($sent ? 'success' : 'error', $sent ? 'Signature enregistrée et invitation transmise par e-mail.' : 'Signature enregistrée, mais l’e-mail n’a pas été envoyé. Utilisez le lien affiché ou vérifiez le SMTP.');
            } else {
                flash('success', 'Signature enregistrée. Copiez et transmettez le lien personnel au client.');
            }
        } catch (ValidationException $exception) {
            flash('error', implode(' ', $exception->errors()));
        }
        return new RedirectResponse('/contracts/' . $id);
    }

    public function revoke(Request $request, int $id, int $signature): Response
    {
        $this->auth()->requirePermission('contracts.write');
        try {
            $this->signatures->revoke($id, $signature);
            flash('success', 'Invitation révoquée.');
        } catch (ValidationException $exception) {
            flash('error', implode(' ', $exception->errors()));
        }
        return new RedirectResponse('/contracts/' . $id);
    }

    public function clientPage(Request $request, string $token): Response
    {
        $row = $this->signatures->byToken($token);
        $this->signatures->snapshot($row);
        return $this->view('Contracts::signature_client', ['title' => 'Signature du contrat', 'signature' => $row, 'token' => $token], 'layouts/minimal');
    }

    public function clientSign(Request $request, string $token): Response
    {
        if ($request->input('consent') !== '1') {
            flash('error', 'Vous devez confirmer avoir lu et accepter de signer le contrat.');
        } else {
            try {
                $this->signatures->signClient($token, $request->ip(), $request->userAgent());
                flash('success', 'Votre signature a été enregistrée. Téléchargez le contrat et son justificatif.');
            } catch (ValidationException $exception) {
                flash('error', implode(' ', $exception->errors()));
            }
        }
        return new RedirectResponse('/signature/' . $token);
    }

    public function clientDocument(Request $request, string $token): Response
    {
        return $this->document($this->signatures->byToken($token));
    }

    public function clientReceipt(Request $request, string $token): Response
    {
        return $this->receipt($this->signatures->byToken($token));
    }

    public function staffDocument(Request $request, int $id, int $signature): Response
    {
        $this->auth()->requirePermission('contracts.read');
        return $this->document($this->staffRow($id, $signature));
    }

    public function staffReceipt(Request $request, int $id, int $signature): Response
    {
        $this->auth()->requirePermission('contracts.read');
        return $this->receipt($this->staffRow($id, $signature));
    }

    private function staffRow(int $id, int $signature): array
    {
        foreach ($this->signatures->history($id) as $row) {
            if ((int) $row['id'] === $signature) {
                $row['contract_number'] = $this->contracts->getOrFail($id)['contract_number'];
                return $row;
            }
        }
        throw new NotFoundException('Signature introuvable.');
    }

    private function document(array $row): Response
    {
        return new Response($this->signatures->snapshot($row), 200, [
            'Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="contrat.pdf"', 'Cache-Control' => 'no-store',
        ]);
    }

    private function receipt(array $row): Response
    {
        if (empty($row['client_signed_at'])) {
            throw new NotFoundException('Justificatif disponible après les deux signatures.');
        }
        $this->signatures->snapshot($row);
        $pdf = new PdfDocument();
        $pdf->setDocumentTitle('Justificatif de signature')->addPage();
        $pdf->h1('JUSTIFICATIF DE SIGNATURE ÉLECTRONIQUE SIMPLE');
        $pdf->paragraph('Ce justificatif accompagne la copie figée du contrat. Il ne constitue pas une signature électronique qualifiée et ne vérifie pas l’identité du client. Les adresses IP et navigateurs sont déclaratifs ou techniques et peuvent être inexacts.');
        $pdf->keyValueTable([
            ['Contrat', (string) $row['contract_number']],
            ['Empreinte SHA-256 du PDF', (string) $row['document_sha256']],
            ['Prestataire', (string) $row['provider_name'] . ' (' . $row['provider_email'] . ')'],
            ['Signature prestataire', (string) $row['provider_signed_at'] . ' (heure serveur)'],
            ['IP / navigateur prestataire', (string) $row['provider_ip'] . ' / ' . $row['provider_agent']],
            ['Client déclaré', (string) $row['client_name'] . ' (' . $row['client_email'] . ')'],
            ['Signature client', (string) $row['client_signed_at'] . ' (heure serveur)'],
            ['IP / navigateur client', (string) $row['client_ip'] . ' / ' . $row['client_agent']],
        ]);
        $pdf->h2('Consentements enregistrés');
        $pdf->paragraph('Prestataire : ' . (string) $row['provider_consent']);
        $pdf->paragraph('Client : ' . (string) $row['client_consent']);
        $pdf->paragraph('Conserver ensemble ce justificatif, le PDF figé et les enregistrements de preuve.');
        return new Response($pdf->output(), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="justificatif-signature.pdf"', 'Cache-Control' => 'no-store']);
    }
}
