<?php

declare(strict_types=1);

namespace Modules\Invoicing\Models;

use App\Core\Database\BaseModel;

final class Invoice extends BaseModel
{
    public function __construct(
        public readonly int $id,
        public readonly int $companyId,
        public readonly int $thirdPartyId,
        public readonly string $invoiceNumber,
        public readonly string $type,
        public readonly string $status,
        public readonly string $issueDate,
        public readonly string $dueDate,
        public readonly float $totalHt,
        public readonly float $totalVat,
        public readonly float $totalTtc,
        public readonly float $amountPaid,
        public readonly float $balance
    ) {
    }

    public static function fromRow(array $row): static
    {
        return new self(
            (int) $row['id'],
            (int) $row['company_id'],
            (int) $row['third_party_id'],
            (string) $row['invoice_number'],
            (string) $row['type'],
            (string) $row['status'],
            (string) $row['issue_date'],
            (string) $row['due_date'],
            (float) $row['total_ht'],
            (float) $row['total_vat'],
            (float) $row['total_ttc'],
            (float) $row['amount_paid'],
            (float) $row['balance']
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoiceNumber,
            'status' => $this->status,
            'total_ttc' => $this->totalTtc,
            'balance' => $this->balance,
        ];
    }
}
