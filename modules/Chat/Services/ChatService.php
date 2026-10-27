<?php

declare(strict_types=1);

namespace Modules\Chat\Services;

use App\Core\Database\Connection;
use App\Core\Exception\AppException;
use App\Core\Exception\AuthorizationException;
use App\Core\Exception\NotFoundException;
use App\Core\Exception\ValidationException;
use Modules\Chat\Models\ChatConversation;
use Modules\Chat\Models\ChatMessage;
use Modules\Chat\Repositories\ChatRepository;

/**
 * Messagerie collaborative : conversations directes et de groupe, messages
 * persistés, suivi de lecture. Le bornage d'accès est strict : seuls les
 * participants peuvent lire ou écrire dans une conversation.
 */
final class ChatService
{
    public const MAX_MESSAGE_LENGTH = 5000;

    public const MAX_GROUP_MEMBERS = 100;

    public const HISTORY_LIMIT = 50;

    public function __construct(
        private readonly Connection $db,
        private readonly ChatRepository $repository
    ) {
    }

    /**
     * Ouvre (crée au besoin) la conversation directe entre deux utilisateurs.
     */
    public function startDirect(int $userId, int $otherUserId): int
    {
        if ($otherUserId === $userId) {
            throw new ValidationException(['user_id' => 'Impossible de démarrer une conversation avec vous-même.']);
        }
        $this->assertActiveUser($otherUserId);

        $existing = $this->repository->findDirectBetween($userId, $otherUserId);
        if ($existing !== null) {
            return (int) $existing['id'];
        }

        try {
            $this->db->beginTransaction();
            $conversationId = $this->repository->insertConversation('direct', '', $userId);
            $this->repository->addParticipant($conversationId, $userId);
            $this->repository->addParticipant($conversationId, $otherUserId);
            $this->db->commit();
            return $conversationId;
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            // Une création concurrente peut avoir inséré la conversation entre-temps.
            $existing = $this->repository->findDirectBetween($userId, $otherUserId);
            if ($existing !== null) {
                return (int) $existing['id'];
            }
            throw $exception;
        }
    }

    /**
     * Crée un groupe de travail. Le créateur en est automatiquement membre.
     *
     * @param list<int> $memberIds
     */
    public function createGroup(int $creatorId, string $name, array $memberIds): int
    {
        $name = trim($name);
        if (mb_strlen($name) < 3 || mb_strlen($name) > 150) {
            throw new ValidationException(['name' => 'Le nom du groupe doit contenir entre 3 et 150 caractères.']);
        }

        $memberIds = array_values(array_unique(array_map('intval', $memberIds)));
        $memberIds[] = $creatorId;
        $memberIds = array_values(array_unique($memberIds));
        if (count($memberIds) < 2) {
            throw new ValidationException(['members' => 'Sélectionnez au moins un autre collaborateur.']);
        }
        if (count($memberIds) > self::MAX_GROUP_MEMBERS) {
            throw new ValidationException(['members' => 'Un groupe ne peut pas dépasser ' . self::MAX_GROUP_MEMBERS . ' membres.']);
        }
        foreach ($memberIds as $memberId) {
            $this->assertActiveUser($memberId);
        }

        try {
            $this->db->beginTransaction();
            $conversationId = $this->repository->insertConversation('group', $name, $creatorId);
            foreach ($memberIds as $memberId) {
                $this->repository->addParticipant($conversationId, $memberId);
            }
            $this->db->commit();
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }

        audit('chat.group.create', 'ChatConversation', $conversationId, [
            'name' => $name, 'members' => count($memberIds), 'by' => $creatorId,
        ]);
        return $conversationId;
    }

    /**
     * @return list<ChatConversation>
     */
    public function conversationsForUser(int $userId): array
    {
        $result = [];
        foreach ($this->repository->conversationsForUser($userId) as $row) {
            $participants = $this->repository->participants((int) $row['id']);
            if ($row['type'] === 'direct') {
                $other = array_values(array_filter(
                    $participants,
                    static fn (array $participant): bool => $participant['id'] !== $userId
                ));
                $row['display_name'] = $other[0]['name'] ?? 'Conversation';
            } else {
                $row['display_name'] = trim((string) $row['group_name']) !== '' ? $row['group_name'] : 'Groupe';
            }
            $result[] = ChatConversation::fromRow($row, $participants);
        }
        return $result;
    }

    /**
     * @return list<ChatMessage>
     */
    public function messages(int $userId, int $conversationId, int $afterId = 0, int $limit = self::HISTORY_LIMIT): array
    {
        $this->assertParticipant($conversationId, $userId);
        $limit = max(1, min(200, $limit));
        return array_map(
            fn (array $row): ChatMessage => ChatMessage::fromRow($row, $userId),
            $this->repository->messagesAfter($conversationId, max(0, $afterId), $limit)
        );
    }

