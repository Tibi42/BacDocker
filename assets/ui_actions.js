/**
 * Remplace les handlers inline CSP-interdits par délégation d'événements data-*.
 *
 * data-toggle-hidden="id"     → toggle class hidden
 * data-show="id"              → remove hidden
 * data-hide="id"              → add hidden
 * data-theme-toggle           → window.__toggleTheme()
 * data-close-window           → window.close()
 * data-lightbox-open="url"    → window.openLightbox(url)
 * data-lightbox-close         → window.closeLightbox(event)
 * data-lightbox-stop          → stopPropagation
 * data-toggle-password="id"   → toggle type password/text + eye icons (.eye-closed/.eye-open)
 * data-toggle-pwd             → toggle dans .relative + .eye-on/.eye-off (admin)
 */
function togglePasswordById(inputId, btn) {
    const input = document.getElementById(inputId);
    if (!input) {
        return;
    }
    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    btn.querySelector('.eye-closed')?.classList.toggle('hidden', !show);
    btn.querySelector('.eye-open')?.classList.toggle('hidden', show);
}

function togglePwdButton(btn) {
    const input = btn.closest('.relative')?.querySelector('input');
    const eyeOn = btn.querySelector('.eye-on');
    const eyeOff = btn.querySelector('.eye-off');
    if (!input) {
        return;
    }
    if (input.type === 'password') {
        input.type = 'text';
        eyeOn?.classList.add('hidden');
        eyeOff?.classList.remove('hidden');
    } else {
        input.type = 'password';
        eyeOn?.classList.remove('hidden');
        eyeOff?.classList.add('hidden');
    }
}

function onClick(event) {
    const target = event.target instanceof Element ? event.target : null;
    if (!target) {
        return;
    }

    if (target.closest('[data-lightbox-stop]')) {
        event.stopPropagation();
        return;
    }

    const toggle = target.closest('[data-toggle-hidden]');
    if (toggle) {
        document.getElementById(toggle.getAttribute('data-toggle-hidden'))?.classList.toggle('hidden');
        return;
    }

    const show = target.closest('[data-show]');
    if (show) {
        document.getElementById(show.getAttribute('data-show'))?.classList.remove('hidden');
        return;
    }

    const hide = target.closest('[data-hide]');
    if (hide) {
        document.getElementById(hide.getAttribute('data-hide'))?.classList.add('hidden');
        return;
    }

    if (target.closest('[data-theme-toggle]')) {
        window.__toggleTheme?.();
        return;
    }

    if (target.closest('[data-close-window]')) {
        window.close();
        return;
    }

    const openLb = target.closest('[data-lightbox-open]');
    if (openLb && typeof window.openLightbox === 'function') {
        window.openLightbox(openLb.getAttribute('data-lightbox-open'));
        return;
    }

    if (target.closest('[data-lightbox-close]') && typeof window.closeLightbox === 'function') {
        window.closeLightbox(event);
        return;
    }

    const pwdToggle = target.closest('[data-toggle-password]');
    if (pwdToggle) {
        togglePasswordById(pwdToggle.getAttribute('data-toggle-password'), pwdToggle);
        return;
    }

    const pwdBtn = target.closest('[data-toggle-pwd]');
    if (pwdBtn) {
        togglePwdButton(pwdBtn);
    }
}

function checkPasswordMatch() {
    const pwd = document.getElementById('new_password');
    const confirm = document.getElementById('confirm_password');
    const indicator = document.getElementById('password-match-indicator');
    if (!pwd || !confirm || !indicator) {
        return;
    }
    if (!confirm.value) {
        indicator.classList.add('hidden');
        return;
    }
    indicator.classList.remove('hidden');
    if (pwd.value === confirm.value) {
        indicator.textContent = '✓ Les mots de passe sont identiques';
        indicator.className = 'mt-1 text-xs font-medium text-emerald-400';
    } else {
        indicator.textContent = '✗ Les mots de passe ne correspondent pas';
        indicator.className = 'mt-1 text-xs font-medium text-red-400';
    }
}

function onInput(event) {
    const target = event.target;
    if (!(target instanceof HTMLElement)) {
        return;
    }
    if (target.id === 'new_password' || target.id === 'confirm_password') {
        if (document.getElementById('password-match-indicator')) {
            checkPasswordMatch();
        }
    }
}

document.addEventListener('click', onClick);
document.addEventListener('input', onInput);
