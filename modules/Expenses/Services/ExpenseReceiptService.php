<?php

declare(strict_types=1);

namespace Modules\Expenses\Services;

use App\Core\Database\Connection;
use App\Core\Exception\AppException;
use App\Core\Exception\NotFoundException;
use App\Core\Exception\ValidationException;
use App\Core\Http\UploadedFile;

/** Justificatifs privés associés à une note, sans accès public direct. */
final class ExpenseReceiptService
{
    private const MAX_SIZE = 10485760;

    public function __construct(private readonly Connection $db)
    {
    }

    public function forReport(int $id): array
    {
        return $this->db->fetchAll('SELECT id, original_name, file_size, created_at FROM expense_receipts WHERE report_id = :id ORDER BY id DESC', ['id' => $id]);
    }

    public function upload(int $reportId, UploadedFile $file, int $userId): string
    {
        if ($file->extension() !== 'pdf' || $file->size() <= 0 || $file->size() > self::MAX_SIZE) {
            throw new ValidationException(['file' => 'Sélectionnez un PDF non vide de 10 Mo maximum.']);
        }
        $contents = $file->contents();
        if (strlen($contents) !== $file->size() || !str_starts_with($contents, '%PDF-')
            || (new \finfo(FILEINFO_MIME_TYPE))->buffer($contents) !== 'application/pdf') {
            throw new ValidationException(['file' => 'Le fichier doit être un PDF valide.']);
        }
        $name = bin2hex(random_bytes(16)) . '.pdf';
        $path = null;
        try {
            $this->db->transaction(function (Connection $db) use ($reportId, $file, $userId, $name, &$path): void {
                $report = $db->fetchOne('SELECT status FROM expense_reports WHERE id = :id AND deleted_at IS NULL' . ($db->isSqlite() ? '' : ' FOR UPDATE'), ['id' => $reportId]);
                if ($report === null) {
                    throw new NotFoundException('Note de frais introuvable.');
                }
                if ($report['status'] !== 'draft') {
                    throw new AppException('Les justificatifs ne peuvent être ajoutés qu’à un brouillon.');
                }
                $path = $file->moveTo(storage_path('uploads/expense-receipts'), $name);
                $original = str_replace('\\', '/', $file->originalName());
                $original = mb_substr(basename($original), 0, 255);
                $db->execute('INSERT INTO expense_receipts (report_id, original_name, storage_name, file_size, uploaded_by) VALUES (:report, :original, :name, :size, :user)', [
                    'report' => $reportId, 'original' => $original, 'name' => $name, 'size' => $file->size(), 'user' => $userId,
                ]);
                audit('expense.receipt_upload', 'ExpenseReport', $reportId);
            });
        } catch (\Throwable $exception) {
            if ($path !== null) {
                @unlink($path);
            }
            throw $exception;
        }
        return $path;
    }

    /** @return array{path:string,name:string,size:int} */
    public function download(int $reportId, int $receiptId): array
    {
        $row = $this->db->fetchOne('SELECT * FROM expense_receipts WHERE id = :id AND report_id = :report', ['id' => $receiptId, 'report' => $reportId]);
        if ($row === null || !preg_match('/^[a-f0-9]{32}\\.pdf$/D', (string) $row['storage_name'])) {
            throw new NotFoundException('Justificatif introuvable.');
        }
        $root = realpath(storage_path('uploads/expense-receipts'));
        $path = $root === false ? false : realpath($root . DIRECTORY_SEPARATOR . $row['storage_name']);
        if ($root === false || $path === false || !str_starts_with($path, rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR) || !is_file($path)) {
            throw new NotFoundException('Justificatif introuvable.');
        }
        return ['path' => $path, 'name' => (string) $row['original_name'], 'size' => (int) filesize($path)];
    }
}
