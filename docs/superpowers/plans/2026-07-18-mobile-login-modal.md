# Mobile Login Modal Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** On mobile viewports shorter than 900px, clicking "NOUS REJOINDRE" opens a login modal instead of expanding the inline accordion form that overflows the screen.

**Architecture:** Add a new `#login-modal` block (structurally identical to the existing `#register-modal`) to the two templates that render the mobile drawer (`templates/home/index.html.twig`, `templates/layouts/page.html.twig`). Extend `assets/join_panel.js` with `openLoginModal`/`closeLoginModal` functions and a viewport-threshold check that both the mobile join button's click handler and the `?open=login` redirect flow consult.

**Tech Stack:** Symfony 8 / Twig templates, vanilla JS (Symfony AssetMapper, no build step, no JS test runner in this repo — verification is manual/browser-driven), Tailwind CSS.

## Global Constraints

- Threshold condition, evaluated live (not cached) at the moment of use: `window.innerWidth < 1024 && window.innerHeight < 900`.
- No change to desktop behavior (`#join-btn` / `#join-panel`) or to mobile behavior at height ≥ 900px (`#mobile-join-form` accordion stays as-is).
- `#login-modal` markup/behavior mirrors `#register-modal` (same overlay/card classes, same close-on-overlay/close-button/Escape pattern, same `z-[9999]`).
- `#login-modal` must appear only in `templates/home/index.html.twig` and `templates/layouts/page.html.twig` — the only two templates including `partials/_navbar.html.twig` and `partials/_mobile_menu.html.twig`.
- Do not auto-close the mobile drawer when the modal opens (matches current `S'INSCRIRE` → `#register-modal` behavior).
- No automated test suite covers JS/Twig in this repo (`php bin/phpunit` only covers `src/`); verification is manual via browser/DevTools viewport resizing, per the "Tests / vérification" section of the design spec at `docs/superpowers/specs/2026-07-18-mobile-login-modal-design.md`.

---

### Task 1: Add `#login-modal` markup to `templates/home/index.html.twig`

**Files:**
- Modify: `templates/home/index.html.twig` (insert immediately before the existing `{# ── Modale inscription (hors de tout stacking context) ── #}` block, currently starting around line 674)

**Interfaces:**
- Produces: DOM node `#login-modal` with children `#login-overlay`, `#login-close`, a `<form>` posting to `app_login` containing inputs `#login-username` (`name="_username"`), `#login-password` (`name="_password"`, with a `data-toggle-password="login-password"` show/hide button), a submit button, an `S'INSCRIRE` button carrying both `data-open-register` and `data-close-login` attributes, and a "Mot de passe oublié ?" link. Task 3 (JS) queries these exact ids/attributes.

- [ ] **Step 1: Insert the `#login-modal` block**

Insert this block in `templates/home/index.html.twig` directly above the line `{# ── Modale inscription (hors de tout stacking context) ── #}`:

