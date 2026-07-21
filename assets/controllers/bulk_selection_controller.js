import { Controller } from '@hotwired/stimulus';

/**
 * Barre d'actions bulk admin : cases à cocher + Valider / Modifier / Suspendre / Supprimer.
 *
 * Targets : bar, count, editLink, validateBtn, suspendBtn, deleteBtn, returnDueAt (optionnel)
 * Items : checkboxes [data-bulk-selection-target="item"] avec data-can-validate|suspend|delete et data-edit-url
 */
export default class extends Controller {
    static targets = ['bar', 'count', 'editLink', 'validateBtn', 'suspendBtn', 'deleteBtn', 'returnDueAt', 'validateWrap', 'item'];
    static values = {
        label: { type: String, default: 'élément(s)' },
        deleteConfirm: { type: String, default: 'Supprimer la sélection ?' },
        suspendConfirm: { type: String, default: 'Suspendre la sélection ?' },
        validateConfirm: { type: String, default: '' },
    };

    connect() {
        this.refresh();
    }

    toggle(event) {
        const el = event.currentTarget;
        this.syncValue(el.value, el.checked);
        this.refresh();
    }

    syncValue(value, checked) {
        this.itemTargets.forEach((el) => {
            if (el.value === value && !el.disabled) {
                el.checked = checked;
            }
        });
    }

    uniqueChecked() {
        const seen = {};
        const items = [];
        this.itemTargets.forEach((el) => {
            if (el.checked && !el.disabled && !seen[el.value]) {
                seen[el.value] = true;
                items.push(el);
            }
        });
        return items;
    }

    refresh() {
        const checked = this.uniqueChecked();
        const n = checked.length;

        if (this.hasCountTarget) {
            this.countTarget.textContent = String(n);
        }
        if (this.hasBarTarget) {
            this.barTarget.hidden = n === 0;
        }

        if (this.hasEditLinkTarget) {
            const showEdit = n === 1 && checked[0].dataset.editUrl;
            this.editLinkTarget.hidden = !showEdit;
            if (showEdit) {
                this.editLinkTarget.href = checked[0].dataset.editUrl;
            }
        }

        const any = (attr) => checked.some((el) => el.dataset[attr] === '1');

        if (this.hasValidateBtnTarget) {
            this.validateBtnTarget.disabled = !any('canValidate');
            this.validateBtnTarget.hidden = !any('canValidate');
        }
        if (this.hasValidateWrapTarget) {
            this.validateWrapTarget.hidden = !any('canValidate');
        }
        if (this.hasSuspendBtnTarget) {
            this.suspendBtnTarget.disabled = !any('canSuspend');
            this.suspendBtnTarget.hidden = !any('canSuspend');
        }
        if (this.hasDeleteBtnTarget) {
            this.deleteBtnTarget.disabled = !any('canDelete');
            this.deleteBtnTarget.hidden = !any('canDelete');
        }
    }

    injectIds(form) {
        form.querySelectorAll('input[data-bulk-injected]').forEach((el) => el.remove());
        this.uniqueChecked().forEach((item) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'ids[]';
            input.value = item.value;
            input.dataset.bulkInjected = '1';
            form.appendChild(input);
        });
    }

    confirmValidate(event) {
        if (this.validateConfirmValue) {
            const n = this.uniqueChecked().length;
            const msg = this.validateConfirmValue.replace('%n%', String(n));
            if (!confirm(msg)) {
                event.preventDefault();
                return;
            }
        }
        if (this.hasReturnDueAtTarget && !this.returnDueAtTarget.value) {
            event.preventDefault();
            alert('Veuillez indiquer une date de retour.');
            return;
        }
        this.injectIds(event.target);
    }

    confirmSuspend(event) {
        const n = this.uniqueChecked().length;
        const msg = this.suspendConfirmValue.replace('%n%', String(n));
        if (!confirm(msg)) {
            event.preventDefault();
            return;
        }
        this.injectIds(event.target);
    }

    confirmDelete(event) {
        const n = this.uniqueChecked().length;
        const msg = this.deleteConfirmValue.replace('%n%', String(n));
        if (!confirm(msg)) {
            event.preventDefault();
            return;
        }
        this.injectIds(event.target);
    }
}
