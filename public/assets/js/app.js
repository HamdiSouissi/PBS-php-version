/* =============================================================================
 * Prime Business Suite — scripts globaux
 * ES2024, aucune dépendance de build. Bootstrap (bundle) et Lucide sont chargés
 * par le layout. Toutes les fonctionnalités sont progressives : sans JS,
 * navigation et formulaires restent pleinement utilisables.
 * ========================================================================== */
'use strict';

/* -----------------------------------------------------------------------------
 * Table de correspondance Bootstrap Icons -> Lucide (unique jeu d'icônes rendu)
 * -------------------------------------------------------------------------- */
const ICON_MAP = {
    'speedometer2': 'gauge',
    'building': 'building-2',
    'people-fill': 'users',
    'people': 'users',
    'person': 'user',
    'person-badge': 'id-card',
    'person-plus': 'user-plus',
    'calendar-week': 'calendar-range',
    'calendar3': 'calendar',
    'calendar-plus': 'calendar-plus',
    'cash-stack': 'banknote',
    'cash-coin': 'coins',
    'bank': 'landmark',
    'receipt': 'receipt',
    'file-earmark-text': 'file-text',
    'file-earmark-pdf': 'file-text',
    'file-earmark-plus': 'file-plus-2',
    'arrow-repeat': 'repeat',
    'wallet2': 'wallet',
    'journal-book': 'book-open',
    'journal-plus': 'circle-plus',
    'pie-chart': 'pie-chart',
    'shield-check': 'shield-check',
    'shield-lock': 'shield-lock',
    'shield-plus': 'shield-plus',
    'shield-exclamation': 'shield-alert',
    'folder2-open': 'folder-open',
    'stars': 'sparkles',
    'clock-history': 'history',
    'graph-up-arrow': 'trending-up',
    'house': 'house',
    'envelope': 'mail',
    'gear': 'settings',
    'pen-square': 'pen-square',
    'reply': 'reply',
    'arrow-left': 'arrow-left',
    'paperclip': 'paperclip',
    'send': 'send',
    'star': 'star',
    'mail-warning': 'mail-warning',
    'lock': 'lock',
    'key': 'key-round',
    'list': 'menu',
    'search': 'search',
    'pencil': 'pencil',
    'trash': 'trash-2',
    'plus-lg': 'plus',
    'x-circle': 'circle-x',
    'check-lg': 'check',
    'check2': 'check',
    'check2-circle': 'circle-check',
    'download': 'download',
    'upload': 'upload',
    'eye': 'eye',
    'save': 'save',
    'box-arrow-right': 'log-out',
    'box-arrow-in-right': 'log-in',
    'info-circle-fill': 'info',
    'lightning-charge': 'zap',
    'calculator': 'calculator',
    'database-check': 'database-check',
    'bookmark-plus': 'bookmark-plus',
    'exclamation-triangle': 'triangle-alert',
    'hourglass-split': 'hourglass',
    'currency-euro': 'euro',
    'bug': 'bug',
};

