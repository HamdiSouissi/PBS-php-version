<?php

declare(strict_types=1);

namespace Modules\Chat\Repositories;

use App\Core\Database\BaseRepository;
use App\Core\Database\Connection;

/**
 * Accès aux données de messagerie collaborative. Toutes les requêtes sont
 * explicites et paramétrées ; le bornage aux participants reste appliqué
 * par le service via les clauses user_id.
 */
final class ChatRepository extends BaseRepository
{
    public function __construct(Connection $db)
    {
        parent::__construct($db);
    }

    protected function table(): string
    {
        return 'chat_conversations';
    }

    public function insertConversation(string $type, string $name, int $creatorId): int
    {
        return $this->insert('chat_conversations', [
            'type' => $type,
            'name' => $name,
            'created_by' => $creatorId,
        ]);
    }

    public function addParticipant(int $conversationId, int $userId): void
    {
        $this->db->insertIgnore(
            'chat_participants',
            ['conversation_id' => $conversationId, 'user_id' => $userId],
            ['conversation_id', 'user_id']
        );
    }

    /**
     * Conversation directe existante entre deux utilisateurs, sinon null.
     */
    public function findDirectBetween(int $userId, int $otherUserId): ?array
    {
        return $this->db->fetchOne(
            "SELECT c.*
             FROM chat_conversations c
             JOIN chat_participants p1 ON p1.conversation_id = c.id AND p1.user_id = :user
             JOIN chat_participants p2 ON p2.conversation_id = c.id AND p2.user_id = :other
             WHERE c.type = 'direct'
             LIMIT 1",
            ['user' => $userId, 'other' => $otherUserId]
        );
    }

    public function isParticipant(int $conversationId, int $userId): bool
    {
        return (int) $this->db->fetchScalar(
            'SELECT COUNT(*) FROM chat_participants WHERE conversation_id = :conversation AND user_id = :user',
            ['conversation' => $conversationId, 'user' => $userId]
        ) > 0;
    }

