-- =============================================================================
-- Migration 015 - Messagerie collaborative interne (chat direct et de groupe)
-- MySQL 8.0.16+ / MariaDB 10.11+ (InnoDB, utf8mb4)
--
-- Historique intégralement persisté : conversations, participants et messages.
-- La lecture est suivie par participant (last_read_message_id) pour les
-- pastilles de non-lus, exactes même à la seconde près. Aucun contenu n'est
-- chiffré : messages internes professionnels,
-- l'accès reste borné aux participants par la couche applicative (RBAC).
-- =============================================================================

CREATE TABLE IF NOT EXISTS chat_conversations (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    type        VARCHAR(10) NOT NULL,
    name        VARCHAR(150) NOT NULL DEFAULT '',
    created_by  BIGINT UNSIGNED NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_chat_conversation_type CHECK (type IN ('direct', 'group')),
    CONSTRAINT fk_chat_conversations_creator FOREIGN KEY (created_by)
        REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS chat_participants (
    id                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    conversation_id       BIGINT UNSIGNED NOT NULL,
    user_id               BIGINT UNSIGNED NOT NULL,
    last_read_message_id  BIGINT UNSIGNED NULL,
    joined_at             DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_chat_participant UNIQUE (conversation_id, user_id),
    CONSTRAINT fk_chat_participants_conversation FOREIGN KEY (conversation_id)
        REFERENCES chat_conversations(id) ON DELETE CASCADE,
    CONSTRAINT fk_chat_participants_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX idx_chat_participants_user ON chat_participants(user_id);

CREATE TABLE IF NOT EXISTS chat_messages (
    id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    conversation_id  BIGINT UNSIGNED NOT NULL,
    sender_id        BIGINT UNSIGNED NOT NULL,
    body             TEXT NOT NULL,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_chat_messages_conversation FOREIGN KEY (conversation_id)
        REFERENCES chat_conversations(id) ON DELETE CASCADE,
    CONSTRAINT fk_chat_messages_sender FOREIGN KEY (sender_id)
        REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT chk_chat_message_body CHECK (CHAR_LENGTH(TRIM(body)) > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE INDEX idx_chat_messages_conversation ON chat_messages(conversation_id, id);