const App = {
    initialized: false,

    /* Stockage local tolérant : certains navigateurs (mode confidentiel,
     * politiques de sécurité) lèvent une exception à l'écriture. */
    store: {
        get(key) {
            try { return localStorage.getItem(key); } catch { return null; }
        },
        set(key, value) {
            try { localStorage.setItem(key, value); } catch { /* stockage indisponible */ }
        },
        remove(key) {
            try { localStorage.removeItem(key); } catch { /* idem */ }
        },
        json(key, fallback) {
            try { return JSON.parse(this.get(key) ?? '') ?? fallback; } catch { return fallback; }
        },
    },

    init() {
        if (this.initialized) return;
        this.initialized = true;
        this.initTheme();
        this.initClock();
        this.initIcons();
        this.initSidebar();
        this.initToasts();
        this.initConfirm();
        this.initAjaxForms();
        this.initCountUp();
        this.initTableSearch();
        this.initTableSort();
        this.initScrollTargets();
        this.initFormGuard();
        this.initPalette();
        this.initShortcuts();
        this.initModals();
    },

    /* --- Modales : rendues dans <body> pour éviter tout ancêtre à -------
       contexte de empilement (transform, backdrop-filter) qui les rendrait
       grisées et incliquables derrière le voile backdrop. ---------------- */
    initModals() {
        document.querySelectorAll('.modal').forEach((modal) => {
            modal.addEventListener('show.bs.modal', () => {
                if (modal.parentElement !== document.body) {
                    document.body.appendChild(modal);
                }
            });
        });
    },

    /* --- Thème (auto / clair / sombre), persisté, sans clignotement ------ */
    initTheme() {
        const root = document.documentElement;
        const stored = this.store.get('pbs-theme');
        this.applyTheme(stored || 'auto');

        document.querySelectorAll('[data-theme-toggle]').forEach((btn) => {
            btn.addEventListener('click', () => {
                const order = ['auto', 'light', 'dark'];
                const current = root.dataset.theme || 'auto';
                const next = order[(order.indexOf(current) + 1) % order.length];
                this.applyTheme(next);
                this.store.set('pbs-theme', next);
            });
        });
    },

    applyTheme(theme) {
        const root = document.documentElement;
        root.dataset.theme = theme;
        // Bootstrap aligne ses propres composants (dropdowns, modales, tableaux).
        root.dataset.bsTheme = theme === 'dark' ||
            (theme === 'auto' && window.matchMedia('(prefers-color-scheme: dark)').matches)
            ? 'dark' : 'light';
        document.querySelectorAll('[data-theme-toggle]').forEach((btn) => {
            const labels = { auto: 'Automatique (suit le système)', light: 'Mode clair', dark: 'Mode sombre' };
            btn.setAttribute('aria-label', `Basculer le thème — actif : ${labels[theme]}`);
            btn.title = labels[theme];
        });
        document.dispatchEvent(new CustomEvent('pbs:theme-changed', { detail: { theme, dark: root.dataset.bsTheme === 'dark' } }));
    },

    /* --- Horloge légale Europe/Paris, rendue côté serveur puis vivante --- */
    initClock() {
        const clock = document.getElementById('pbsClock');
        if (!clock || typeof Intl === 'undefined') return;
        const dateEl = clock.querySelector('[data-clock-date]');
        const timeEl = clock.querySelector('[data-clock-time]');
        const dateFmt = new Intl.DateTimeFormat('fr-FR', {
            timeZone: 'Europe/Paris', day: '2-digit', month: '2-digit', year: 'numeric',
        });
        const timeFmt = new Intl.DateTimeFormat('fr-FR', {
            timeZone: 'Europe/Paris', hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: false,
        });
        const tick = () => {
            const now = new Date();
            if (dateEl) dateEl.textContent = dateFmt.format(now);
            if (timeEl) timeEl.textContent = timeFmt.format(now).replace(/\u202f/g, ' ');
        };
        tick();
        // On se cale sur la seconde suivante pour un décompre régulier.
        setTimeout(() => {
            tick();
            setInterval(tick, 1000);
        }, 1000 - (Date.now() % 1000));
    },

    /* --- Icônes : Lucide + compatibilité des anciens <i class="bi-…"> ---- */
    initIcons() {
        const upgrade = (rootNode = document) => {
            rootNode.querySelectorAll('i.bi, i[class*="bi-"]').forEach((el) => {
                const match = el.className.match(/bi-([a-z0-9-]+)/);
                if (!match) return;
                const lucideName = ICON_MAP[match[1]];
                if (!lucideName) return;
                el.setAttribute('data-lucide', lucideName);
                el.removeAttribute('class');
                el.classList.add('pbs-icon-slot');
            });
            if (window.lucide) window.lucide.createIcons();
        };
        upgrade();

        // Gère les noeuds ajoutés dynamiquement (lignes de facture, modales…).
        let scheduled = false;
        const observer = new MutationObserver((mutations) => {
            if (!mutations.some((m) => m.addedNodes.length)) return;
            if (scheduled) return;
            scheduled = true;
            requestAnimationFrame(() => {
                scheduled = false;
                upgrade();
            });
        });
        observer.observe(document.body, { childList: true, subtree: true });
    },

    /* --- Sidebar : tiroir mobile + rail repliable sur desktop ------------ */
    initSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        const mobileToggle = document.getElementById('sidebarToggle');
        const railToggle = document.getElementById('railToggle');
        if (!sidebar) return;

        const closeMobile = () => {
            sidebar.classList.remove('show');
            overlay?.classList.remove('show');
        };

        mobileToggle?.addEventListener('click', () => {
            sidebar.classList.toggle('show');
            overlay?.classList.toggle('show', sidebar.classList.contains('show'));
        });
        overlay?.addEventListener('click', closeMobile);
        window.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeMobile();
        });

        if (this.store.get('pbs-sidebar-rail') === '1') {
            document.body.classList.add('sidebar-collapsed');
        }
        railToggle?.addEventListener('click', () => {
            document.body.classList.toggle('sidebar-collapsed');
            this.store.set(
                'pbs-sidebar-rail',
                document.body.classList.contains('sidebar-collapsed') ? '1' : '0'
            );
        });
    },

    /* --- Toasts : fermeture, expiration, API globale --------------------- */
    initToasts() {
        document.addEventListener('click', (e) => {
            const close = e.target.closest('[data-toast-close]');
            if (close) close.closest('.app-toast')?.remove();
        });
        document.querySelectorAll('.app-toast').forEach((toast) => this.scheduleDismiss(toast));
    },

    scheduleDismiss(toast, ms = 5000) {
        setTimeout(() => {
            toast.classList.add('is-leaving');
            toast.addEventListener('animationend', () => toast.remove(), { once: true });
        }, ms);
    },

    toast(message, type = 'info', title = '') {
        const region = document.getElementById('toastRegion');
        if (!region) return;
        const icons = { success: 'circle-check', warning: 'triangle-alert', danger: 'circle-x', error: 'circle-x', info: 'info' };
        const el = document.createElement('div');
        el.className = `app-toast is-${type}`;
        el.setAttribute('role', type === 'danger' || type === 'error' ? 'alert' : 'status');
        el.innerHTML = `
            <span class="toast-icon" data-lucide="${icons[type] || 'info'}"></span>
            <span class="toast-body">
                ${title ? `<span class="toast-title"></span>` : ''}
                <span class="toast-message"></span>
            </span>
            <button type="button" class="toast-close" data-toast-close aria-label="Fermer" data-lucide="x"></button>`;
        el.querySelector('.toast-message').textContent = message;
        if (title) el.querySelector('.toast-title').textContent = title;
        region.appendChild(el);
        if (window.lucide) window.lucide.createIcons();
        this.scheduleDismiss(el);
    },

    /* --- Confirmation élégante remplaçant window.confirm ----------------- */
    initConfirm() {
        const modalEl = document.getElementById('confirmModal');
        if (!modalEl) return;
        const modal = new bootstrap.Modal(modalEl);
        const titleEl = modalEl.querySelector('[data-confirm-title]');
        const bodyEl = modalEl.querySelector('[data-confirm-body]');
        const okBtn = modalEl.querySelector('[data-confirm-ok]');
        let pending = null;

        document.addEventListener('submit', (event) => {
            const form = event.target.closest('form[data-confirm]');
            if (!form || form.dataset.confirmed === '1') return;
            event.preventDefault();
            pending = form;
            titleEl.textContent = form.dataset.confirmTitle || 'Confirmer l’action';
            bodyEl.textContent = form.dataset.confirm || 'Confirmer cette action ?';
            okBtn.classList.toggle('btn-danger', form.dataset.confirmDanger !== '0');
            modal.show();
        });

        okBtn.addEventListener('click', () => {
            if (!pending) return;
            pending.dataset.confirmed = '1';
            modal.hide();
            pending.requestSubmit();
            pending = null;
        });
    },

    /* --- Formulaires AJAX (conservent le comportement existant) ---------- */
    initAjaxForms() {
        document.querySelectorAll('form[data-ajax]').forEach((form) => {
            form.addEventListener('submit', async (event) => {
                event.preventDefault();
                try {
                    const response = await fetch(form.action, {
                        method: form.method || 'POST',
                        body: new FormData(form),
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    const payload = await response.json().catch(() => ({}));
                    if (payload.success) {
                        window.location.reload();
                    } else {
                        this.toast(payload.error?.message || 'Une erreur est survenue.', 'danger');
                    }
                } catch {
                    this.toast('Réponse réseau impossible.', 'danger');
                }
            });
        });
    },

    /* --- Compteurs animés (montants et KPI) ------------------------------ */
    initCountUp() {
        const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        document.querySelectorAll('[data-countup]').forEach((el) => {
            const target = parseFloat(el.dataset.countup);
            if (Number.isNaN(target)) return;
            const decimals = parseInt(el.dataset.decimals || '0', 10);
            const currency = el.dataset.currency || '';
            const format = (n) => {
                const text = n.toLocaleString('fr-FR', {
                    minimumFractionDigits: decimals,
                    maximumFractionDigits: decimals,
                });
                return currency ? `${text} ${currency}` : text;
            };
            if (reduce) {
                el.textContent = format(target);
                return;
            }
            const duration = 750;
            const start = performance.now();
            const tick = (now) => {
                const p = Math.min((now - start) / duration, 1);
                const eased = 1 - Math.pow(1 - p, 3);
                el.textContent = format(target * eased);
                if (p < 1) requestAnimationFrame(tick);
            };
            requestAnimationFrame(tick);
        });
    },

    /* --- Filtre instantané des tableaux ---------------------------------- */
    initTableSearch() {
        document.querySelectorAll('[data-table-target]').forEach((input) => {
            const table = document.querySelector(input.dataset.tableTarget);
            if (!table) return;
            input.addEventListener('input', () => {
                const q = input.value.trim().toLowerCase();
                table.querySelectorAll('tbody tr').forEach((row) => {
                    row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
                });
            });
        });
    },

    /* --- Tri client des tableaux déclaratifs (data-sortable) ------------- */
    initTableSort() {
        const parseCell = (td) => {
            const raw = (td.dataset.sort || td.textContent || '').trim();
            const numeric = raw.replace(/[ \s]/g, '').replace(',', '.').replace(/[^\d.-]/g, '');
            if (numeric !== '' && /^-?\d+(\.\d+)?$/.test(numeric)) return parseFloat(numeric);
            const date = raw.match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
            if (date) return new Date(`${date[3]}-${date[2]}-${date[1]}`).getTime();
            return raw.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
        };

        document.querySelectorAll('table[data-sortable]').forEach((table) => {
            const tbody = table.querySelector('tbody');
            if (!tbody) return;
            table.querySelectorAll('thead th').forEach((th, col) => {
                if (th.dataset.sortable === 'false') return;
                th.classList.add('sortable-th');
                th.setAttribute('aria-sort', 'none');
                th.tabIndex = 0;
                const sort = () => {
                    const stateRow = tbody.querySelector('tr td[colspan]');
                    if (stateRow) return; // état vide : rien à trier
                    const direction = th.getAttribute('aria-sort') === 'asc' ? 'desc' : 'asc';
                    [...tbody.querySelectorAll('tr')]
                        .sort((a, b) => {
                            const va = parseCell(a.children[col]);
                            const vb = parseCell(b.children[col]);
                            const cmp = typeof va === 'number' && typeof vb === 'number'
                                ? va - vb
                                : String(va).localeCompare(String(vb), 'fr');
                            return direction === 'asc' ? cmp : -cmp;
                        })
                        .forEach((row) => tbody.appendChild(row));
                    table.querySelectorAll('thead th').forEach((h) => h.setAttribute('aria-sort', 'none'));
                    th.setAttribute('aria-sort', direction);
                };
                th.addEventListener('click', sort);
                th.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        sort();
                    }
                });
            });
        });
    },

    /* --- Défilement automatique vers un élément (ex : carte d'édition) --- */
    initScrollTargets() {
        document.querySelectorAll('[data-scroll-into-view]').forEach((el) => {
            el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    },

    /* --- Protection contre la perte de saisie ---------------------------- */
    initFormGuard() {
        document.querySelectorAll('form[data-guard]').forEach((form) => {
            let dirty = false;
            form.addEventListener('input', () => { dirty = true; });
            form.addEventListener('submit', () => { dirty = false; });
            window.addEventListener('beforeunload', (e) => {
                if (!dirty) return;
                e.preventDefault();
                e.returnValue = '';
            });
        });
    },

    /* --- Palette de commandes (Cmd/Ctrl + K) ----------------------------- */
    initPalette() {
        const overlay = document.getElementById('commandPalette');
        const input = document.getElementById('paletteInput');
        const results = document.getElementById('paletteResults');
        if (!overlay || !input || !results) return;

        const items = window.PBS_DATA?.palette || [];
        const normalize = (s) => s.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
        let filtered = items;
        let activeIndex = 0;

        const recent = () => {
            try {
                return this.store.json('pbs-recent', []);
            } catch {
                return [];
            }
        };
        const remember = (href) => {
            const list = [href, ...recent().filter((h) => h !== href)].slice(0, 5);
            this.store.set('pbs-recent', JSON.stringify(list));
        };

        const render = () => {
            const q = normalize(input.value.trim());
            filtered = q === ''
                ? items
                : items.filter((it) => normalize(it.label).includes(q) || normalize(it.group).includes(q));
            activeIndex = 0;
            if (filtered.length === 0) {
                results.innerHTML = `
                    <div class="palette-empty">
                        <span data-lucide="search-x"></span>
                        <div>Aucune commande correspondante.</div>
                    </div>`;
                if (window.lucide) window.lucide.createIcons();
                return;
            }
            const recents = q === '' ? recent() : [];
            const groups = new Map();
            filtered.forEach((it) => {
                const group = recents.includes(it.href) ? 'Récent' : it.group;
                if (!groups.has(group)) groups.set(group, []);
                groups.get(group).push(it);
            });
            let flatIndex = -1;
            results.innerHTML = [...groups.entries()].map(([group, list]) => `
                <div class="palette-group-label">${group}</div>
                ${list.map((it) => {
                    flatIndex += 1;
                    return `
                    <a class="palette-item" href="${it.href}" data-index="${flatIndex}"
                       aria-selected="${flatIndex === activeIndex ? 'true' : 'false'}">
                        <span data-lucide="${it.icon}"></span>
                        <span>${it.label}</span>
                        <span class="palette-hint">${it.hint || ''}</span>
                    </a>`;
                }).join('')}`).join('');
            if (window.lucide) window.lucide.createIcons();
        };

        const open = () => {
            overlay.classList.add('show');
            input.value = '';
            render();
            requestAnimationFrame(() => input.focus());
            document.body.style.overflow = 'hidden';
        };
        const close = () => {
            overlay.classList.remove('show');
            document.body.style.overflow = '';
        };

        document.querySelectorAll('[data-palette-open]').forEach((btn) => btn.addEventListener('click', open));
        overlay.addEventListener('click', (e) => { if (e.target === overlay) close(); });

        window.addEventListener('keydown', (e) => {
            if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                overlay.classList.contains('show') ? close() : open();
            }
            if (!overlay.classList.contains('show')) return;
            if (e.key === 'Escape') close();
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                activeIndex = (activeIndex + (e.key === 'ArrowDown' ? 1 : -1) + filtered.length) % filtered.length;
                results.querySelectorAll('.palette-item').forEach((el, i) =>
                    el.setAttribute('aria-selected', i === activeIndex ? 'true' : 'false'));
                results.querySelector(`[data-index="${activeIndex}"]`)?.scrollIntoView?.({ block: 'nearest' });
            }
            if (e.key === 'Enter') {
                const target = results.querySelector(`[data-index="${activeIndex}"]`);
                if (target) {
                    remember(target.getAttribute('href'));
                    window.location.href = target.getAttribute('href');
                }
            }
        });
        input.addEventListener('input', render);
        results.addEventListener('click', (e) => {
            const item = e.target.closest('.palette-item');
            if (item) remember(item.getAttribute('href'));
        });
    },

    /* --- Aide-mémoire des raccourcis ------------------------------------- */
    initShortcuts() {
        window.addEventListener('keydown', (e) => {
            const typing = /INPUT|TEXTAREA|SELECT/.test(document.activeElement?.tagName || '');
            if (typing || e.metaKey || e.ctrlKey || e.altKey) return;
            if (e.key !== '?') return;
            e.preventDefault();
            const existing = document.getElementById('shortcutsModal');
            if (existing) {
                bootstrap.Modal.getOrCreateInstance(existing).show();
                return;
            }
            const wrap = document.createElement('div');
            wrap.innerHTML = `
            <div class="modal fade" id="shortcutsModal" tabindex="-1" aria-hidden="true">
              <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                  <div class="modal-header">
                    <h2 class="modal-title h5">Raccourcis clavier</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                  </div>
                  <div class="modal-body">
                    <div class="d-flex flex-column gap-2 small">
                      <div class="d-flex justify-content-between"><span>Recherche globale / navigation</span><span><kbd>Ctrl</kbd> + <kbd>K</kbd></span></div>
                      <div class="d-flex justify-content-between"><span>Ouvrir cette aide</span><span><kbd>?</kbd></span></div>
                      <div class="d-flex justify-content-between"><span>Fermer un panneau / une modale</span><span><kbd>Échap</kbd></span></div>
                      <div class="d-flex justify-content-between"><span>Navigation dans la palette</span><span><kbd>↑</kbd> <kbd>↓</kbd> <kbd>Entrée</kbd></span></div>
                    </div>
                  </div>
                </div>
              </div>
            </div>`;
            document.body.appendChild(wrap.firstElementChild);
            bootstrap.Modal.getOrCreateInstance(document.getElementById('shortcutsModal')).show();
        });
    },
};

document.addEventListener('DOMContentLoaded', () => App.init());

/* API publique minimale : notifications toast depuis les scripts de page. */
window.PBSApp = App;
