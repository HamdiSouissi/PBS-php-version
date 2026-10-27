/* =============================================================================
 * Prime Business Suite — messagerie collaborative
 * Vanilla ES2024, aucune dépendance. Scrutation (polling) toutes les 4 s :
 * aucune infra WebSocket requise, l'historique reste intégralement persisté.
 * ========================================================================== */
'use strict';

(function () {
    const root = document.getElementById('chatApp');
    if (!root) return;

    const csrf = root.dataset.csrf;
    const currentUserId = Number(root.dataset.currentUser);
    const state = {
        conversations: JSON.parse(root.dataset.conversations || '[]'),
        users: JSON.parse(root.dataset.users || '[]'),
        activeId: 0,
        lastMessageId: 0,
        pollTimer: null,
    };

    const els = {
        list: document.getElementById('conversationList'),
        thread: document.getElementById('messageThread'),
        empty: document.getElementById('threadEmpty'),
        header: document.getElementById('threadHeader'),
        title: document.getElementById('threadTitle'),
        participants: document.getElementById('threadParticipants'),
        groupActions: document.getElementById('groupActions'),
        composer: document.getElementById('composerForm'),
        composerConversation: document.getElementById('composerConversationId'),
        body: document.getElementById('composerBody'),
        navBadge: document.getElementById('chatNavBadge'),
    };

    const escapeHtml = (value) => String(value ?? '')
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#39;');

    async function api(url, options = {}) {
        // X-Requested-With (et non Accept: application/json) : ce dernier fait
        // basculer en mode API JWT sans session, alors que le chat s'appuie
        // sur la session navigateur et la protection CSRF.
        const response = await fetch(url, {
            method: options.method || 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                ...(options.body ? { 'Content-Type': 'application/json' } : {}),
            },
            body: options.body ? JSON.stringify({ _csrf: csrf, ...options.body }) : undefined,
        });
        const payload = await response.json().catch(() => ({}));
        if (!response.ok || payload.success === false) {
            const fields = payload.error?.fields ? ` ${Object.values(payload.error.fields).join(' ')}` : '';
            throw new Error((payload.error?.message || 'Requête impossible.') + fields);
        }
        return payload;
    }

    function formatTime(createdAt) {
        // Format serveur « YYYY-MM-DD HH:MM:SS » (heure de Paris) : on n'affiche
        // que la date et l'heure, sans conversion de fuseau côté navigateur.
        const match = /^(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2})/.exec(createdAt || '');
        return match ? `${match[3]}/${match[2]}/${match[1]} ${match[4]}:${match[5]}` : '';
    }

    function activeConversation() {
        return state.conversations.find((c) => c.id === state.activeId) || null;
    }

    function renderConversationList() {
        if (state.conversations.length === 0) {
            els.list.innerHTML = '<p class="text-muted small text-center py-4 px-3 mb-0">Aucune conversation pour le moment.</p>';
            return;
        }
        els.list.textContent = '';
        state.conversations.forEach((conversation) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = `chat-conversation w-100 text-start border-0${conversation.id === state.activeId ? ' active' : ''}`;
            button.dataset.conversationId = String(conversation.id);
            const icon = conversation.type === 'group' ? 'users' : 'user';
            const badge = conversation.unread > 0
                ? `<span class="badge text-bg-danger rounded-pill chat-unread-badge">${conversation.unread}</span>` : '';
            button.innerHTML = `
                <div class="d-flex justify-content-between align-items-start gap-2">
                    <span class="chat-conversation-name text-truncate">
                        <i data-lucide="${icon}" class="me-1"></i>${escapeHtml(conversation.name)}
                    </span>${badge}
                </div>
                <span class="chat-conversation-preview text-truncate d-block text-muted small">
                    ${escapeHtml(conversation.last_body || 'Aucun message')}
                </span>`;
            button.addEventListener('click', () => openConversation(conversation.id));
            els.list.appendChild(button);
        });
        if (window.lucide) window.lucide.createIcons();
    }

    function renderMessages(messages, append = false) {
        if (!append) els.thread.querySelectorAll('.chat-bubble').forEach((n) => n.remove());
        els.empty.hidden = messages.length > 0 || append;
        const nearBottom = els.thread.scrollHeight - els.thread.scrollTop - els.thread.clientHeight < 120;

        messages.forEach((message) => {
            if (message.id <= state.lastMessageId) return;
            state.lastMessageId = Math.max(state.lastMessageId, message.id);
            const bubble = document.createElement('div');
            bubble.className = `chat-bubble${message.mine ? ' mine' : ''}`;
            bubble.dataset.messageId = String(message.id);
            const sender = message.mine ? '' : `<div class="small fw-semibold">${escapeHtml(message.sender_name)}</div>`;
            bubble.innerHTML = `${sender}
                <div class="chat-bubble-body"></div>
                <span class="chat-meta">${escapeHtml(formatTime(message.created_at))}</span>`;
            bubble.querySelector('.chat-bubble-body').textContent = message.body;
            els.thread.appendChild(bubble);
        });

        if (nearBottom || !append) {
            els.thread.scrollTop = els.thread.scrollHeight;
        }
    }

    async function openConversation(id) {
        state.activeId = id;
        const conversation = activeConversation();
        if (!conversation) return;

        els.header.classList.remove('d-none');
        els.composer.classList.remove('d-none');
        els.composer.classList.add('d-flex');
        els.composerConversation.value = String(id);
        els.title.textContent = conversation.name;
        els.participants.textContent = conversation.participants.map((p) => p.name).join(', ');
        els.groupActions.hidden = conversation.type !== 'group'
            || conversation.created_by !== currentUserId;
        document.getElementById('renameInput').value = conversation.type === 'group' ? conversation.name : '';

        state.lastMessageId = 0;
        renderConversationList();
        try {
            const payload = await api(`/chat/messages?conversation_id=${id}&after=0`);
            renderMessages(payload.data, false);
            await api('/chat/read', { method: 'POST', body: { conversation_id: id } });
            conversation.unread = 0;
            renderConversationList();
            refreshNavBadge();
            els.body.focus();
        } catch (error) {
            window.PBSApp?.toast(error.message, 'danger');
        }
    }

    function refreshNavBadge() {
        if (!els.navBadge) return;
        const total = state.conversations.reduce((sum, c) => sum + (c.unread || 0), 0);
        els.navBadge.textContent = String(total);
        els.navBadge.hidden = total === 0;
    }

    async function pollConversations() {
        try {
            const payload = await api('/chat/conversations');
            state.conversations = payload.data;
            renderConversationList();
            refreshNavBadge();

            if (state.activeId) {
                const messages = await api(
                    `/chat/messages?conversation_id=${state.activeId}&after=${state.lastMessageId}`
                );
                if (messages.data.length > 0) {
                    renderMessages(messages.data, true);
                    await api('/chat/read', { method: 'POST', body: { conversation_id: state.activeId } });
                    const current = activeConversation();
                    if (current) current.unread = 0;
                    renderConversationList();
                    refreshNavBadge();
                }
            }
        } catch {
            /* Une scrutation ratée ne doit pas casser l'interface ; la suivante reprend. */
        }
    }

    els.composer.addEventListener('submit', async (event) => {
        event.preventDefault();
        const body = els.body.value.trim();
        if (!body || !state.activeId) return;
        els.body.value = '';
        els.body.style.height = 'auto';
        try {
            const payload = await api('/chat/messages', {
                method: 'POST',
                body: { conversation_id: state.activeId, body },
            });
            renderMessages([payload.data], true);
            await pollConversations();
        } catch (error) {
            els.body.value = body;
            window.PBSApp?.toast(error.message, 'danger');
        }
    });

    // Entrée = envoi, Maj+Entrée = saut de ligne ; textarea auto-grandissante.
    els.body.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            els.composer.requestSubmit();
        }
    });
    els.body.addEventListener('input', () => {
        els.body.style.height = 'auto';
        els.body.style.height = `${Math.min(els.body.scrollHeight, 140)}px`;
    });

    /* --- Création d'une conversation directe ------------------------------ */
    document.getElementById('directForm').addEventListener('submit', async (event) => {
        event.preventDefault();
        const errorEl = document.getElementById('directError');
        errorEl.classList.add('d-none');
        try {
            const payload = await api('/chat/conversations/direct', {
                method: 'POST',
                body: { user_id: Number(document.getElementById('directUserId').value) },
            });
            bootstrap.Modal.getInstance(document.getElementById('directModal')).hide();
            await pollConversations();
            openConversation(payload.conversation_id);
        } catch (error) {
            errorEl.textContent = error.message;
            errorEl.classList.remove('d-none');
        }
    });

    /* --- Création d'un groupe --------------------------------------------- */
    document.getElementById('groupForm').addEventListener('submit', async (event) => {
        event.preventDefault();
        const nameError = document.getElementById('groupNameError');
        const membersError = document.getElementById('groupMembersError');
        nameError.classList.add('d-none');
        membersError.classList.add('d-none');
        const members = [...document.querySelectorAll('.group-member-check:checked')].map((el) => Number(el.value));
        try {
            const payload = await api('/chat/conversations/group', {
                method: 'POST',
                body: { name: document.getElementById('groupName').value, members },
            });
            event.target.reset();
            bootstrap.Modal.getInstance(document.getElementById('groupModal')).hide();
            await pollConversations();
            openConversation(payload.conversation_id);
        } catch (error) {
            const target = members.length === 0 ? membersError : nameError;
            target.textContent = error.message;
            target.classList.remove('d-none');
        }
    });

    /* --- Renommage --------------------------------------------------------- */
    document.getElementById('renameForm').addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!state.activeId) return;
        try {
            await api(`/chat/conversations/${state.activeId}/rename`, {
                method: 'POST',
                body: { name: document.getElementById('renameInput').value },
            });
            bootstrap.Modal.getInstance(document.getElementById('renameModal')).hide();
            await pollConversations();
            const current = activeConversation();
            if (current) els.title.textContent = current.name;
        } catch (error) {
            const errorEl = document.getElementById('renameError');
            errorEl.textContent = error.message;
            errorEl.classList.remove('d-none');
        }
    });

    /* --- Ajout de membres : la liste exclut les participants actuels ------ */
    document.getElementById('addMembersBtn').addEventListener('click', () => {
        const conversation = activeConversation();
        if (!conversation) return;
        const existing = new Set(conversation.participants.map((p) => p.id));
        const picklist = document.getElementById('membersPicklist');
        picklist.textContent = '';
        state.users.filter((u) => !existing.has(u.id)).forEach((user) => {
            const wrap = document.createElement('div');
            wrap.className = 'form-check';
            wrap.innerHTML = `
                <input class="form-check-input" type="checkbox" value="${user.id}" id="am-${user.id}">
                <label class="form-check-label" for="am-${user.id}"></label>`;
            wrap.querySelector('label').textContent = `${user.name} — ${user.email}`;
            picklist.appendChild(wrap);
        });
        if (picklist.children.length === 0) {
            picklist.innerHTML = '<p class="text-muted small mb-0">Tous les collaborateurs sont déjà membres.</p>';
        }
    });

    document.getElementById('membersForm').addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!state.activeId) return;
        const members = [...document.querySelectorAll('#membersPicklist input:checked')].map((el) => Number(el.value));
        try {
            await api(`/chat/conversations/${state.activeId}/members`, { method: 'POST', body: { members } });
            bootstrap.Modal.getInstance(document.getElementById('membersModal')).hide();
            window.PBSApp?.toast('Membres ajoutés.', 'success');
            await pollConversations();
        } catch (error) {
            window.PBSApp?.toast(error.message, 'danger');
        }
    });

    /* --- Départ d'un groupe ------------------------------------------------ */
    document.getElementById('leaveGroupBtn').addEventListener('click', async () => {
        if (!state.activeId || !window.confirm('Quitter cette conversation de groupe ?')) return;
        try {
            await api(`/chat/conversations/${state.activeId}/leave`, { method: 'POST', body: {} });
            state.activeId = 0;
            state.lastMessageId = 0;
            els.header.classList.add('d-none');
            els.composer.classList.add('d-none');
            els.composer.classList.remove('d-flex');
            els.thread.querySelectorAll('.chat-bubble').forEach((n) => n.remove());
            els.empty.hidden = false;
            await pollConversations();
        } catch (error) {
            window.PBSApp?.toast(error.message, 'danger');
        }
    });

    /* --- Initialisation ---------------------------------------------------- */
    renderConversationList();
    refreshNavBadge();
    state.pollTimer = setInterval(pollConversations, 4000);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) pollConversations();
    });
})();
