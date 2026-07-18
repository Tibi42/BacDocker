/**
 * Panneau « NOUS REJOINDRE » (desktop + mobile).
 * Garde anti double-init : DOMContentLoaded + turbo:load ne doivent pas empiler les listeners
 * (sinon un clic peut ouvrir puis refermer dans le même tick).
 */

let escapeHandlerInstalled = false;

function installEscapeHandlerOnce() {
    if (escapeHandlerInstalled) {
        return;
    }
    escapeHandlerInstalled = true;
    document.addEventListener('keydown', (e) => {
        if (e.key !== 'Escape') {
            return;
        }
        const panel = document.getElementById('join-panel');
        const joinBtn = document.getElementById('join-btn');
        const mobileForm = document.getElementById('mobile-join-form');
        const mobileJoinBtn = document.getElementById('mobile-join-btn');

        const desktopOpen =
            panel &&
            joinBtn &&
            panel.style.maxHeight &&
            panel.style.maxHeight !== '0' &&
            panel.style.maxHeight !== '0px';
        if (desktopOpen) {
            panel.style.maxHeight = '0';
            joinBtn.classList.remove('ring-2', 'ring-white/30');
            const headerBar = joinBtn.closest('header')?.querySelector('.header-container');
            if (headerBar) {
                headerBar.classList.remove('rounded-b-none', 'border-b-0');
            }
        }

        const mobileOpen =
            mobileForm &&
            mobileJoinBtn &&
            mobileForm.style.maxHeight &&
            mobileForm.style.maxHeight !== '0' &&
            mobileForm.style.maxHeight !== '0px';
        if (mobileOpen) {
            mobileForm.style.maxHeight = '0';
            mobileJoinBtn.classList.remove('ring-2', 'ring-white/30');
        }
    });
}

function initJoinPanel() {
    installEscapeHandlerOnce();

    // Desktop
    const btn = document.getElementById('join-btn');
    const panel = document.getElementById('join-panel');

    if (btn && panel && btn.dataset.joinPanelInit !== '1') {
        btn.dataset.joinPanelInit = '1';

        let open = false;

        const headerBar = btn.closest('header')?.querySelector('.header-container');

        function openPanel() {
            open = true;
            panel.style.maxHeight = panel.scrollHeight + 'px';
            btn.classList.add('ring-2', 'ring-white/30');
            if (headerBar) {
                headerBar.classList.add('rounded-b-none', 'border-b-0');
            }
            setTimeout(() => panel.querySelector('input')?.focus(), 300);
        }

        function closePanel() {
            open = false;
            panel.style.maxHeight = '0';
            btn.classList.remove('ring-2', 'ring-white/30');
            if (headerBar) {
                headerBar.classList.remove('rounded-b-none', 'border-b-0');
            }
        }

        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            if (open) {
                closePanel();
            } else {
                openPanel();
            }
        });
    }

    // Mobile (menu burger)
    const mobileBtn = document.getElementById('mobile-join-btn');
    const mobileForm = document.getElementById('mobile-join-form');

    if (mobileBtn && mobileForm && mobileBtn.dataset.joinPanelInit !== '1') {
        mobileBtn.dataset.joinPanelInit = '1';

        let mobileOpen = false;

        function openMobile() {
            mobileOpen = true;
            mobileForm.style.maxHeight = mobileForm.scrollHeight + 'px';
            mobileBtn.classList.add('ring-2', 'ring-white/30');
            setTimeout(() => mobileForm.querySelector('input')?.focus(), 300);
        }

        function closeMobile() {
            mobileOpen = false;
            mobileForm.style.maxHeight = '0';
            mobileBtn.classList.remove('ring-2', 'ring-white/30');
        }

        mobileBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            if (mobileOpen) {
                closeMobile();
            } else {
                openMobile();
            }
        });
    }
}