    public function sendMessage(int $userId, int $conversationId, string $body): ChatMessage
    {
        $this->assertParticipant($conversationId, $userId);
        $body = trim($body);
        if ($body === '') {
            throw new ValidationException(['body' => 'Le message ne peut pas être vide.']);
        }
        if (mb_strlen($body) > self::MAX_MESSAGE_LENGTH) {
            throw new ValidationException(['body' => 'Le message ne peut pas dépasser ' . self::MAX_MESSAGE_LENGTH . ' caractères.']);
        }

        $id = $this->repository->insertMessage($conversationId, $userId, $body);
        $rows = $this->repository->messagesAfter($conversationId, $id - 1, 1);
        if ($rows === []) {
            throw new AppException("Le message envoyé est introuvable (conversation #{$conversationId}).");
        }
        return ChatMessage::fromRow($rows[0], $userId);
    }

    public function markRead(int $userId, int $conversationId): void
    {
        $this->assertParticipant($conversationId, $userId);
        $this->repository->markRead($conversationId, $userId);
    }

    public function unreadTotal(int $userId): int
    {
        return $this->repository->totalUnreadForUser($userId);
    }

    public function renameGroup(int $userId, int $conversationId, string $name): void
    {
        $conversation = $this->assertGroupAndCreator($conversationId, $userId);
        $name = trim($name);
        if (mb_strlen($name) < 3 || mb_strlen($name) > 150) {
            throw new ValidationException(['name' => 'Le nom du groupe doit contenir entre 3 et 150 caractères.']);
        }
        $this->repository->renameConversation($conversationId, $name);
        audit('chat.group.rename', 'ChatConversation', $conversationId, [
            'from' => $conversation['name'], 'to' => $name, 'by' => $userId,
        ]);
    }

    /**
     * @param list<int> $memberIds
     */
    public function addMembers(int $userId, int $conversationId, array $memberIds): void
    {
        $this->assertGroupAndCreator($conversationId, $userId);
        $existing = $this->repository->participantIds($conversationId);
        $memberIds = array_values(array_unique(array_map('intval', $memberIds)));
        foreach ($memberIds as $memberId) {
            $this->assertActiveUser($memberId);
        }
        if (count($existing) + count(array_diff($memberIds, $existing)) > self::MAX_GROUP_MEMBERS) {
            throw new ValidationException(['members' => 'Limite de ' . self::MAX_GROUP_MEMBERS . ' membres atteinte.']);
        }
        foreach ($memberIds as $memberId) {
            $this->repository->addParticipant($conversationId, $memberId);
        }
    }

    /**
     * Départ d'un participant. Un groupe sans membre restant est supprimé ;
     * si le créateur part, la propriété est transmise au plus ancien membre.
     */
    public function leave(int $userId, int $conversationId): void
    {
        $this->assertParticipant($conversationId, $userId);
        $conversation = $this->requireConversation($conversationId);

        if ($conversation['type'] === 'direct') {
            $this->repository->deleteConversation($conversationId);
            return;
        }

        $remaining = array_values(array_filter(
            $this->repository->participantIds($conversationId),
            static fn (int $id): bool => $id !== $userId
        ));
        $this->repository->removeParticipant($conversationId, $userId);

        if ($remaining === []) {
            $this->repository->deleteConversation($conversationId);
            return;
        }
        if ((int) $conversation['created_by'] === $userId) {
            $this->db->execute(
                'UPDATE chat_conversations SET created_by = :owner, updated_at = :now WHERE id = :id',
                ['owner' => $remaining[0], 'now' => date('Y-m-d H:i:s'), 'id' => $conversationId]
            );
        }
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function availableUsers(int $userId): array
    {
        return $this->repository->availableUsers($userId);
    }

    private function assertParticipant(int $conversationId, int $userId): void
    {
        if (!$this->repository->isParticipant($conversationId, $userId)) {
            throw new AuthorizationException("Vous n'êtes pas membre de cette conversation.");
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function assertGroupAndCreator(int $conversationId, int $userId): array
    {
        $this->assertParticipant($conversationId, $userId);
        $conversation = $this->requireConversation($conversationId);
        if ($conversation['type'] !== 'group') {
            throw new AppException('Cette action est réservée aux conversations de groupe.');
        }
        if ((int) $conversation['created_by'] !== $userId) {
            throw new AuthorizationException('Seul le créateur du groupe peut réaliser cette action.');
        }
        return $conversation;
    }

    /**
     * @return array<string,mixed>
     */
    private function requireConversation(int $conversationId): array
    {
        $conversation = $this->repository->find($conversationId);
        if ($conversation === null) {
            throw new NotFoundException('Conversation introuvable.');
        }
        return $conversation;
    }

    private function assertActiveUser(int $userId): void
    {
        $user = $this->db->fetchOne(
            'SELECT id, is_active FROM users WHERE id = :id',
            ['id' => $userId]
        );
        if ($user === null || (int) $user['is_active'] !== 1) {
            throw new ValidationException(['user_id' => 'Collaborateur introuvable ou compte désactivé.']);
        }
    }
}
