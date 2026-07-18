/**
 * Initialise le menu de navigation mobile (drawer / tiroir latéral).
 *
 * - Réinitialise toujours l'état fermé au chargement (Turbo peut restaurer
 *   un état ouvert depuis son cache).
 * - Garde anti-double-init via dataset.menuInit pour éviter l'empilement
 *   de listeners sur DOMContentLoaded + turbo:load.
 * - Bloque le scroll du body quand le menu est ouvert.
 * - Ferme le menu au clic sur l'overlay ou sur un lien de navigation.
 */
function initMobileMenu() {
    const btn = document.getElementById('mobile-menu-btn');
    const drawer = document.getElementById('mobile-menu-drawer');

    if (!btn || !drawer) return;

    // Toujours réinitialiser l'état au chargement (Turbo peut restaurer un état ouvert depuis le cache)
    btn.classList.remove('hidden');
    drawer.classList.remove('menu-open');
    drawer.classList.add('pointer-events-none');
    document.body.style.overflow = '';

    if (btn.dataset.menuInit) return;
    btn.dataset.menuInit = '1';

    let isOpen = false;

    function open() {
        isOpen = true;
        drawer.classList.remove('pointer-events-none');
        drawer.classList.add('menu-open');
        btn.classList.add('hidden');
        document.body.style.overflow = 'hidden';
    }

    function close() {
        isOpen = false;
        drawer.classList.remove('menu-open');
        drawer.classList.add('pointer-events-none');
        btn.classList.remove('hidden');
        document.body.style.overflow = '';
    }

    btn.addEventListener('click', (e) => { e.stopPropagation(); isOpen ? close() : open(); });
    document.getElementById('mobile-menu-overlay')?.addEventListener('click', close);
    document.querySelectorAll('.mobile-nav-link').forEach(link => {
        link.addEventListener('click', close);
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && isOpen) close();
    });
}

document.addEventListener('DOMContentLoaded', initMobileMenu);
document.addEventListener('turbo:load', initMobileMenu);
