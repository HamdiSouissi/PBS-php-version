<?php

declare(strict_types=1);

namespace Modules\Chat\Models;

/**
 * Conversation telle que présentée dans la liste d'un utilisateur :
 * identité du correspondant (chat direct) ou nom du groupe, dernier message
 * et nombre de non-lus personnels.
 */
final class ChatConversation
{
    /**
     * @param list<array{id:int,name:string}> $participants
     */
    public function __construct(
        public readonly int $id,
        public readonly string $type,
        public readonly string $name,
        public readonly int $createdBy,
        public readonly string $updatedAt,
        public readonly ?string $lastBody,
        public readonly ?string $lastAt,
        public readonly int $unread,
        public readonly array $participants
    ) {
    }

    public function isGroup(): bool
    {
        return $this->type === 'group';
    }

    /**
     * @param array<string,mixed> $row
     * @param list<array{id:int,name:string}> $participants
     */
    public static function fromRow(array $row, array $participants): self
    {
        return new self(
            (int) $row['id'],
            (string) $row['type'],
            (string) $row['display_name'],
            (int) $row['created_by'],
            (string) $row['updated_at'],
            isset($row['last_body']) && $row['last_body'] !== null ? (string) $row['last_body'] : null,
            isset($row['last_at']) && $row['last_at'] !== null ? (string) $row['last_at'] : null,
            (int) ($row['unread'] ?? 0),
            $participants
        );
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'name' => $this->name,
            'created_by' => $this->createdBy,
            'updated_at' => $this->updatedAt,
            'last_body' => $this->lastBody,
            'last_at' => $this->lastAt,
            'unread' => $this->unread,
            'participants' => $this->participants,
        ];
    }
}
