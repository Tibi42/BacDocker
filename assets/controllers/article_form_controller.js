import { Controller } from '@hotwired/stimulus';

const ACTIVE_TAB = 'px-3 py-1.5 rounded-md text-[9px] font-bold uppercase tracking-wider bg-custom-orange text-gray-900 shadow transition-all';
const INACTIVE_TAB = 'px-3 py-1.5 rounded-md text-[9px] font-bold uppercase tracking-wider text-text-secondary hover:text-text-primary transition-all';
const EMPTY_CONTENT = '<p class="italic text-text-secondary/50">Rédigez votre texte pour voir l\'aperçu...</p>';

/**
 * Éditeur Quill + aperçu live (carte / page article) pour le formulaire admin.
 * Script externe (AssetMapper) → compatible CSP nonce + navigation Turbo.
 */
export default class extends Controller {
    static targets = [
        'editor',
        'contentInput',
        'form',
        'titleInput',
        'tagInput',
        'imageInput',
        'galleryInput',
        'previewCardBtn',
        'previewDetailBtn',
        'cardSection',
        'detailSection',
        'cardTitle',
        'detailTitle',
        'cardTag',
        'detailTag',
        'cardImg',
        'detailImg',
        'cardImgPlaceholder',
        'detailImgContainer',
        'previewContent',
        'galleryContainer',
        'galleryGrid',
    ];

    connect() {
        this.newGalleryPreviews = [];
        this.boundSubmit = this.syncContentOnSubmit.bind(this);
        this.initPreview();
        this.loadQuill().then(() => this.initQuill()).catch(() => {
            console.error('Impossible de charger Quill.');
        });
    }

    disconnect() {
        if (this.hasFormTarget && this.boundSubmit) {
            this.formTarget.removeEventListener('submit', this.boundSubmit);
        }
        this.quill = null;
    }

    showCardPreview() {
        this.cardSectionTarget.classList.remove('hidden');
        this.detailSectionTarget.classList.add('hidden');
        this.previewCardBtnTarget.className = ACTIVE_TAB;
        this.previewDetailBtnTarget.className = INACTIVE_TAB;
    }

    showDetailPreview() {
        this.cardSectionTarget.classList.add('hidden');
        this.detailSectionTarget.classList.remove('hidden');
        this.previewCardBtnTarget.className = INACTIVE_TAB;
        this.previewDetailBtnTarget.className = ACTIVE_TAB;
    }

    updateTitle() {
        if (!this.hasTitleInputTarget) {
            return;
        }
        const val = this.titleInputTarget.value.trim() || "Titre de l'article";
        if (this.hasCardTitleTarget) {
            this.cardTitleTarget.textContent = val;
        }
        if (this.hasDetailTitleTarget) {
            this.detailTitleTarget.textContent = val;
        }
    }

    updateTag() {
        if (!this.hasTagInputTarget) {
            return;
        }
        const val = this.tagInputTarget.value.trim() || 'TAG';
        if (this.hasCardTagTarget) {
            this.cardTagTarget.textContent = val;
        }
        if (this.hasDetailTagTarget) {
            this.detailTagTarget.textContent = val;
        }
    }

    updateImagePreview() {
        if (!this.hasImageInputTarget || !this.imageInputTarget.files?.[0]) {
            return;
        }
        const reader = new FileReader();
        reader.onload = (e) => {
            const src = e.target.result;
            if (this.hasCardImgTarget) {
                this.cardImgTarget.src = src;
                this.cardImgTarget.classList.remove('hidden');
            }
            if (this.hasCardImgPlaceholderTarget) {
                this.cardImgPlaceholderTarget.classList.add('hidden');
            }
            if (this.hasDetailImgTarget) {
                this.detailImgTarget.src = src;
            }
            if (this.hasDetailImgContainerTarget) {
                this.detailImgContainerTarget.classList.remove('hidden');
            }
        };
        reader.readAsDataURL(this.imageInputTarget.files[0]);
    }

    updateGalleryFromInput() {
        this.newGalleryPreviews = [];
        if (!this.hasGalleryInputTarget || !this.galleryInputTarget.files?.length) {
            this.renderGalleryPreview();
            return;
        }

        const files = Array.from(this.galleryInputTarget.files);
        let loaded = 0;
        files.forEach((file) => {
            const reader = new FileReader();
            reader.onload = (e) => {
                this.newGalleryPreviews.push(e.target.result);
                loaded++;
                if (loaded === files.length) {
                    this.renderGalleryPreview();
                }
            };
            reader.readAsDataURL(file);
        });
    }

