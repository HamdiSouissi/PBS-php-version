<?php

declare(strict_types=1);

namespace Modules\Chat\Controllers;

use App\Core\Http\Controller;
use App\Core\Http\JsonResponse;
use App\Core\Http\Request;
use App\Core\Http\Response;
use Modules\Chat\Services\ChatService;

/**
 * Messagerie collaborative : page unique et endpoints JSON utilisés par la
 * scrutation côté client (polling). Tous les endpoints exigent la permission
 * chat.use et le CSRF ; le service borne chaque accès aux participants.
 */
final class ChatController extends Controller
{
    public function __construct(private readonly ChatService $chat)
    {
    }

    public function index(Request $request): Response
    {
        $userId = (int) $this->auth()->id();
        return $this->view('Chat::chat/index', [
            'title' => 'Messagerie collaborative',
            'currentUserId' => $userId,
            'conversations' => $this->chat->conversationsForUser($userId),
            'users' => $this->chat->availableUsers($userId),
            'unreadTotal' => $this->chat->unreadTotal($userId),
        ]);
    }

    public function users(Request $request): JsonResponse
    {
        return new JsonResponse([
            'success' => true,
            'data' => $this->chat->availableUsers((int) $this->auth()->id()),
        ]);
    }

    public function unread(Request $request): JsonResponse
    {
        return new JsonResponse([
            'success' => true,
            'total' => $this->chat->unreadTotal((int) $this->auth()->id()),
        ]);
    }

    public function conversations(Request $request): JsonResponse
    {
        $userId = (int) $this->auth()->id();
        return new JsonResponse([
            'success' => true,
            'data' => array_map(
                static fn ($conversation): array => $conversation->toArray(),
                $this->chat->conversationsForUser($userId)
            ),
            'unread_total' => $this->chat->unreadTotal($userId),
        ]);
    }

    public function startDirect(Request $request): JsonResponse
    {
        $conversationId = $this->chat->startDirect(
            (int) $this->auth()->id(),
            (int) $request->input('user_id', 0)
        );
        return new JsonResponse(['success' => true, 'conversation_id' => $conversationId]);
    }

    public function createGroup(Request $request): JsonResponse
    {
        $members = $request->input('members', []);
        if (!is_array($members)) {
            $members = [];
        }
        $conversationId = $this->chat->createGroup(
            (int) $this->auth()->id(),
            (string) $request->input('name', ''),
            array_map('intval', $members)
        );
        return new JsonResponse(['success' => true, 'conversation_id' => $conversationId]);
    }

    public function messages(Request $request): JsonResponse
    {
        $messages = $this->chat->messages(
            (int) $this->auth()->id(),
            (int) $request->query('conversation_id', 0),
            (int) $request->query('after', 0)
        );
        return new JsonResponse([
            'success' => true,
            'data' => array_map(static fn ($message): array => $message->toArray(), $messages),
        ]);
    }

    public function send(Request $request): JsonResponse
    {
        $message = $this->chat->sendMessage(
            (int) $this->auth()->id(),
            (int) $request->input('conversation_id', 0),
            (string) $request->input('body', '')
        );
        return new JsonResponse(['success' => true, 'data' => $message->toArray()], 201);
    }

    public function read(Request $request): JsonResponse
    {
        $this->chat->markRead(
            (int) $this->auth()->id(),
            (int) $request->input('conversation_id', 0)
        );
        return new JsonResponse(['success' => true]);
    }

    public function rename(Request $request, int $id): JsonResponse
    {
        $this->chat->renameGroup((int) $this->auth()->id(), $id, (string) $request->input('name', ''));
        return new JsonResponse(['success' => true]);
    }

    public function addMembers(Request $request, int $id): JsonResponse
    {
        $members = $request->input('members', []);
        $members = is_array($members) ? array_map('intval', $members) : [];
        $this->chat->addMembers((int) $this->auth()->id(), $id, $members);
        return new JsonResponse(['success' => true]);
    }

    public function leave(Request $request, int $id): JsonResponse
    {
        $this->chat->leave((int) $this->auth()->id(), $id);
        return new JsonResponse(['success' => true]);
    }
}