function initRegisterModal() {
    const modal = document.getElementById('register-modal');
    if (!modal) return;

    const closeBtn = document.getElementById('register-close');
    const overlay = document.getElementById('register-overlay');

    function openModal() {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        setTimeout(() => {
            const input = modal.querySelector('input:not([type="hidden"])');
            if (input && !input.value) input.focus();
            else {
                const username = document.getElementById('register-username');
                if (username) username.focus();
            }
        }, 100);
    }

    function closeModal() {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        // Clean URL
        const url = new URL(window.location);
        url.searchParams.delete('open');
        url.searchParams.delete('email');
        window.history.replaceState({}, '', url);
    }

    if (closeBtn) closeBtn.addEventListener('click', closeModal);
    if (overlay) overlay.addEventListener('click', closeModal);

    // Open modal via [data-open-register] buttons
    document.querySelectorAll('[data-open-register]').forEach((btn) => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            openModal();
        });
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
            closeModal();
        }
    });

    // Toggle password visibility
    modal.querySelectorAll('[data-toggle-password]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const input = document.getElementById(btn.dataset.togglePassword);
            if (!input) return;
            const isPassword = input.type === 'password';
            input.type = isPassword ? 'text' : 'password';
            btn.querySelector('.eye-closed').classList.toggle('hidden', isPassword);
            btn.querySelector('.eye-open').classList.toggle('hidden', !isPassword);
        });
    });

    // Password confirmation validation
    const form = modal.querySelector('form');
    const pwd = document.getElementById('register-password');
    const confirm = document.getElementById('register-password-confirm');
    const error = document.getElementById('register-password-error');

    if (form && pwd && confirm && error) {
        function checkMatch() {
            if (confirm.value && pwd.value !== confirm.value) {
                error.classList.remove('hidden');
                confirm.classList.add('border-red-500');
                confirm.classList.remove('border-custom');
            } else {
                error.classList.add('hidden');
                confirm.classList.remove('border-red-500');
                confirm.classList.add('border-custom');
            }
        }

        pwd.addEventListener('input', checkMatch);
        confirm.addEventListener('input', checkMatch);

        form.addEventListener('submit', (e) => {
            if (pwd.value !== confirm.value) {
                e.preventDefault();
                error.classList.remove('hidden');
                confirm.classList.add('border-red-500');
                confirm.classList.remove('border-custom');
                confirm.focus();
            }
        });
    }

    // Auto-open if ?open=register
    if (new URLSearchParams(window.location.search).get('open') === 'register') {
        openModal();
    }
}

function bootJoinPanel() {
    initJoinPanel();
    initRegisterModal();

    const openParam = new URLSearchParams(window.location.search).get('open');

    // Auto-open login panel when redirected after a login error (?open=login)
    if (openParam === 'login') {
        const panel = document.getElementById('join-panel');
        const btn = document.getElementById('join-btn');
        if (panel && btn) {
            panel.style.maxHeight = panel.scrollHeight + 'px';
            btn.classList.add('ring-2', 'ring-white/30');
            const headerBar = btn.closest('header')?.querySelector('.header-container');
            if (headerBar) {
                headerBar.classList.add('rounded-b-none', 'border-b-0');
            }
        }

        // Idem sur mobile : ouvrir le tiroir de menu et le formulaire de connexion,
        // sinon la redirection ?open=login n'affiche rien de visible en dessous de lg.
        const drawer = document.getElementById('mobile-menu-drawer');
        const mobileMenuBtn = document.getElementById('mobile-menu-btn');
        const mobileForm = document.getElementById('mobile-join-form');
        const mobileBtn = document.getElementById('mobile-join-btn');
        if (drawer && mobileMenuBtn && mobileForm && mobileBtn) {
            drawer.classList.remove('pointer-events-none');
            drawer.classList.add('menu-open');
            mobileMenuBtn.classList.add('hidden');
            document.body.style.overflow = 'hidden';
            mobileForm.style.maxHeight = mobileForm.scrollHeight + 'px';
            mobileBtn.classList.add('ring-2', 'ring-white/30');
        }
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootJoinPanel, { once: true });
} else {
    bootJoinPanel();
}

document.addEventListener('turbo:load', initJoinPanel);