    toggleExistingGalleryImage(event) {
        const checkbox = event.currentTarget;
        const filename = checkbox.value;
        if (!this.hasGalleryGridTarget) {
            return;
        }
        const img = this.galleryGridTarget.querySelector(`img[data-existing-img="${CSS.escape(filename)}"]`);
        if (img) {
            img.classList.toggle('hidden', checkbox.checked);
        }
        this.renderGalleryPreview();
    }

    // ─── private ───────────────────────────────────────────────

    initPreview() {
        this.updateTitle();
        this.updateTag();
    }

    loadQuill() {
        if (typeof window.Quill !== 'undefined') {
            return Promise.resolve();
        }

        const existing = document.querySelector('script[data-article-quill]');
        if (existing) {
            return new Promise((resolve, reject) => {
                if (typeof window.Quill !== 'undefined') {
                    resolve();
                    return;
                }
                existing.addEventListener('load', () => resolve());
                existing.addEventListener('error', () => reject(new Error('Quill load failed')));
            });
        }

        return new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = '/vendor/quill/quill.js';
            script.dataset.articleQuill = '1';
            script.onload = () => resolve();
            script.onerror = () => reject(new Error('Quill load failed'));
            document.head.appendChild(script);
        });
    }

    initQuill() {
        if (!this.hasEditorTarget || this.editorTarget.classList.contains('ql-container')) {
            return;
        }
        if (typeof window.Quill === 'undefined') {
            return;
        }

        this.quill = new window.Quill(this.editorTarget, {
            theme: 'snow',
            modules: {
                toolbar: [
                    [{ header: [1, 2, 3, false] }],
                    ['bold', 'italic', 'underline', 'strike'],
                    [{ list: 'ordered' }, { list: 'bullet' }],
                    ['link', 'clean'],
                ],
            },
        });

        if (this.hasFormTarget) {
            this.formTarget.addEventListener('submit', this.boundSubmit);
        }

        this.quill.on('text-change', () => this.syncPreviewContent());
    }

    syncContentOnSubmit() {
        if (!this.quill || !this.hasContentInputTarget) {
            return;
        }
        let html = this.quill.root.innerHTML;
        if (html === '<p><br></p>' || html === '<p></p>') {
            html = '';
        }
        this.contentInputTarget.value = html;
    }

    syncPreviewContent() {
        if (!this.quill || !this.hasPreviewContentTarget) {
            return;
        }
        let html = this.quill.root.innerHTML;
        if (html === '<p><br></p>' || html === '<p></p>') {
            html = EMPTY_CONTENT;
        } else {
            html = this.sanitizePreviewHtml(html);
        }
        this.previewContentTarget.innerHTML = html;
    }

    renderGalleryPreview() {
        if (!this.hasGalleryGridTarget || !this.hasGalleryContainerTarget) {
            return;
        }

        this.galleryGridTarget.querySelectorAll('.new-preview-img').forEach((el) => el.remove());

        this.newGalleryPreviews.forEach((src) => {
            const img = document.createElement('img');
            img.src = src;
            img.className = 'h-16 w-full object-cover rounded border border-custom new-preview-img';
            this.galleryGridTarget.appendChild(img);
        });

        const visibleExisting = this.galleryGridTarget.querySelectorAll('img[data-existing-img]:not(.hidden)');
        if (visibleExisting.length > 0 || this.newGalleryPreviews.length > 0) {
            this.galleryContainerTarget.classList.remove('hidden');
        } else {
            this.galleryContainerTarget.classList.add('hidden');
        }
    }

    sanitizePreviewHtml(html) {
        const allowed = ['P', 'BR', 'STRONG', 'B', 'EM', 'I', 'U', 'S', 'UL', 'OL', 'LI', 'A', 'H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'BLOCKQUOTE', 'SPAN', 'DIV'];
        const template = document.createElement('template');
        template.innerHTML = html;

        const walk = (node) => {
            Array.from(node.childNodes).forEach((child) => {
                if (child.nodeType !== 1) {
                    return;
                }
                if (!allowed.includes(child.tagName)) {
                    while (child.firstChild) {
                        child.parentNode.insertBefore(child.firstChild, child);
                    }
                    child.parentNode.removeChild(child);
                    return;
                }
                Array.from(child.attributes).forEach((attr) => {
                    const name = attr.name.toLowerCase();
                    if (name.startsWith('on') || !['href', 'class', 'title', 'rel', 'target'].includes(name)) {
                        child.removeAttribute(attr.name);
                    }
                    if (name === 'href') {
                        const href = (attr.value || '').trim().toLowerCase();
                        if (href.startsWith('javascript:') || href.startsWith('data:') || href.startsWith('vbscript:')) {
                            child.removeAttribute('href');
                        }
                    }
                });
                if (child.tagName === 'A') {
                    child.setAttribute('rel', 'noopener noreferrer');
                }
                walk(child);
            });
        };

        walk(template.content);
        return template.innerHTML;
    }
}
