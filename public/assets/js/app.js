/* Prime Business Suite - scripts globaux (ES2024, aucun framework) */
'use strict';

document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    toggle?.addEventListener('click', () => sidebar?.classList.toggle('show'));

    // Confirmations de suppression.
    document.querySelectorAll('[data-confirm]').forEach((element) => {
        element.addEventListener('submit', (event) => {
            if (!window.confirm(element.dataset.confirm || 'Confirmer cette action ?')) {
                event.preventDefault();
            }
        });
    });

    // Client Fetch API pour les formulaires AJAX marqués data-ajax.
    document.querySelectorAll('form[data-ajax]').forEach((form) => {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const response = await fetch(form.action, {
                method: form.method || 'POST',
                body: new FormData(form),
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            const payload = await response.json().catch(() => ({}));
            if (payload.success) {
                window.location.reload();
            } else {
                window.alert(payload.error?.message || 'Une erreur est survenue.');
            }
        });
    });
});
