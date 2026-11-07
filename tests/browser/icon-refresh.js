/* Test autonome : ouvrir icon-refresh.html dans un navigateur. */
document.addEventListener('DOMContentLoaded', async () => {
    const result = document.getElementById('test-result');
    const pause = () => new Promise((resolve) => setTimeout(resolve, 100));
    const check = (condition, message) => { if (!condition) throw new Error(message); };
    try {
        const link = document.getElementById('test-link');
        const originalIcon = link.querySelector('svg');
        check(originalIcon !== null, 'Icône initiale absente');
        await pause();
        check(link.querySelector('svg') === originalIcon, 'Icône initiale remplacée en boucle');
        const dynamic = document.getElementById('dynamic-icons');
        dynamic.innerHTML = '<span data-lucide="mail"></span><i class="bi bi-bank"></i>';
        await pause();
        check(dynamic.querySelector('svg.lucide-mail') && dynamic.querySelector('svg.lucide-landmark'), 'Icônes dynamiques absentes');
        check(link.querySelector('svg') === originalIcon, 'Navigation modifiée lors du rendu dynamique');
        window.PBSApp.refreshIcons();
        check(link.querySelector('svg') === originalIcon, 'Rendu explicite non idempotent');
        let clicks = 0;
        link.addEventListener('click', (event) => { event.preventDefault(); clicks++; });
        for (let i = 0; i < 10; i++) link.querySelector('svg').dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
        check(clicks === 10, 'Clics perdus');
        result.textContent = 'OK';
        result.dataset.testStatus = 'passed';
    } catch (error) {
        result.textContent = 'Échec : ' + error.message;
        result.dataset.testStatus = 'failed';
        console.error(error);
    }
});
