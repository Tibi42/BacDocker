# Archivage en masse ludothèque — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Permettre à un admin de cocher plusieurs jeux disponibles sur `/admin/ludotheque` et de les archiver en une seule action POST.

**Architecture:** Formulaire HTML classique enveloppe la liste (ou une barre d’actions liée aux checkboxes). Nouvelle route `POST /admin/ludotheque/archiver-selection` qui archive uniquement les jeux `available` non déjà archivés. JS minimal inline pour le compteur, le bouton d’action et « tout sélectionner ».

**Tech Stack:** Symfony 7 (Attribute Routing, CSRF), Twig, Doctrine ORM, Tailwind (classes existantes du template admin), PHPUnit unit tests.

**Spec:** `docs/superpowers/specs/2026-07-19-ludotheque-bulk-archive-design.md`

## Global Constraints

- Soft-delete uniquement (`setArchived(true)`), jamais de `remove()`.
- Cases cochables uniquement pour les jeux `status === available`.
- CSRF token id : `archive_bulk`.
- Route name : `app_admin_ludotheque_archive_bulk`, path `/admin/ludotheque/archiver-selection`, déclarée **avant** les routes `/{id}/…`.
- Pas de Stimulus, pas d’AJAX, pas de sélection multi-pages.
- Commits uniquement si l’utilisateur le demande explicitement (ne pas committer automatiquement).

---

## File Structure

| File | Responsibility |
|------|----------------|
| `src/Repository/BoardGameRepository.php` | `findForBulkArchive(array $ids): BoardGame[]` — charge les jeux éligibles |
| `tests/Unit/BoardGameRepositoryTest.php` | Tests unitaires de la nouvelle méthode repo |
| `src/Controller/Admin/LudothequeController.php` | Action `archiveBulk` |
| `templates/admin/ludotheque/index.html.twig` | Checkboxes, barre d’action, JS |

---

### Task 1: Repository — jeux éligibles à l’archivage bulk

**Files:**
- Modify: `src/Repository/BoardGameRepository.php`
- Modify: `tests/Unit/BoardGameRepositoryTest.php`

**Interfaces:**
- Produces: `BoardGameRepository::findForBulkArchive(array $ids): array` — retourne les `BoardGame` avec `id IN (:ids)`, `archived = false`, `status = available`. Si `$ids` est vide, retourne `[]` sans requête.

- [ ] **Step 1: Write the failing test**

Ajouter dans `tests/Unit/BoardGameRepositoryTest.php` :

```php
public function testFindForBulkArchiveReturnsEmptyForEmptyIds(): void
{
    $registry = $this->createMock(ManagerRegistry::class);
    $qb = $this->createMock(QueryBuilder::class);
    $qb->expects($this->never())->method('andWhere');

    $repo = $this->makeRepoWithQueryBuilder($registry, $qb);

    $this->assertSame([], $repo->findForBulkArchive([]));
}

public function testFindForBulkArchiveFiltersAvailableNonArchived(): void
{
    $registry = $this->createMock(ManagerRegistry::class);

    $query = $this->createMock(Query::class);
    $query->expects($this->once())->method('getResult')->willReturn([]);

    $qb = $this->createMock(QueryBuilder::class);
    $qb->expects($this->exactly(3))->method('andWhere')->willReturnSelf();
    $qb->expects($this->exactly(2))->method('setParameter')->willReturnSelf();
    $qb->expects($this->once())->method('getQuery')->willReturn($query);

    $repo = $this->makeRepoWithQueryBuilder($registry, $qb);

    $this->assertSame([], $repo->findForBulkArchive([1, 2, 3]));
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php bin/phpunit tests/Unit/BoardGameRepositoryTest.php --filter FindForBulkArchive`

Expected: FAIL (method `findForBulkArchive` undefined)

- [ ] **Step 3: Implement `findForBulkArchive`**

Dans `src/Repository/BoardGameRepository.php`, ajouter :