```twig
{# ── Modale connexion mobile (écrans courts, hors de tout stacking context) ── #}
{% if not app.user %}
	<div id="login-modal" class="fixed inset-0 z-[9999] hidden items-center justify-center">
		<div id="login-overlay" class="absolute inset-0 bg-black/80 backdrop-blur-sm"></div>
		<div class="relative z-10 w-full max-w-md mx-4 rounded-2xl border border-custom bg-custom-secondary shadow-2xl overflow-hidden">
			<div class="bg-custom-tertiary px-6 py-4 border-b border-custom flex items-center justify-between">
				<div>
					<span class="text-[9px] font-bold uppercase tracking-widest text-custom-orange">Membre</span>
					<h2 class="text-lg font-bold text-text-primary uppercase tracking-wide">Se connecter</h2>
				</div>
				<button id="login-close" type="button" class="text-text-secondary hover:text-text-primary transition-colors p-1" aria-label="Fermer">
					<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
				</button>
			</div>
			<div class="px-6 py-5">
				{% if login_error is defined and login_error %}<p class="mb-3 text-xs font-semibold text-red-400 text-center">{{ login_error.messageKey|trans(login_error.messageData, 'security') }}</p>{% endif %}
				<form action="{{ path('app_login') }}" method="post" data-turbo="false" class="flex flex-col gap-4">
					<input type="hidden" name="_csrf_token" value="{{ csrf_token('authenticate') }}">
					<div>
						<label for="login-username" class="block text-[10px] font-bold uppercase tracking-wider text-text-secondary mb-1.5">Email</label>
						<input type="email" id="login-username" name="_username" value="{{ last_username|default('') }}" required autocomplete="email" class="w-full rounded-lg border border-custom bg-custom-tertiary px-4 py-2.5 text-sm text-text-primary placeholder-text-secondary/60 focus:border-custom-orange focus:outline-none focus:ring-1 focus:ring-custom-orange {{ (login_error is defined and login_error) ? 'border-red-500' : '' }}" placeholder="votre@email.com">
					</div>
					<div>
						<label for="login-password" class="block text-[10px] font-bold uppercase tracking-wider text-text-secondary mb-1.5">Mot de passe</label>
						<div class="relative">
							<input type="password" id="login-password" name="_password" required autocomplete="current-password" class="w-full rounded-lg border border-custom bg-custom-tertiary px-4 py-2.5 pr-10 text-sm text-text-primary placeholder-text-secondary/60 focus:border-custom-orange focus:outline-none focus:ring-1 focus:ring-custom-orange" placeholder="········">
							<button type="button" data-toggle-password="login-password" class="absolute right-3 top-1/2 -translate-y-1/2 text-text-secondary hover:text-custom-orange hover:scale-110 transition-all cursor-pointer" tabindex="-1" aria-label="Afficher le mot de passe">
								<svg class="h-4 w-4 eye-closed" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L6.5 6.5m3.378 3.378L6.5 6.5m0 0L3 3m3.5 3.5l10 10M17.5 17.5L21 21m-3.5-3.5l-10-10m13.043 4.51A9.953 9.953 0 0121 12c-1.275 4.057-5.065 7-9.543 7"/></svg>
								<svg class="h-4 w-4 eye-open hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
							</button>
						</div>
					</div>
					<button type="submit" class="w-full rounded-lg bg-custom-orange px-6 py-3 text-[11px] font-extrabold uppercase tracking-[0.05em] text-gray-900 cursor-pointer hover:bg-orange-600 hover:scale-105 hover:shadow-xl hover:shadow-custom-orange/40 transition-all shadow-lg shadow-custom-orange/20">SE CONNECTER</button>
					<button type="button" data-open-register data-close-login class="w-full rounded-lg border border-custom px-6 py-3 text-[11px] font-extrabold uppercase tracking-[0.05em] text-text-primary cursor-pointer hover:border-custom-orange hover:text-custom-orange transition-all">S'INSCRIRE</button>
					<a href="{{ path('app_forgot_password_request') }}" class="text-xs text-text-secondary hover:text-custom-orange transition-colors text-center">Mot de passe oublié ?</a>
				</form>
			</div>
		</div>
	</div>
{% endif %}
```

- [ ] **Step 2: Verify the template still renders**

Run: `php bin/console lint:twig templates/home/index.html.twig`
Expected: `[OK] All 1 Twig files contain valid syntax.`

- [ ] **Step 3: Commit**

```bash
git add templates/home/index.html.twig
git commit -m "feat: add login modal markup to home template"
```

---

### Task 2: Add `#login-modal` markup to `templates/layouts/page.html.twig`

**Files:**
- Modify: `templates/layouts/page.html.twig` (insert immediately before the existing `{# ── Modale inscription (hors de tout stacking context) ── #}` block, currently starting around line 160)

**Interfaces:**
- Produces: same `#login-modal` DOM structure as Task 1, byte-for-byte identical block, so both templates behave identically. Consumes nothing from Task 1 (templates are independent, not shared partials — this mirrors how `#register-modal` is already duplicated).

- [ ] **Step 1: Insert the identical `#login-modal` block**

Insert the exact same block used in Task 1, Step 1, in `templates/layouts/page.html.twig` directly above its own `{# ── Modale inscription (hors de tout stacking context) ── #}` line:

