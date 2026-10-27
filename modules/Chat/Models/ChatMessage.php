<?php

declare(strict_types=1);

namespace Modules\Chat\Models;

/**
 * Message de chat persisté, prêt pour l'API JSON et l'affichage. Le corps
 * reste du texte brut : l'échappement HTML reste à la charge des vues.
 */
final class ChatMessage
{
    public function __construct(
        public readonly int $id,
        public readonly int $conversationId,
        public readonly int $senderId,
        public readonly string $senderName,
        public readonly string $body,
        public readonly string $createdAt,
        public readonly bool $mine
    ) {
    }

    /**
     * @param array<string,mixed> $row
     */
    public static function fromRow(array $row, int $currentUserId): self
    {
        $name = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
        if ($name === '') {
            $name = (string) ($row['sender_email'] ?? '');
        }
        return new self(
            (int) $row['id'],
            (int) $row['conversation_id'],
            (int) $row['sender_id'],
            $name,
            (string) $row['body'],
            (string) $row['created_at'],
            (int) $row['sender_id'] === $currentUserId
        );
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversationId,
            'sender_id' => $this->senderId,
            'sender_name' => $this->senderName,
            'body' => $this->body,
            'created_at' => $this->createdAt,
            'mine' => $this->mine,
        ];
    }
}