```php
/**
 * @param list<int> $ids
 * @return list<BoardGame>
 */
public function findForBulkArchive(array $ids): array
{
    if ($ids === []) {
        return [];
    }

    /** @var list<BoardGame> $games */
    $games = $this->createQueryBuilder('b')
        ->andWhere('b.id IN (:ids)')
        ->andWhere('b.archived = false')
        ->andWhere('b.status = :status')
        ->setParameter('ids', $ids)
        ->setParameter('status', BoardGame::STATUS_AVAILABLE)
        ->getQuery()
        ->getResult();

    return $games;
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `php bin/phpunit tests/Unit/BoardGameRepositoryTest.php --filter FindForBulkArchive`

Expected: PASS (2 tests)

---

### Task 2: Controller — action `archiveBulk`

**Files:**
- Modify: `src/Controller/Admin/LudothequeController.php`

**Interfaces:**
- Consumes: `BoardGameRepository::findForBulkArchive(array $ids): array`
- Produces: route `app_admin_ludotheque_archive_bulk` → `Response` redirect

- [ ] **Step 1: Add the `archiveBulk` action**

Insérer la méthode **après** `importCsv` et **avant** `edit` (donc avant toute route `/{id}/…`) :

```php
/**
 * Archive plusieurs jeux disponibles en une seule action.
 */
#[Route('/archiver-selection', name: 'archive_bulk', methods: ['POST'])]
public function archiveBulk(Request $request): Response
{
    $redirectParams = $this->redirectParams($request);

    if (!$this->isCsrfTokenValid('archive_bulk', (string) $request->request->get('_token'))) {
        $this->addFlash('error', 'Jeton de sécurité invalide.');

        return $this->redirectToRoute('app_admin_ludotheque_index', $redirectParams, Response::HTTP_SEE_OTHER);
    }

    $rawIds = $request->request->all('ids');
    if (!\is_array($rawIds)) {
        $rawIds = [];
    }

    $ids = [];
    foreach ($rawIds as $rawId) {
        $id = filter_var($rawId, \FILTER_VALIDATE_INT);
        if ($id !== false && $id > 0) {
            $ids[] = $id;
        }
    }
    $ids = array_values(array_unique($ids));

    if ($ids === []) {
        $this->addFlash('error', 'Aucun jeu sélectionné.');

        return $this->redirectToRoute('app_admin_ludotheque_index', $redirectParams, Response::HTTP_SEE_OTHER);
    }

    $games = $this->boardGameRepository->findForBulkArchive($ids);
    foreach ($games as $boardGame) {
        $boardGame->setArchived(true);
    }

    if ($games !== []) {
        $this->entityManager->flush();
    }

    $archivedCount = \count($games);
    if ($archivedCount > 0) {
        $this->addFlash(
            'success',
            sprintf('%d jeu%s archivé%s.', $archivedCount, $archivedCount > 1 ? 'x' : '', $archivedCount > 1 ? 's' : '')
        );
    } else {
        $this->addFlash('error', 'Aucun jeu disponible à archiver dans la sélection.');
    }

    return $this->redirectToRoute('app_admin_ludotheque_index', $redirectParams, Response::HTTP_SEE_OTHER);
}
```

Note : `redirectParams()` lit `page` / `q` depuis `$request->query`. Pour conserver les filtres après POST, le formulaire devra aussi envoyer `page` et `q` en champs hidden **ou** les passer en query string sur l’`action` du form. Choisir l’`action` avec query string :

```twig
action="{{ path('app_admin_ludotheque_archive_bulk', (pagination.currentPageNumber > 1 ? { page: pagination.currentPageNumber } : {})|merge(search ? { q: search } : {})) }}"
```

Ainsi `redirectParams($request)` fonctionne sans changement (query sur l’URL POST).

- [ ] **Step 2: Smoke-check route registration**

Run: `php bin/console debug:router app_admin_ludotheque_archive_bulk`

Expected: `POST /admin/ludotheque/archiver-selection`

---

### Task 3: Template — checkboxes, barre d’action, JS

**Files:**
- Modify: `templates/admin/ludotheque/index.html.twig`

**Interfaces:**
- Consumes: route `app_admin_ludotheque_archive_bulk`, CSRF `archive_bulk`
- Produces: UI sélection multiple (desktop + mobile)

- [ ] **Step 1: Wrap list + add bulk action bar**

Après le formulaire de recherche (vers la ligne 80), et **uniquement** si `pagination.totalItemCount > 0`, ouvrir un `<form id="ludo-bulk-archive-form" method="post" …>` qui enveloppe :
1. la barre d’actions bulk ;
2. la vue mobile ;
3. la vue desktop.

Structure de la barre :

```twig
{% set bulkQuery = {} %}
{% if pagination.currentPageNumber > 1 %}{% set bulkQuery = bulkQuery|merge({ page: pagination.currentPageNumber }) %}{% endif %}
{% if search %}{% set bulkQuery = bulkQuery|merge({ q: search }) %}{% endif %}