    /**
     * @return list<int>
     */
    public function participantIds(int $conversationId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT user_id FROM chat_participants WHERE conversation_id = :conversation ORDER BY joined_at, id',
            ['conversation' => $conversationId]
        );
        return array_map(static fn (array $row): int => (int) $row['user_id'], $rows);
    }

    /**
     * @return list<array{id:int,name:string}>
     */
    public function participants(int $conversationId): array
    {
        $rows = $this->db->fetchAll(
            "SELECT u.id, u.first_name, u.last_name, u.email
             FROM chat_participants p
             JOIN users u ON u.id = p.user_id
             WHERE p.conversation_id = :conversation
             ORDER BY u.last_name, u.first_name",
            ['conversation' => $conversationId]
        );
        return array_map(static function (array $row): array {
            $name = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
            return ['id' => (int) $row['id'], 'name' => $name !== '' ? $name : (string) $row['email']];
        }, $rows);
    }

    public function insertMessage(int $conversationId, int $senderId, string $body): int
    {
        $id = $this->insert('chat_messages', [
            'conversation_id' => $conversationId,
            'sender_id' => $senderId,
            'body' => $body,
        ]);
        $this->db->execute(
            'UPDATE chat_conversations SET updated_at = :now WHERE id = :id',
            ['now' => date('Y-m-d H:i:s'), 'id' => $conversationId]
        );
        return $id;
    }

    /**
     * Messages postérieurs à $afterId (ou les derniers par défaut), avec
     * l'identité de l'expéditeur, ordonnés chronologiquement.
     *
     * @return list<array<string,mixed>>
     */
    public function messagesAfter(int $conversationId, int $afterId, int $limit): array
    {
        if ($afterId > 0) {
            $sql = 'SELECT m.*, u.first_name, u.last_name, u.email AS sender_email
                    FROM chat_messages m
                    JOIN users u ON u.id = m.sender_id
                    WHERE m.conversation_id = :conversation AND m.id > :after
                    ORDER BY m.id ASC';
            $params = ['conversation' => $conversationId, 'after' => $afterId];
        } else {
            $sql = 'SELECT * FROM (
                        SELECT m.*, u.first_name, u.last_name, u.email AS sender_email
                        FROM chat_messages m
                        JOIN users u ON u.id = m.sender_id
                        WHERE m.conversation_id = :conversation
                        ORDER BY m.id DESC LIMIT :limit
                    ) AS latest ORDER BY latest.id ASC';
            $params = ['conversation' => $conversationId, 'limit' => $limit];
        }
        return $this->db->fetchAll($sql, $params);
    }

    public function markRead(int $conversationId, int $userId): void
    {
        // Le suivi par identifiant de message reste exact même à la même seconde.
        $this->db->execute(
            'UPDATE chat_participants
             SET last_read_message_id = (SELECT COALESCE(MAX(id), 0) FROM chat_messages WHERE conversation_id = :conversation)
             WHERE conversation_id = :conversation AND user_id = :user',
            ['conversation' => $conversationId, 'user' => $userId]
        );
    }

    public function totalUnreadForUser(int $userId): int
    {
        return (int) $this->db->fetchScalar(
            "SELECT COUNT(*)
             FROM chat_messages m
             JOIN chat_participants p ON p.conversation_id = m.conversation_id
             WHERE p.user_id = :user
               AND m.sender_id <> :user
               AND (p.last_read_message_id IS NULL OR m.id > p.last_read_message_id)",
            ['user' => $userId]
        );
    }

    /**
     * Liste brute des conversations de l'utilisateur avec dernier message et
     * nombre de non-lus, la plus récemment active en premier. Le libellé
     * humain (nom du correspondant en direct, nom du groupe) est composé
     * par le service, pour rester portable MySQL/SQLite.
     *
     * @return list<array<string,mixed>>
     */
    public function conversationsForUser(int $userId): array
    {
        return $this->db->fetchAll(
            "SELECT c.id, c.type, c.name AS group_name, c.created_by, c.updated_at,
                    (SELECT m3.body FROM chat_messages m3
                       WHERE m3.conversation_id = c.id ORDER BY m3.id DESC LIMIT 1) AS last_body,
                    (SELECT m4.created_at FROM chat_messages m4
                       WHERE m4.conversation_id = c.id ORDER BY m4.id DESC LIMIT 1) AS last_at,
                    (SELECT COUNT(*) FROM chat_messages m5
                       JOIN chat_participants p5 ON p5.conversation_id = m5.conversation_id
                      WHERE p5.user_id = :user AND m5.conversation_id = c.id
                        AND m5.sender_id <> :user
                        AND (p5.last_read_message_id IS NULL OR m5.id > p5.last_read_message_id)) AS unread
             FROM chat_conversations c
             JOIN chat_participants p ON p.conversation_id = c.id
             WHERE p.user_id = :user
             ORDER BY c.updated_at DESC, c.id DESC",
            ['user' => $userId]
        );
    }

    public function renameConversation(int $conversationId, string $name): void
    {
        $this->db->execute(
            'UPDATE chat_conversations SET name = :name, updated_at = :now WHERE id = :id',
            ['name' => $name, 'now' => date('Y-m-d H:i:s'), 'id' => $conversationId]
        );
    }

    public function removeParticipant(int $conversationId, int $userId): void
    {
        $this->db->execute(
            'DELETE FROM chat_participants WHERE conversation_id = :conversation AND user_id = :user',
            ['conversation' => $conversationId, 'user' => $userId]
        );
    }

    public function deleteConversation(int $conversationId): void
    {
        $this->db->execute('DELETE FROM chat_conversations WHERE id = :id', ['id' => $conversationId]);
    }

    /**
     * Utilisateurs actifs avec lesquels démarrer une conversation.
     *
     * @return list<array<string,mixed>>
     */
    public function availableUsers(int $exceptUserId): array
    {
        return $this->db->fetchAll(
            "SELECT id, email, first_name, last_name
             FROM users
             WHERE is_active = 1 AND id <> :except
             ORDER BY last_name, first_name",
            ['except' => $exceptUserId]
        );
    }
}
