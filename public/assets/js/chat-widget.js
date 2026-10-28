/* =============================================================================
 * Prime Business Suite — widget de messagerie flottant (ancre bas-droite)
 * Vanilla ES2024, aucune dépendance. Réutilise les endpoints JSON du module
 * Chat : scrutation lente des non-lus fermé, scrutation rapide ouvert.
 * ========================================================================== */
'use strict';

(function () {
    const dock = document.getElementById('chatDock');
    // Absent (permission refusée) ou page de messagerie complète : pas de doublon.
    if (!dock || window.location.pathname === '/chat') return;

    const csrf = dock.dataset.csrf;
    const els = {
        fab: document.getElementById('chatDockFab'),
        topbarBtn: document.getElementById('chatTopbarBtn'),
        panel: document.getElementById('chatDockPanel'),
        listView: document.getElementById('dockListView'),
        threadView: document.getElementById('dockThreadView'),
        list: document.getElementById('dockConversationList'),
        messages: document.getElementById('dockMessages'),
        title: document.getElementById('dockThreadTitle'),
        composer: document.getElementById('dockComposer'),
        conversationId: document.getElementById('dockConversationId'),
        body: document.getElementById('dockBody'),
        closeBtn: document.getElementById('dockCloseBtn'),
        threadCloseBtn: document.getElementById('dockThreadCloseBtn'),
        minimizeBtn: document.getElementById('dockMinimizeBtn'),
        threadMinimizeBtn: document.getElementById('dockThreadMinimizeBtn'),
        backBtn: document.getElementById('dockBackBtn'),
    };

    const state = {
        conversations: [],
        activeId: 0,
        lastMessageId: 0,
        isOpen: false,
        fastTimer: null,
        slowTimer: null,
    };

    const escapeHtml = (value) => String(value ?? '')
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#39;');

    async function api(url, options = {}) {
        // X-Requested-With et non Accept: application/json : ce dernier bascule
        // en mode API JWT sans session, alors que le widget s'appuie sur la
        // session navigateur et la protection CSRF.
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
            throw new Error(payload.error?.message || 'Requête impossible.');
        }
        return payload;
    }

    function formatTime(createdAt) {
        const match = /^(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2})/.exec(createdAt || '');
        return match ? `${match[3]}/${match[2]} ${match[4]}:${match[5]}` : '';
    }

    function activeConversation() {
        return state.conversations.find((c) => c.id === state.activeId) || null;
    }

    function updateBadges(total) {
        document.querySelectorAll('.chat-unread-total').forEach((badge) => {
            badge.textContent = String(total);
            badge.hidden = total === 0;
        });
    }

    function unreadTotal() {
        return state.conversations.reduce((sum, c) => sum + (c.unread || 0), 0);
    }

    function renderList() {
        if (state.conversations.length === 0) {
            els.list.innerHTML = '';
            const empty = document.createElement('p');
            empty.className = 'chat-dock-empty text-muted small mb-0';
            empty.textContent = 'Aucune conversation. Démarrez un échange depuis la messagerie complète.';
            els.list.appendChild(empty);
            return;
        }
        els.list.textContent = '';
        state.conversations.forEach((conversation) => {
            const item = document.createElement('button');
            item.type = 'button';
            item.className = `chat-dock-item${conversation.unread > 0 ? ' unread' : ''}`;
            item.dataset.conversationId = String(conversation.id);
            item.innerHTML = `
                <span class="chat-dock-item-row">
                    <i class="chat-dock-item-icon" data-lucide="${conversation.type === 'group' ? 'users' : 'user'}"></i>
                    <span class="chat-dock-item-name"></span>
                    <span class="badge text-bg-danger rounded-pill chat-dock-item-badge" hidden></span>
                </span>
                <span class="chat-dock-item-preview"></span>`;
            item.querySelector('.chat-dock-item-name').textContent = conversation.name;
            const badge = item.querySelector('.chat-dock-item-badge');
            if (conversation.unread > 0) {
                badge.textContent = String(conversation.unread);
                badge.hidden = false;
            }
            item.querySelector('.chat-dock-item-preview').textContent = conversation.last_body || 'Aucun message';
            item.addEventListener('click', () => openConversation(conversation.id));
            els.list.appendChild(item);
        });
        if (window.lucide) window.lucide.createIcons();
    }

    function renderMessages(messages, append = false) {
        if (!append) els.messages.textContent = '';
        const nearBottom = els.messages.scrollHeight - els.messages.scrollTop - els.messages.clientHeight < 120;
        messages.forEach((message) => {
            if (message.id <= state.lastMessageId) return;
            state.lastMessageId = Math.max(state.lastMessageId, message.id);
            const bubble = document.createElement('div');
            bubble.className = `chat-bubble${message.mine ? ' mine' : ''}`;
            const sender = message.mine ? '' : '<div class="small fw-semibold"></div>';
            bubble.innerHTML = `${sender}
                <div class="chat-bubble-body"></div>
                <span class="chat-meta"></span>`;
            if (!message.mine) {
                bubble.querySelector('.small').textContent = message.sender_name;
            }
            bubble.querySelector('.chat-bubble-body').textContent = message.body;
            bubble.querySelector('.chat-meta').textContent = formatTime(message.created_at);
            els.messages.appendChild(bubble);
        });
        if (nearBottom || !append) els.messages.scrollTop = els.messages.scrollHeight;
    }

    async function refreshList() {
        const payload = await api('/chat/conversations');
        state.conversations = payload.data;
        updateBadges(payload.unread_total ?? unreadTotal());
        renderList();
    }

    async function refreshThread() {
        if (!state.activeId) return;
        const payload = await api(`/chat/messages?conversation_id=${state.activeId}&after=${state.lastMessageId}`);
        if (payload.data.length > 0) {
            renderMessages(payload.data, true);
            await api('/chat/read', { method: 'POST', body: { conversation_id: state.activeId } });
            const current = activeConversation();
            if (current) current.unread = 0;
            updateBadges(unreadTotal());
            renderList();
        }
    }

    async function openConversation(id) {
        state.activeId = id;
        const conversation = state.conversations.find((c) => c.id === id);
        if (!conversation) return;
        els.conversationId.value = String(id);
        els.title.textContent = conversation.name;
        els.listView.hidden = true;
        els.threadView.hidden = false;
        state.lastMessageId = 0;
        try {
            const payload = await api(`/chat/messages?conversation_id=${id}&after=0`);
            renderMessages(payload.data, false);
            await api('/chat/read', { method: 'POST', body: { conversation_id: id } });
            conversation.unread = 0;
            updateBadges(unreadTotal());
            renderList();
            els.body.focus();
        } catch (error) {
            window.PBSApp?.toast(error.message, 'danger');
        }
    }

    function backToList() {
        state.activeId = 0;
        state.lastMessageId = 0;
        els.threadView.hidden = true;
        els.listView.hidden = false;
        refreshList().catch(() => {});
    }

    async function openPanel() {
        state.isOpen = true;
        // Une réduction conserve la vue courante ; une fermeture (X) a remis
        // la liste par défaut. On garantit la cohérence de l'affichage.
        if (state.activeId) {
            els.listView.hidden = true;
            els.threadView.hidden = false;
        } else {
            els.listView.hidden = false;
            els.threadView.hidden = true;
        }
        els.panel.hidden = false;
        [els.fab, els.topbarBtn].forEach((btn) => btn && btn.setAttribute('aria-expanded', 'true'));
        clearInterval(state.slowTimer);
        state.fastTimer = setInterval(tick, 4000);
        try {
            await refreshList();
            if (state.activeId) {
                const payload = await api(`/chat/messages?conversation_id=${state.activeId}&after=${state.lastMessageId}`);
                renderMessages(payload.data, true);
            }
        } catch {
            /* Une scrutation ratée ne bloque pas l'ouverture ; la suivante reprend. */
        }
    }

    // Réduire : masque le panneau en conservant la conversation ouverte ;
    // la réouverture ramène exactement le fil en cours.
    function minimizePanel() {
        state.isOpen = false;
        els.panel.hidden = true;
        [els.fab, els.topbarBtn].forEach((btn) => btn && btn.setAttribute('aria-expanded', 'false'));
        clearInterval(state.fastTimer);
        state.slowTimer = setInterval(refreshUnreadOnly, 20000);
    }

    // Fermer : réduit ET revient à la liste des conversations.
    function closePanel() {
        minimizePanel();
        state.activeId = 0;
        state.lastMessageId = 0;
        els.threadView.hidden = true;
        els.listView.hidden = false;
    }

    async function refreshUnreadOnly() {
        try {
            const payload = await api('/chat/unread');
            updateBadges(payload.total);
        } catch {
            /* silencieux : la scrutation suivante reprend */
        }
    }

    async function tick() {
        try {
            await refreshList();
            if (state.activeId) await refreshThread();
        } catch {
            /* une scrutation ratée ne doit pas casser le widget */
        }
    }

    /* --- Événements -------------------------------------------------------- */
    // La bulle et l'icône topbar basent sur l'état RÉEL du panneau : un
    // décalage éventuel du drapeau mémoire ne peut jamais les rendre muettes.
    [els.fab, els.topbarBtn].forEach((btn) => {
        btn?.addEventListener('click', () => (els.panel.hidden ? openPanel() : minimizePanel()));
    });
    // « − » réduit en conservant la conversation ; « X » ferme et revient à la liste.
    // Le chaînage optionnel évite qu'un élément absent neutralise tout le widget.
    els.minimizeBtn?.addEventListener('click', minimizePanel);
    els.threadMinimizeBtn?.addEventListener('click', minimizePanel);
    els.closeBtn?.addEventListener('click', closePanel);
    els.threadCloseBtn?.addEventListener('click', closePanel);
    els.backBtn?.addEventListener('click', backToList);

    els.composer?.addEventListener('submit', async (event) => {
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
            await refreshList();
        } catch (error) {
            els.body.value = body;
            window.PBSApp?.toast(error.message, 'danger');
        }
    });

    els.body?.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            els.composer.requestSubmit();
        }
    });
    els.body?.addEventListener('input', () => {
        els.body.style.height = 'auto';
        els.body.style.height = `${Math.min(els.body.scrollHeight, 96)}px`;
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && state.isOpen) minimizePanel();
    });
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden && state.isOpen) tick();
    });

    /* --- Initialisation ---------------------------------------------------- */
    updateBadges(Number(dock.dataset.initialUnread || 0));
    state.slowTimer = setInterval(refreshUnreadOnly, 20000);
})();