<form id="ludo-bulk-archive-form"
	  method="post"
	  action="{{ path('app_admin_ludotheque_archive_bulk', bulkQuery) }}"
	  class="space-y-4"
	  data-bulk-archive
	  onsubmit="return window.ludoBulkArchiveConfirm(this)">
	<input type="hidden" name="_token" value="{{ csrf_token('archive_bulk') }}">

	<div id="ludo-bulk-bar" class="hidden flex flex-wrap items-center justify-between gap-3 rounded-xl border border-custom bg-custom-secondary p-4 glass-card" data-bulk-bar>
		<p class="text-xs text-text-secondary">
			<span data-bulk-count>0</span> jeu(x) sélectionné(s)
		</p>
		<button type="submit"
				class="inline-flex items-center gap-2 rounded-lg border border-red-400/40 bg-red-500/10 py-2.5 px-4 text-[10px] font-extrabold uppercase tracking-widest text-red-400 hover:bg-red-500/20 transition-all disabled:opacity-40 disabled:pointer-events-none"
				data-bulk-submit
				disabled>
			Archiver la sélection (<span data-bulk-count>0</span>)
		</button>
	</div>

	{# … mobile cards + desktop table (existants) … #}
</form>
```

Important : les formulaires POST imbriqués (valider / rejeter / retourner / archiver unitaire / noter) **cassent le HTML**. Ne pas envelopper toute la liste dans un seul form si des `<form>` enfants existent déjà.

**Approche correcte (sans nesting) :**
- Les checkboxes sont hors de tout form enfant, avec `form="ludo-bulk-archive-form"` (attribut HTML `form`).
- Le formulaire bulk est un élément **séparé** (barre d’actions) avec `id="ludo-bulk-archive-form"`.
- Les checkboxes déclarent `form="ludo-bulk-archive-form"` pour être associées au formulaire distant.

- [ ] **Step 2: Add checkboxes — desktop**

Dans `<thead>`, première colonne :

```twig
<th class="px-4 py-3 w-10">
	<input type="checkbox"
		   id="ludo-select-all-desktop"
		   class="rounded border-custom bg-custom-tertiary text-custom-orange focus:ring-custom-orange"
		   data-bulk-select-all
		   aria-label="Tout sélectionner">
</th>
```

Dans chaque `<tr>`, première cellule :

```twig
<td class="px-4 py-3">
	<input type="checkbox"
		   name="ids[]"
		   value="{{ boardGame.id }}"
		   form="ludo-bulk-archive-form"
		   class="rounded border-custom bg-custom-tertiary text-custom-orange focus:ring-custom-orange"
		   data-bulk-item
		   {% if boardGame.status != 'available' %}disabled{% endif %}
		   aria-label="Sélectionner {{ boardGame.title }}">
</td>
```

- [ ] **Step 3: Add checkboxes — mobile**

En haut de chaque carte mobile, avant le titre :

```twig
<input type="checkbox"
	   name="ids[]"
	   value="{{ boardGame.id }}"
	   form="ludo-bulk-archive-form"
	   class="mt-1 rounded border-custom bg-custom-tertiary text-custom-orange focus:ring-custom-orange shrink-0"
	   data-bulk-item
	   {% if boardGame.status != 'available' %}disabled{% endif %}
	   aria-label="Sélectionner {{ boardGame.title }}">
```

Ajouter aussi une case « tout sélectionner » mobile au-dessus de la liste de cartes (même `data-bulk-select-all`).

- [ ] **Step 4: Add bulk form bar (standalone)**

Juste après le formulaire de recherche :

```twig
{% if pagination.totalItemCount > 0 %}
	{% set bulkQuery = {} %}
	{% if pagination.currentPageNumber > 1 %}{% set bulkQuery = bulkQuery|merge({ page: pagination.currentPageNumber }) %}{% endif %}
	{% if search %}{% set bulkQuery = bulkQuery|merge({ q: search }) %}{% endif %}

	<form id="ludo-bulk-archive-form"
		  method="post"
		  action="{{ path('app_admin_ludotheque_archive_bulk', bulkQuery) }}"
		  class="mb-4"
		  onsubmit="return window.ludoBulkArchiveConfirm(event)">
		<input type="hidden" name="_token" value="{{ csrf_token('archive_bulk') }}">
		<div id="ludo-bulk-bar" hidden class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-custom bg-custom-secondary p-4 glass-card">
			<p class="text-xs text-text-secondary"><span id="ludo-bulk-count">0</span> jeu(x) sélectionné(s)</p>
			<button type="submit" id="ludo-bulk-submit" disabled
					class="inline-flex items-center gap-2 rounded-lg border border-red-400/40 bg-red-500/10 py-2.5 px-4 text-[10px] font-extrabold uppercase tracking-widest text-red-400 hover:bg-red-500/20 transition-all disabled:opacity-40 disabled:pointer-events-none">
				Archiver la sélection (<span id="ludo-bulk-count-btn">0</span>)
			</button>
		</div>
	</form>
{% endif %}
```

- [ ] **Step 5: Add inline JS**

Avant `{% endblock %}` (dans `admin_body`) :

```twig
{% if pagination.totalItemCount > 0 %}
<script>
(function () {
	var items = Array.prototype.slice.call(document.querySelectorAll('[data-bulk-item]:not(:disabled)'));
	var selectAlls = document.querySelectorAll('[data-bulk-select-all]');
	var bar = document.getElementById('ludo-bulk-bar');
	var countEl = document.getElementById('ludo-bulk-count');
	var countBtn = document.getElementById('ludo-bulk-count-btn');
	var submitBtn = document.getElementById('ludo-bulk-submit');

	function selected() {
		return items.filter(function (el) { return el.checked; });
	}

	function refresh() {
		var n = selected().length;
		var total = items.length;
		if (countEl) countEl.textContent = String(n);
		if (countBtn) countBtn.textContent = String(n);
		if (submitBtn) submitBtn.disabled = n === 0;
		if (bar) bar.hidden = n === 0;
		selectAlls.forEach(function (el) {
			el.checked = total > 0 && n === total;
			el.indeterminate = n > 0 && n < total;
		});
	}

	items.forEach(function (el) {
		el.addEventListener('change', refresh);
	});
	selectAlls.forEach(function (el) {
		el.addEventListener('change', function () {
			items.forEach(function (item) { item.checked = el.checked; });
			refresh();
		});
	});

	window.ludoBulkArchiveConfirm = function () {
		var n = selected().length;
		if (n === 0) return false;
		return confirm(n > 1 ? ('Archiver ' + n + ' jeux ?') : 'Archiver 1 jeu ?');
	};

	refresh();
})();
</script>
{% endif %}
```

- [ ] **Step 6: Manual verification checklist**

Sur `/admin/ludotheque` (connecté en admin) :
1. 0 coché → barre cachée / bouton disabled.
2. 2 jeux disponibles cochés → confirm → flash succès, jeux absents de la liste.
3. « Tout sélectionner » ignore pending/loaned.
4. CSRF : modifier le token → flash erreur.
5. Mobile : cases + barre OK.

---

## Self-review (plan vs spec)

| Spec requirement | Task |
|------------------|------|
| Checkboxes desktop + mobile | Task 3 |
| Select-all available only | Task 3 JS + disabled attrs |
| Disabled for pending/loaned | Task 3 |
| Bulk bar with count + confirm | Task 3 |
| Individual archive kept | Task 3 (no removal) |
| Route + CSRF `archive_bulk` | Task 2 |
| Route before `/{id}` | Task 2 placement |
| Soft-archive available only | Task 1 + 2 |
| Flash success/error | Task 2 |
| Redirect keep q/page | Task 2 + 3 action query |
| No nested forms | Task 3 (`form=` attribute) |

Placeholder scan: none remaining after clarifying the nested-form approach.

---

## Execution Handoff

Plan saved to `docs/superpowers/plans/2026-07-19-ludotheque-bulk-archive.md`.