```twig
{# ── Modale connexion mobile (écrans courts, hors de tout stacking context) ── #}
{% if not app.user %}
	<div id="login-modal" class="fixed inset-0 z-[9999] hidden items-center justify-center">
		<div id="login-overlay" class="absolute inset-0 bg-black/80 backdrop-blur-sm"></div>
		<div class="relative z-10 w-full max-w-md mx-4 rounded-2xl border border-custom bg-custom-secondary shadow-2xl overflow-hidden">
			<div class="bg-custom-tertiary px-6 py-4 border-b border-custom flex items-center justify-between">
				<div>
					<span class="text-[9px] font-bold uppercase tracking-widest text-custom-orange">Membre</span>
					<h2 class="text-lg font-bold text-text-primary uppercase tracking-wide">Se connecter</h2>
				</div>
				<button id="login-close" type="button" class="text-text-secondary hover:text-text-primary transition-colors p-1" aria-label="Fermer">
					<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
				</button>
			</div>
			<div class="px-6 py-5">
				{% if login_error is defined and login_error %}<p class="mb-3 text-xs font-semibold text-red-400 text-center">{{ login_error.messageKey|trans(login_error.messageData, 'security') }}</p>{% endif %}
				<form action="{{ path('app_login') }}" method="post" data-turbo="false" class="flex flex-col gap-4">
					<input type="hidden" name="_csrf_token" value="{{ csrf_token('authenticate') }}">
					<div>
						<label for="login-username" class="block text-[10px] font-bold uppercase tracking-wider text-text-secondary mb-1.5">Email</label>
						<input type="email" id="login-username" name="_username" value="{{ last_username|default('') }}" required autocomplete="email" class="w-full rounded-lg border border-custom bg-custom-tertiary px-4 py-2.5 text-sm text-text-primary placeholder-text-secondary/60 focus:border-custom-orange focus:outline-none focus:ring-1 focus:ring-custom-orange {{ (login_error is defined and login_error) ? 'border-red-500' : '' }}" placeholder="votre@email.com">
					</div>
					<div>
						<label for="login-password" class="block text-[10px] font-bold uppercase tracking-wider text-text-secondary mb-1.5">Mot de passe</label>
						<div class="relative">
							<input type="password" id="login-password" name="_password" required autocomplete="current-password" class="w-full rounded-lg border border-custom bg-custom-tertiary px-4 py-2.5 pr-10 text-sm text-text-primary placeholder-text-secondary/60 focus:border-custom-orange focus:outline-none focus:ring-1 focus:ring-custom-orange" placeholder="········">
							<button type="button" data-toggle-password="login-password" class="absolute right-3 top-1/2 -translate-y-1/2 text-text-secondary hover:text-custom-orange hover:scale-110 transition-all cursor-pointer" tabindex="-1" aria-label="Afficher le mot de passe">
								<svg class="h-4 w-4 eye-closed" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L6.5 6.5m3.378 3.378L6.5 6.5m0 0L3 3m3.5 3.5l10 10M17.5 17.5L21 21m-3.5-3.5l-10-10m13.043 4.51A9.953 9.953 0 0121 12c-1.275 4.057-5.065 7-9.543 7"/></svg>
								<svg class="h-4 w-4 eye-open hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
							</button>
						</div>
					</div>
					<button type="submit" class="w-full rounded-lg bg-custom-orange px-6 py-3 text-[11px] font-extrabold uppercase tracking-[0.05em] text-gray-900 cursor-pointer hover:bg-orange-600 hover:scale-105 hover:shadow-xl hover:shadow-custom-orange/40 transition-all shadow-lg shadow-custom-orange/20">SE CONNECTER</button>
					<button type="button" data-open-register data-close-login class="w-full rounded-lg border border-custom px-6 py-3 text-[11px] font-extrabold uppercase tracking-[0.05em] text-text-primary cursor-pointer hover:border-custom-orange hover:text-custom-orange transition-all">S'INSCRIRE</button>
					<a href="{{ path('app_forgot_password_request') }}" class="text-xs text-text-secondary hover:text-custom-orange transition-colors text-center">Mot de passe oublié ?</a>
				</form>
			</div>
		</div>
	</div>
{% endif %}
```

- [ ] **Step 2: Verify the template still renders**

Run: `php bin/console lint:twig templates/layouts/page.html.twig`
Expected: `[OK] All 1 Twig files contain valid syntax.`

- [ ] **Step 3: Commit**

```bash
git add templates/layouts/page.html.twig
git commit -m "feat: add login modal markup to shared page layout"
```

---

### Task 3: Add login modal open/close logic and viewport-threshold wiring to `assets/join_panel.js`

**Files:**
- Modify: `assets/join_panel.js`

**Interfaces:**
- Consumes: DOM nodes produced by Tasks 1–2: `#login-modal`, `#login-overlay`, `#login-close`, `#login-modal input:not([type="hidden"])`, `[data-open-register]` (existing, handled by `initRegisterModal()`), `[data-close-login]` (new, added in Tasks 1–2).
- Produces: top-level functions `isShortMobileViewport(): boolean`, `openLoginModal(): void`, `closeLoginModal(): void`, and `initLoginModal(): void`. `initLoginModal` is called once from `bootJoinPanel()`, alongside the existing `initRegisterModal()` call.

- [ ] **Step 1: Add the threshold helper and the login modal open/close functions**

In `assets/join_panel.js`, add this block right after the top comment (before `let escapeHandlerInstalled = false;`):

```js
function isShortMobileViewport() {
    return window.innerWidth < 1024 && window.innerHeight < 900;
}

function openLoginModal() {
    const modal = document.getElementById('login-modal');
    if (!modal) return;
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    setTimeout(() => {
        const input = modal.querySelector('input:not([type="hidden"])');
        if (input) input.focus();
    }, 100);
}

function closeLoginModal() {
    const modal = document.getElementById('login-modal');
    if (!modal) return;
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}
```

