import { Controller } from '@hotwired/stimulus';

/**
 * Autocomplétion pour un champ de recherche (suggestions JSON).
 *
 * Usage :
 *   <div data-controller="search-autocomplete"
 *        data-search-autocomplete-url-value="/ludotheque/suggest"
 *        data-search-autocomplete-min-length-value="2">
 *     <input data-search-autocomplete-target="input" data-action="input->search-autocomplete#onInput …">
 *     <ul data-search-autocomplete-target="list" hidden></ul>
 *   </div>
 */
export default class extends Controller {
    static targets = ['input', 'list'];
    static values = {
        url: String,
        minLength: { type: Number, default: 2 },
        debounce: { type: Number, default: 250 },
    };

    connect() {
        this.activeIndex = -1;
        this.abortController = null;
        this.debounceTimer = null;
        this.boundOutsideClick = this.onOutsideClick.bind(this);
        document.addEventListener('click', this.boundOutsideClick);
    }

    disconnect() {
        document.removeEventListener('click', this.boundOutsideClick);
        this.clearDebounce();
        this.abortFetch();
    }

    onInput() {
        this.clearDebounce();
        const term = this.inputTarget.value.trim();

        if (term.length < this.minLengthValue) {
            this.hideList();
            return;
        }

        this.debounceTimer = window.setTimeout(() => this.fetchSuggestions(term), this.debounceValue);
    }

    onKeydown(event) {
        if (this.listTarget.hidden) {
            return;
        }

        const items = this.optionElements();
        if (items.length === 0) {
            return;
        }

        if (event.key === 'ArrowDown') {
            event.preventDefault();
            this.setActiveIndex(Math.min(this.activeIndex + 1, items.length - 1));
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            this.setActiveIndex(Math.max(this.activeIndex - 1, 0));
        } else if (event.key === 'Enter' && this.activeIndex >= 0) {
            event.preventDefault();
            items[this.activeIndex].click();
        } else if (event.key === 'Escape') {
            event.preventDefault();
            this.hideList();
        }
    }

    select(event) {
        const button = event.target.closest('button[data-title]');
        if (!button || !this.listTarget.contains(button)) {
            return;
        }

        event.preventDefault();
        const title = button.dataset.title;
        if (!title) {
            return;
        }

        this.inputTarget.value = title;
        this.hideList();

        const form = this.inputTarget.form;
        if (form) {
            form.requestSubmit();
        }
    }

    async fetchSuggestions(term) {
        this.abortFetch();
        this.abortController = new AbortController();

        try {
            const url = new URL(this.urlValue, window.location.origin);
            url.searchParams.set('q', term);

            const response = await fetch(url.toString(), {
                headers: { Accept: 'application/json' },
                signal: this.abortController.signal,
            });

            if (!response.ok) {
                this.hideList();
                return;
            }

            const data = await response.json();
            this.renderSuggestions(Array.isArray(data.suggestions) ? data.suggestions : []);
        } catch (error) {
            if (error.name !== 'AbortError') {
                this.hideList();
            }
        }
    }

    renderSuggestions(suggestions) {
        this.listTarget.innerHTML = '';
        this.activeIndex = -1;

        if (suggestions.length === 0) {
            this.hideList();
            return;
        }

        for (const item of suggestions) {
            const li = document.createElement('li');
            li.setAttribute('role', 'option');

            const button = document.createElement('button');
            button.type = 'button';
            button.dataset.title = item.title;
            button.className = 'flex w-full flex-col items-start gap-0.5 px-3 py-2 text-left text-sm text-text-primary hover:bg-custom-orange/15 focus:bg-custom-orange/15 focus:outline-none';

            const title = document.createElement('span');
            title.className = 'font-semibold truncate w-full';
            title.textContent = item.title;
            button.appendChild(title);

            if (item.category) {
                const category = document.createElement('span');
                category.className = 'text-[10px] uppercase tracking-wider text-text-secondary truncate w-full';
                category.textContent = item.category;
                button.appendChild(category);
            }

            li.appendChild(button);
            this.listTarget.appendChild(li);
        }

        this.listTarget.hidden = false;
    }

    setActiveIndex(index) {
        this.activeIndex = index;
        const items = this.optionElements();
        items.forEach((el, i) => {
            el.classList.toggle('bg-custom-orange/15', i === index);
        });
        items[index]?.scrollIntoView({ block: 'nearest' });
    }

    optionElements() {
        return Array.from(this.listTarget.querySelectorAll('button[data-title]'));
    }

    hideList() {
        this.listTarget.hidden = true;
        this.listTarget.innerHTML = '';
        this.activeIndex = -1;
    }

    onOutsideClick(event) {
        if (!this.element.contains(event.target)) {
            this.hideList();
        }
    }

    clearDebounce() {
        if (this.debounceTimer !== null) {
            window.clearTimeout(this.debounceTimer);
            this.debounceTimer = null;
        }
    }

    abortFetch() {
        if (this.abortController) {
            this.abortController.abort();
            this.abortController = null;
        }
    }
}
