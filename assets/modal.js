// assets/modal.js
// Modale d'activité / inscription : overlay immédiat + spinner, puis contenu.

const MODAL_LOADING_HTML = `
<div class="flex flex-col items-center justify-center py-14 gap-4" data-modal-loading aria-live="polite" aria-busy="true">
    <span class="modal-spinner" aria-hidden="true"></span>
    <p class="text-[10px] font-extrabold uppercase tracking-widest text-text-secondary">Chargement…</p>
</div>
`;

function openModal() {
    const modal = document.getElementById('activity-modal');
    if (!modal) return;

    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    const modal = document.getElementById('activity-modal');
    const frame = document.getElementById('activity-modal-frame');
    if (!modal) return;

    modal.classList.add('hidden');
    document.body.style.overflow = '';

    if (frame) {
        frame.innerHTML = '';
    }
}

function setSubmitLoading(form, isLoading) {
    form.querySelectorAll('button[type="submit"]').forEach((btn) => {
        if (isLoading) {
            if (!btn.dataset.originalHtml) {
                btn.dataset.originalHtml = btn.innerHTML;
            }
            btn.disabled = true;
            btn.setAttribute('aria-busy', 'true');
            btn.innerHTML = `
                <span class="inline-flex items-center justify-center gap-2">
                    <span class="modal-spinner modal-spinner-sm" aria-hidden="true"></span>
                    Envoi…
                </span>
            `;
        } else if (btn.dataset.originalHtml) {
            btn.disabled = false;
            btn.removeAttribute('aria-busy');
            btn.innerHTML = btn.dataset.originalHtml;
        }
    });
}

// Clic → overlay + spinner immédiatement, puis fetch du formulaire
document.addEventListener('click', async (e) => {
    const link = e.target.closest('[data-modal-url]');
    if (!link) return;

    e.preventDefault();
    const url = link.getAttribute('data-modal-url');
    const frame = document.getElementById('activity-modal-frame');
    if (!frame || !url) return;

    frame.innerHTML = MODAL_LOADING_HTML;
    openModal();

    try {
        const response = await fetch(url, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        if (!response.ok || response.redirected) {
            window.location.href = url;
            return;
        }
        const html = await response.text();
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        const remoteFrame = doc.querySelector('turbo-frame#activity-modal-frame');
        frame.innerHTML = remoteFrame ? remoteFrame.innerHTML : html;
    } catch {
        window.location.href = url;
    }
});

// Feedback immédiat à la soumission d'un formulaire dans la modale
document.addEventListener('submit', (e) => {
    const form = e.target;
    if (!(form instanceof HTMLFormElement)) return;
    if (!form.closest('#activity-modal')) return;

    setSubmitLoading(form, true);
});

// Fermer au clic sur le backdrop
document.addEventListener('click', (e) => {
    const modal = document.getElementById('activity-modal');
    if (modal && e.target === modal) {
        closeModal();
    }
});

// Fermer via les boutons data-modal-close
document.addEventListener('click', (e) => {
    if (e.target.closest('[data-modal-close]')) {
        e.preventDefault();
        closeModal();
    }
});

// Fermer avec Escape
document.addEventListener('keydown', (e) => {
    const modal = document.getElementById('activity-modal');
    if (e.key === 'Escape' && modal && !modal.classList.contains('hidden')) {
        closeModal();
    }
});
