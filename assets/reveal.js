/**
 * Révélation progressive des éléments .reveal au scroll (mobile).
 * Desktop (>=1024px) : les éléments sont déjà visibles via CSS, pas d'animation.
 *
 * Gère : DOMContentLoaded, Turbo navigation, et bfcache (back/forward).
 */

let currentObserver = null;

function initReveal() {
    // Déconnecter l'ancien observer si on ré-initialise (navigation Turbo)
    if (currentObserver) {
        currentObserver.disconnect();
        currentObserver = null;
    }

    const revealEls = document.querySelectorAll('.reveal');
    if (!revealEls.length) return;

    function activateElement(el) {
        if (el.classList.contains('active')) return;
        el.classList.add('active');
    }

    function activateInstantly(el) {
        if (el.classList.contains('active')) return;
        el.classList.add('reveal-instant', 'active');
        requestAnimationFrame(() => {
            el.classList.remove('reveal-instant');
        });
    }

    // Marquer les éléments déjà dans le viewport comme immédiatement visibles.
    // Les lectures (getBoundingClientRect) et écritures (classList) sont
    // séparées en deux passes pour éviter un reflow forcé par élément.
    function markVisibleReveals() {
        const toActivate = [];
        revealEls.forEach((el) => {
            if (el.classList.contains('active')) return;
            const rect = el.getBoundingClientRect();
            if (rect.width > 0 && rect.height > 0 &&
                rect.top < window.innerHeight + 50 && rect.bottom > -50) {
                toActivate.push(el);
            }
        });
        toActivate.forEach(activateInstantly);
    }

    // Exécuter immédiatement + après 2 frames (le layout peut ne pas être prêt après Turbo)
    markVisibleReveals();
    requestAnimationFrame(() => {
        requestAnimationFrame(markVisibleReveals);
    });

    // IntersectionObserver pour les éléments hors viewport
    currentObserver = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                activateElement(entry.target);
                currentObserver.unobserve(entry.target);
            }
        });
    }, {
        threshold: 0.05,
        rootMargin: '0px 0px -30px 0px'
    });

    revealEls.forEach((el) => {
        if (!el.classList.contains('active')) {
            currentObserver.observe(el);
        }
    });

    // Fallback scroll : si l'IntersectionObserver ne se déclenche pas (certains navigateurs
    // après Turbo ou bfcache), on vérifie manuellement au scroll.
    let scrollFallbackActive = true;
    function onScrollFallback() {
        let allActive = true;
        const toActivate = [];
        revealEls.forEach((el) => {
            if (el.classList.contains('active')) return;
            allActive = false;
            const rect = el.getBoundingClientRect();
            if (rect.top < window.innerHeight + 50 && rect.bottom > -50) {
                toActivate.push(el);
            }
        });
        toActivate.forEach(activateElement);
        if (allActive) {
            window.removeEventListener('scroll', onScrollFallback);
            scrollFallbackActive = false;
        }
    }

    window.addEventListener('scroll', onScrollFallback, { passive: true });

    // Nettoyer le scroll listener quand on navigue (Turbo)
    document.addEventListener('turbo:before-cache', () => {
        if (scrollFallbackActive) {
            window.removeEventListener('scroll', onScrollFallback);
        }
    }, { once: true });
}

// Restauration depuis le bfcache (back/forward sans Turbo)
window.addEventListener('pageshow', (event) => {
    if (event.persisted) {
        initReveal();
    }
});

document.addEventListener('DOMContentLoaded', initReveal);
document.addEventListener('turbo:load', initReveal);