- [ ] **Step 2: Add `initLoginModal()`**

Add this function definition right after `initRegisterModal()`'s closing brace (the function currently ends around the line `if (new URLSearchParams(window.location.search).get('open') === 'register') { openModal(); } }`):

```js
function initLoginModal() {
    const modal = document.getElementById('login-modal');
    if (!modal) return;

    const closeBtn = document.getElementById('login-close');
    const overlay = document.getElementById('login-overlay');

    if (closeBtn) closeBtn.addEventListener('click', closeLoginModal);
    if (overlay) overlay.addEventListener('click', closeLoginModal);

    modal.querySelectorAll('[data-close-login]').forEach((btn) => {
        btn.addEventListener('click', closeLoginModal);
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
            closeLoginModal();
        }
    });
}
```

- [ ] **Step 3: Call `initLoginModal()` from `bootJoinPanel()`**

Find this in `assets/join_panel.js`:

```js
function bootJoinPanel() {
    initJoinPanel();
    initRegisterModal();
```

Replace with:

```js
function bootJoinPanel() {
    initJoinPanel();
    initRegisterModal();
    initLoginModal();
```

- [ ] **Step 4: Wire the mobile join button click handler to the threshold check**

Find this in `assets/join_panel.js` (inside `initJoinPanel()`, mobile section):

```js
        mobileBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            if (mobileOpen) {
                closeMobile();
            } else {
                openMobile();
            }
        });
```

Replace with:

```js
        mobileBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            if (isShortMobileViewport()) {
                openLoginModal();
                return;
            }
            if (mobileOpen) {
                closeMobile();
            } else {
                openMobile();
            }
        });
```

- [ ] **Step 5: Wire the `?open=login` redirect flow to the threshold check**

Find this in `assets/join_panel.js` (inside `bootJoinPanel()`):

```js
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
```

Replace with:

```js
        // Idem sur mobile : ouvrir le tiroir de menu et le formulaire de connexion,
        // sinon la redirection ?open=login n'affiche rien de visible en dessous de lg.
        // Sur les écrans courts, la modale de connexion remplace l'accordéon (voir isShortMobileViewport).
        if (isShortMobileViewport()) {
            openLoginModal();
        } else {
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
}
```

- [ ] **Step 6: Commit**

```bash
git add assets/join_panel.js
git commit -m "feat: open login modal instead of accordion on short mobile viewports"
```

---

### Task 4: Manual verification across viewport sizes

**Files:**
- None (verification only). Requires the local Symfony/Laragon dev server running and reachable (e.g. `http://devboitechimere.test` or the project's configured local host).

**Interfaces:**
- Consumes: the full feature built in Tasks 1–3.

- [ ] **Step 1: Verify short mobile viewport opens the modal**

Using the Playwright MCP browser tools (`browser_navigate`, `browser_resize`, `browser_click`, `browser_snapshot`):
1. Navigate to the home page.
2. Resize to width 375, height 700 (< 1024 wide, < 900 tall).
3. Open the hamburger menu (`#mobile-menu-btn`), then click `NOUS REJOINDRE` (`#mobile-join-btn`).
4. Expected: `#login-modal` becomes visible (class `flex`, no longer `hidden`); `#mobile-join-form` stays collapsed (`max-height: 0`).

- [ ] **Step 2: Verify taller mobile viewport keeps the accordion**

1. Resize to width 375, height 950 (< 1024 wide, ≥ 900 tall).
2. Click `NOUS REJOINDRE` again (reload the page first so state resets).
3. Expected: `#mobile-join-form` expands (non-zero `max-height`); `#login-modal` stays hidden.

- [ ] **Step 3: Verify desktop viewport is unaffected**

1. Resize to width 1280, height 800 (≥ 1024 wide).
2. Click the desktop `NOUS REJOINDRE` button (`#join-btn`).
3. Expected: `#join-panel` expands as before; `#login-modal` stays hidden.

- [ ] **Step 4: Verify the S'INSCRIRE handoff from the login modal**

1. With the short mobile viewport (375×700) and `#login-modal` open (from Step 1), click the `S'INSCRIRE` button inside it.
2. Expected: `#login-modal` becomes hidden again; `#register-modal` becomes visible.

- [ ] **Step 5: Verify the `?open=login` flow on a short viewport**

1. With the short mobile viewport (375×700), navigate to `/?open=login`.
2. Expected: `#login-modal` is visible on load; `#mobile-menu-drawer` does not forcibly open.

- [ ] **Step 6: Record results**

No commit needed for this task — it's verification only. If any expectation fails, fix the corresponding code in Task 1–3 and re-run the failing step before proceeding.
