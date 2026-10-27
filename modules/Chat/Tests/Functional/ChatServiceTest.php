<?php

declare(strict_types=1);

namespace Modules\Chat\Tests\Functional;

use App\Core\Application;
use App\Core\Exception\AuthorizationException;
use App\Core\Exception\ValidationException;
use Modules\Chat\Models\ChatMessage;
use Modules\Chat\Services\ChatService;
use Tests\Core\DatabaseTestCase;

final class ChatServiceTest extends DatabaseTestCase
{
    private ChatService $service;

    /** @var array<string,int> */
    private array $users = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = Application::container()->get(ChatService::class);
        foreach (['Alice Martin', 'Bernard Durand', 'Claire Leroy', 'David Petit'] as $label) {
            [$first, $last] = explode(' ', $label);
            $email = strtolower($first) . '@primevisionsolutions.fr';
            $this->db->execute(
                'INSERT INTO users (email, password_hash, first_name, last_name, is_active)
                 VALUES (:email, :hash, :first, :last, 1)',
                ['email' => $email, 'hash' => 'x', 'first' => $first, 'last' => $last]
            );
            $this->users[$first] = (int) $this->db->lastInsertId();
        }
    }

    public function testStartDirectConversationIsCreatedOnceWithTwoParticipants(): void
    {
        $alice = $this->users['Alice'];
        $bernard = $this->users['Bernard'];

        $first = $this->service->startDirect($alice, $bernard);
        $second = $this->service->startDirect($alice, $bernard);
        self::assertSame($first, $second, 'Une seule conversation directe doit exister entre deux personnes.');

        $conversations = $this->service->conversationsForUser($alice);
        self::assertCount(1, $conversations);
        self::assertSame('direct', $conversations[0]->type);
        self::assertSame('Bernard Durand', $conversations[0]->name);
        self::assertCount(2, $conversations[0]->participants);
    }

    public function testStartDirectWithSelfIsRejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->service->startDirect($this->users['Alice'], $this->users['Alice']);
    }

    public function testInactiveOrUnknownUserIsRejected(): void
    {
        $this->db->execute(
            'UPDATE users SET is_active = 0 WHERE id = :id',
            ['id' => $this->users['David']]
        );
        $this->expectException(ValidationException::class);
        $this->service->startDirect($this->users['Alice'], $this->users['David']);
    }

    public function testMessagesArePersistedOrderedAndScopedToParticipants(): void
    {
        $alice = $this->users['Alice'];
        $bernard = $this->users['Bernard'];
        $claire = $this->users['Claire'];
        $conversationId = $this->service->startDirect($alice, $bernard);

        $first = $this->service->sendMessage($alice, $conversationId, 'Bonjour Bernard');
        $this->service->sendMessage($bernard, $conversationId, 'Salut Alice !');

        $messages = $this->service->messages($alice, $conversationId);
        self::assertCount(2, $messages);
        self::assertSame('Bonjour Bernard', $messages[0]->body);
        self::assertTrue($messages[0]->mine);
        self::assertFalse($messages[1]->mine);
        self::assertSame($first->id, $messages[0]->id);

        // Claire, non participante, ne peut ni lire ni écrire.
        $this->expectException(AuthorizationException::class);
        $this->service->messages($claire, $conversationId);
    }

    public function testOutsiderCannotSendToConversation(): void
    {
        $conversationId = $this->service->startDirect($this->users['Alice'], $this->users['Bernard']);
        $this->expectException(AuthorizationException::class);
        $this->service->sendMessage($this->users['Claire'], $conversationId, 'Intrusion');
    }

    public function testEmptyAndOversizedMessagesAreRejected(): void
    {
        $conversationId = $this->service->startDirect($this->users['Alice'], $this->users['Bernard']);

        try {
            $this->service->sendMessage($this->users['Alice'], $conversationId, '   ');
            self::fail('Un message vide devait être rejeté.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('body', $exception->errors());
        }

        $this->expectException(ValidationException::class);
        $this->service->sendMessage($this->users['Alice'], $conversationId, str_repeat('a', ChatService::MAX_MESSAGE_LENGTH + 1));
    }

    public function testUnreadCountsAndMarkRead(): void
    {
        $alice = $this->users['Alice'];
        $bernard = $this->users['Bernard'];
        $conversationId = $this->service->startDirect($alice, $bernard);

        $this->service->sendMessage($alice, $conversationId, 'Un');
        $this->service->sendMessage($alice, $conversationId, 'Deux');
        self::assertSame(2, $this->service->unreadTotal($bernard));
        self::assertSame(0, $this->service->unreadTotal($alice), 'Ses propres messages ne sont jamais non lus.');

        $this->service->markRead($bernard, $conversationId);
        self::assertSame(0, $this->service->unreadTotal($bernard));

        $this->service->sendMessage($alice, $conversationId, 'Trois');
        self::assertSame(1, $this->service->unreadTotal($bernard));
    }

    public function testIncrementalPollingReturnsOnlyNewMessages(): void
    {
        $alice = $this->users['Alice'];
        $bernard = $this->users['Bernard'];
        $conversationId = $this->service->startDirect($alice, $bernard);

        $first = $this->service->sendMessage($alice, $conversationId, 'Initial');
        $this->service->sendMessage($alice, $conversationId, 'Second');

        $incremental = $this->service->messages($bernard, $conversationId, $first->id);
        self::assertCount(1, $incremental);
        self::assertSame('Second', $incremental[0]->body);
        self::assertTrue($incremental[0] instanceof ChatMessage);
    }

    public function testGroupLifecycleAndCreatorOnlyAdministration(): void
    {
        $alice = $this->users['Alice'];
        $bernard = $this->users['Bernard'];
        $claire = $this->users['Claire'];

        try {
            $this->service->createGroup($alice, 'AB', [$bernard]);
            self::fail('Un nom de groupe trop court devait être rejeté.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('name', $exception->errors());
        }

        $groupId = $this->service->createGroup($alice, 'Projet Q4', [$bernard, $claire]);
        $conversation = $this->service->conversationsForUser($bernard)[0];
        self::assertSame('Projet Q4', $conversation->name);
        self::assertTrue($conversation->isGroup());
        self::assertCount(3, $conversation->participants);

        // Seul le créateur peut renommer ou ajouter des membres.
        $this->expectException(AuthorizationException::class);
        $this->service->renameGroup($bernard, $groupId, 'Projet Q5');
    }

    public function testCreatorCanRenameAndAddMembers(): void
    {
        $alice = $this->users['Alice'];
        $bernard = $this->users['Bernard'];
        $david = $this->users['David'];
        $groupId = $this->service->createGroup($alice, 'Équipe commerciale', [$bernard]);

        $this->service->renameGroup($alice, $groupId, 'Équipe commerciale France');
        $this->service->addMembers($alice, $groupId, [$david]);

        $conversation = $this->service->conversationsForUser($alice)[0];
        self::assertSame('Équipe commerciale France', $conversation->name);
        self::assertCount(3, $conversation->participants);
    }

    public function testLeaveTransfersOwnershipAndDeletesEmptyGroup(): void
    {
        $alice = $this->users['Alice'];
        $bernard = $this->users['Bernard'];
        $claire = $this->users['Claire'];
        $groupId = $this->service->createGroup($alice, 'Groupe temporaire', [$bernard, $claire]);

        // La créatrice quitte : Bernard devient propriétaire et peut administrer.
        $this->service->leave($alice, $groupId);
        self::assertSame(0, $this->service->unreadTotal($alice));
        $this->service->renameGroup($bernard, $groupId, 'Groupe repris');

        $this->service->leave($bernard, $groupId);
        $this->service->leave($claire, $groupId);

        self::assertSame(
            0,
            (int) $this->db->fetchScalar('SELECT COUNT(*) FROM chat_conversations WHERE id = :id', ['id' => $groupId]),
            'Un groupe sans membre doit être supprimé.'
        );
    }

    public function testLeavingDirectConversationDeletesIt(): void
    {
        $alice = $this->users['Alice'];
        $bernard = $this->users['Bernard'];
        $conversationId = $this->service->startDirect($alice, $bernard);

        $this->service->leave($bernard, $conversationId);

        self::assertSame(
            0,
            (int) $this->db->fetchScalar('SELECT COUNT(*) FROM chat_conversations WHERE id = :id', ['id' => $conversationId])
        );
        self::assertSame(
            0,
            (int) $this->db->fetchScalar('SELECT COUNT(*) FROM chat_messages WHERE conversation_id = :id', ['id' => $conversationId]),
            'La suppression doit emporter les messages en cascade.'
        );
    }

    public function testConversationListCarriesLastMessagePreview(): void
    {
        $alice = $this->users['Alice'];
        $bernard = $this->users['Bernard'];
        $conversationId = $this->service->startDirect($alice, $bernard);
        $this->service->sendMessage($alice, $conversationId, 'Dernier message aperçu');

        $list = $this->service->conversationsForUser($bernard);
        self::assertSame('Dernier message aperçu', $list[0]->lastBody);
        self::assertSame(1, $list[0]->unread);
        self::assertNotNull($list[0]->lastAt);
    }
}
