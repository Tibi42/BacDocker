/**
 * Menu de navigation mobile (drawer / tiroir latéral).
 *
 * Délégation d'événements au document : résiste au cache Turbo
 * (sinon dataset.menuInit bloquait le re-bind et le burger ne faisait plus rien).
 *
 * - Réinitialise l'état fermé sur turbo:load / turbo:before-cache.
 * - Bloque le scroll du body quand le menu est ouvert.
 * - Ferme au clic overlay, lien de nav, ou Escape.
 * - Burger → X via classe .is-open (pas de hide du bouton).
 */

let isOpen = false;
let listenersBound = false;

function syncBurgerState(open) {
    const btn = document.getElementById('mobile-menu-btn');
    const drawer = document.getElementById('mobile-menu-drawer');
    if (!btn || !drawer) return;

    btn.classList.toggle('is-open', open);
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    btn.setAttribute('aria-label', open ? 'Fermer le menu' : 'Menu principal');
    drawer.setAttribute('aria-hidden', open ? 'false' : 'true');
}

export function openMobileMenu() {
    const btn = document.getElementById('mobile-menu-btn');
    const drawer = document.getElementById('mobile-menu-drawer');
    if (!btn || !drawer) return;

    isOpen = true;
    drawer.classList.remove('pointer-events-none');
    drawer.classList.add('menu-open');
    syncBurgerState(true);
    document.body.style.overflow = 'hidden';
}

export function closeMobileMenu() {
    const drawer = document.getElementById('mobile-menu-drawer');
    if (!drawer) return;

    isOpen = false;
    drawer.classList.remove('menu-open');
    drawer.classList.add('pointer-events-none');
    syncBurgerState(false);
    document.body.style.overflow = '';
}

function resetMobileMenu() {
    closeMobileMenu();
}

function onDocumentClick(e) {
    if (e.target.closest('#mobile-menu-btn')) {
        e.stopPropagation();
        if (isOpen) {
            closeMobileMenu();
        } else {
            openMobileMenu();
        }
        return;
    }

    if (e.target.closest('#mobile-menu-overlay')) {
        closeMobileMenu();
        return;
    }

    if (e.target.closest('.mobile-nav-link')) {
        closeMobileMenu();
    }
}

function onDocumentKeydown(e) {
    if (e.key === 'Escape' && isOpen) {
        closeMobileMenu();
    }
}

function bindListenersOnce() {
    if (listenersBound) return;
    listenersBound = true;
    document.addEventListener('click', onDocumentClick);
    document.addEventListener('keydown', onDocumentKeydown);
}

function initMobileMenu() {
    bindListenersOnce();
    resetMobileMenu();
}

document.addEventListener('DOMContentLoaded', initMobileMenu);
document.addEventListener('turbo:load', initMobileMenu);
document.addEventListener('turbo:before-cache', resetMobileMenu);
