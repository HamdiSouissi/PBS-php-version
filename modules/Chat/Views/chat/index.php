<?php

declare(strict_types=1);

/**
 * @var list<\Modules\Chat\Models\ChatConversation> $conversations
 * @var list<array<string,mixed>> $users
 * @var int $currentUserId
 * @var int $unreadTotal
 */
$initialConversations = array_map(static fn ($c): array => $c->toArray(), $conversations);
$initialUsers = array_map(static fn (array $u): array => [
    'id' => (int) $u['id'],
    'name' => trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')),
    'email' => (string) $u['email'],
], $users);
?>
<?= $viewRenderer->pageHeader('Messagerie collaborative', [['label' => 'Communication']]) ?>

<div class="page-body"
     id="chatApp"
     data-csrf="<?= e(csrf_token()) ?>"
     data-current-user="<?= (int) $currentUserId ?>"
     data-conversations="<?= e(json_encode($initialConversations, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>"
     data-users="<?= e(json_encode($initialUsers, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>">

    <div class="card chat-card">
        <div class="row g-0 chat-layout">
            <!-- Panneau latéral : conversations -->
            <div class="col-lg-4 col-xl-3 chat-sidebar border-end">
                <div class="p-3 border-bottom d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-primary flex-fill" data-bs-toggle="modal" data-bs-target="#directModal">
                        <i data-lucide="message-square"></i> Nouveau message
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-primary flex-fill" data-bs-toggle="modal" data-bs-target="#groupModal">
                        <i data-lucide="users"></i> Groupe
                    </button>
                </div>
                <div class="chat-conversation-list" id="conversationList">
                    <?php if ($conversations === []): ?>
                        <p class="text-muted small text-center py-4 px-3 mb-0">
                            Aucune conversation pour le moment.<br>Démarrez un échange avec un collaborateur.
                        </p>
                    <?php endif; ?>
                    <?php foreach ($conversations as $conversation): ?>
                        <button type="button"
                                class="chat-conversation w-100 text-start border-0"
                                data-conversation-id="<?= $conversation->id ?>">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <span class="chat-conversation-name text-truncate">
                                    <i data-lucide="<?= $conversation->isGroup() ? 'users' : 'user' ?>" class="me-1"></i>
                                    <?= e($conversation->name) ?>
                                </span>
                                <?php if ($conversation->unread > 0): ?>
                                    <span class="badge text-bg-danger rounded-pill chat-unread-badge"><?= $conversation->unread ?></span>
                                <?php endif; ?>
                            </div>
                            <span class="chat-conversation-preview text-truncate d-block text-muted small">
                                <?= e($conversation->lastBody ?? 'Aucun message') ?>
                            </span>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Panneau principal : fil de discussion -->
            <div class="col-lg-8 col-xl-9 d-flex flex-column chat-main">
                <div class="chat-thread-header border-bottom p-3 d-none" id="threadHeader">
                    <div class="d-flex align-items-center gap-2">
                        <h6 class="mb-0 me-auto" id="threadTitle">—</h6>
                        <span class="text-muted small d-none d-md-inline" id="threadParticipants"></span>
                        <div class="dropdown" id="groupActions" hidden>
                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" type="button">Groupe</button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><button class="dropdown-item" type="button" id="renameGroupBtn" data-bs-toggle="modal" data-bs-target="#renameModal">Renommer</button></li>
                                <li><button class="dropdown-item" type="button" id="addMembersBtn" data-bs-toggle="modal" data-bs-target="#membersModal">Ajouter des membres</button></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><button class="dropdown-item text-danger" type="button" id="leaveGroupBtn">Quitter le groupe</button></li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="chat-thread flex-grow-1" id="messageThread" aria-live="polite">
                    <div class="chat-empty text-center text-muted py-5" id="threadEmpty">
                        <i data-lucide="messages-square" style="width:42px;height:42px"></i>
                        <p class="mt-2 mb-0">Sélectionnez une conversation ou démarrez un nouvel échange.</p>
                    </div>
                </div>

                <form class="chat-composer border-top p-3 d-none gap-2" id="composerForm">
                    <input type="hidden" name="conversation_id" id="composerConversationId">
                    <textarea class="form-control" id="composerBody" rows="1" maxlength="5000"
                              placeholder="Votre message… (Entrée pour envoyer, Maj+Entrée pour saut de ligne)"></textarea>
                    <button class="btn btn-primary align-self-end" type="submit">
                        <i data-lucide="send-horizontal"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal : conversation directe -->
<div class="modal fade" id="directModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="directForm">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title">Nouvelle conversation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label" for="directUserId">Collaborateur</label>
                    <select class="form-select" id="directUserId" name="user_id" required>
                        <option value="">— Sélectionner —</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?= (int) $user['id'] ?>">
                                <?= e(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))) ?> — <?= e($user['email']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="invalid-feedback d-none" id="directError"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Ouvrir</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal : création de groupe -->
<div class="modal fade" id="groupModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="groupForm">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title">Nouveau groupe</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="groupName">Nom du groupe</label>
                        <input class="form-control" id="groupName" name="name" minlength="3" maxlength="150" required>
                        <div class="invalid-feedback d-none" id="groupNameError"></div>
                    </div>
                    <label class="form-label">Membres</label>
                    <div class="chat-user-picklist border rounded p-2" id="groupUserList">
                        <?php foreach ($users as $user): ?>
                            <div class="form-check">
                                <input class="form-check-input group-member-check" type="checkbox"
                                       value="<?= (int) $user['id'] ?>" id="gm-<?= (int) $user['id'] ?>">
                                <label class="form-check-label" for="gm-<?= (int) $user['id'] ?>">
                                    <?= e(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))) ?>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="invalid-feedback d-none" id="groupMembersError"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Créer le groupe</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal : renommage -->
<div class="modal fade" id="renameModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form id="renameForm">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title">Renommer le groupe</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <input class="form-control" id="renameInput" name="name" minlength="3" maxlength="150" required>
                    <div class="invalid-feedback d-none" id="renameError"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal : ajout de membres -->
<div class="modal fade" id="membersModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="membersForm">
                <?= csrf_field() ?>
                <div class="modal-header">
                    <h5 class="modal-title">Ajouter des membres</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <div class="chat-user-picklist border rounded p-2" id="membersPicklist"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Ajouter</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="<?= e(asset('assets/js/chat.js')) ?>" defer></script>
