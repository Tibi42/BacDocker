---
title: 'Ludothèque: ratings, cancellation & archiving on top of the existing BoardGame workflow'
slug: 'ludotheque-catalog-loans-ratings'
created: '2026-07-19'
status: 'done'
baseline_commit: '5aeb98cb22e17a35d30f94d3a743e85494d8c225'
stepsCompleted: [1, 2, 3, 4]
tech_stack: ['PHP 8.4', 'Symfony 8.0', 'Doctrine ORM 3.6', 'MySQL', 'Twig', 'AssetMapper/Tailwind (symfonycasts/tailwind-bundle)', 'KnpPaginator', 'PHPUnit 12.5']
files_to_modify: ['src/Entity/BoardGame.php', 'src/Repository/BoardGameRepository.php', 'src/Controller/Admin/LudothequeController.php', 'src/Controller/UserDashboardController.php', 'templates/admin/ludotheque/index.html.twig', 'templates/user_dashboard/index.html.twig']
code_patterns: ['Activity/Inscription status-lifecycle + validated setter', 'per-action CSRF token id pattern', 'Turbo 422/303 convention', 'KnpPaginator QueryBuilder repository methods', 'PrePersist/PreUpdate lifecycle callbacks']
test_patterns: ['tests/Unit/{Entity}Test.php direct instantiation + expectException', 'tests/Unit/{Entity}RepositoryTest.php QueryBuilder-mock anonymous subclass']
---

# Tech-Spec: Ludothèque: ratings, cancellation & archiving on top of the existing BoardGame workflow

**Created:** 2026-07-19

## Overview

### Problem Statement

A prior session already designed and fully implemented a ludothèque (board game library) feature — `BoardGame` entity, `Admin\LudothequeController`, member-facing loan requests in `/mon-espace`, migration, tests — all present and tested in the working tree but **uncommitted**. That implementation covers the catalog + admin CRUD + member-initiated loan request/approve/reject/return workflow, capped at one active loan per member (`BoardGameRepository::findActiveForUser()`).

This session's requirements gathering (via party-mode design + pressure-testing) surfaced three gaps against that existing implementation: **members can't rate a game**, **members can't cancel their own pending request** (only admins can reject it), and **a game can't be retired from the catalog without a permanent hard delete**, which would orphan any rating history. It also surfaced that review eligibility ("did this member actually borrow the game") can't be checked against the existing design, because `BoardGame::return()` clears the `borrower` field — no historical record of past borrowers survives a return.

**Explicit boundary renegotiation**: `_bmad-output/implementation-artifacts/spec-ludotheque-management.md` is a **frozen** spec (`do not modify unless human renegotiates`) whose `Never` section explicitly bans a loan-history entity. Solving review eligibility requires exactly that — a persisted historical borrower record. This was surfaced to the user directly (not glossed over) and confirmed as an intentional renegotiation of that specific boundary; see the changelog entry appended to that file. No other boundary from the frozen spec is touched.

### Solution

**Extend, don't replace**, the existing `BoardGame` implementation — confirmed by the user after investigation showed it's a complete, tested implementation, not a stale plan. Add: a minimal `LoanLog` entity — a small, insert-only loan-history log, one row per approved loan, used solely to answer "has this user ever borrowed this game" — to unblock review eligibility without touching `BoardGame`'s core state machine; a `Review` entity (1-5 rating per member per game, gated on `LoanLog`); a member-initiated cancel action for their own `pending` request; and an `archived` flag on `BoardGame` replacing the current hard-delete, guarded the same way the existing delete already is (only allowed while `available`, and now also enforced on the member-facing request/cancel/rate actions — see Technical Decisions and Tasks 1a/10/11).

`category` and `condition` stay exactly as already implemented (freeform text / hardcoded 4-value `ChoiceType` matching the locked values Neuf/Bon état/Usé/Abîmé) — no enum conversion. That was considered and dropped: the existing fields already satisfy the requirement losslessly, and converting them would be unrequested rework against the "extend, minimal change" direction just confirmed.

### Scope

**In Scope:**
- `BoardGame`: add `archived` boolean column (default `false`)
- `BoardGameRepository::findActiveForUser()`-adjacent guard: `requestLoan()` (existing action) must refuse a request on an `archived` game — without this, archiving doesn't actually stop a game from being borrowed (see Task 1a)
- `LudothequeController`: replace hard-delete `delete()` action with `archive()` (same guard: only while `status === available`); insert a `LoanLog` row inside `approve()`
- New `LoanLog` entity + repository: `boardGame`, `user`, `loanedAt` — insert-only, never exposed in any UI, exists solely to back review eligibility
- New `Review` entity + repository: `boardGame`, `user`, `rating` (1-5), unique per (boardGame, user), upsertable (member can change their own rating); creation gated on `LoanLogRepository::hasBorrowed($game, $user)`
- `UserDashboardController`: new `cancelLoanRequest` action (member cancels their own `pending` request — guarded: `boardGame.status === pending` and `boardGame.borrower === current user`); new `rateGame` action (create-or-update the member's `Review`, gated on loan-log eligibility); catalog listing gets derived average rating per game and an "overdue" indicator (`status === loaned && returnDueAt < now`) visible to the member
- `templates/admin/ludotheque/index.html.twig`: same overdue indicator, visible to admin; archive button replaces delete button
- Migration via `doctrine:migrations:diff` (adds `board_game.archived`, creates `loan_log` and `review` tables)

**Out of Scope:**
- Email notifications
- Multi-copy / stock quantity tracking
- Reservation queue / waitlist
- i18n
- Admin UI to browse/reactivate archived games (archived games simply stop appearing in admin and member listings; data is preserved in DB, not exposed — noted as a known limitation, not a gap to fix now)
- DB-level (shadow-column) enforcement of "one active loan per member" — the existing `findActiveForUser()` application-level check-then-act is a pre-existing pattern already accepted in this codebase (mirrors `ActivityRegisterController`'s capacity check), left as-is; not a regression introduced by this spec

## Context for Development

### Codebase Patterns

- **`BoardGame` status lifecycle** (`src/Entity/BoardGame.php`) is already built: `STATUS_AVAILABLE`/`STATUS_PENDING`/`STATUS_LOANED` constants, `setStatus()` throws `\InvalidArgumentException` on invalid values (mirrors `Activity::setStatus()`). No changes needed to this method.
- **Admin approve/reject/return actions** (`LudothequeController.php:94-185`) already implement the per-action CSRF pattern (`isCsrfTokenValid('approve' . $id, $token)`), status guards, French flash messages, `HTTP_SEE_OTHER` redirects. `archive()` must follow the exact same shape as the current `delete()` (`LudothequeController.php:190-210`), just swapping `entityManager->remove($boardGame)` for `$boardGame->setArchived(true)` + flush, and the flash/CSRF token id from `'delete'` to `'archive'`.
- **Member loan request** (`UserDashboardController.php:343-371`, `requestLoan()`) is the direct precedent for `cancelLoanRequest()` and `rateGame()`: CSRF check first, then business guards, then mutate, then flash, then redirect. `unregister()` (`UserDashboardController.php:255-313`) is the closer precedent specifically for ownership verification before allowing a member-initiated mutation — its actual check is `$inscription->getParticipantEmail() !== $user->getEmail()` (there is no `getBorrower()` call in that method; the analogous check for `cancelLoanRequest()`/`rateGame()` is `$boardGame->getBorrower()?->getId() !== $this->getUser()->getId()`, newly written for these actions, not copied from `unregister()`).
- **Repository shape**: `BoardGameRepository::findAllOrderByTitleQb()`/`findActiveForUser()` (`src/Repository/BoardGameRepository.php`) — `LoanLogRepository` and `ReviewRepository` follow the same `ServiceEntityRepository` + explicit query-method shape, no generic CRUD abstraction.
- **Entities are never `final`**, use `\DateTimeImmutable`, `PrePersist`/`PreUpdate` lifecycle callbacks where an `updatedAt` exists (`BoardGame`), `PrePersist`-only for creation-only timestamps (`Inscription` pattern) — `LoanLog` is insert-only so needs only `PrePersist` for `loanedAt`; `Review` is upsertable so needs both, mirroring `BoardGame`.
- **Turbo convention**: any new form-handling action must keep the `422`/`303` split; the new actions here are simple POST-CSRF-redirect (no form), so they follow the plain `HTTP_SEE_OTHER` redirect pattern used by `approve`/`reject`/`return`/`unregister`, not the form-render pattern.

### Files to Reference

| File | Purpose |
| ---- | ------- |
| `src/Entity/BoardGame.php` | Entity to extend (`archived` column); status lifecycle pattern already established here |
| `src/Entity/Inscription.php` | `PrePersist`-only lifecycle pattern for insert-only entities — precedent for `LoanLog` |
| `src/Repository/BoardGameRepository.php` | Repository shape precedent for `LoanLogRepository`/`ReviewRepository`; `findAllOrderByTitleQb()` needs an `archived = false` filter added |
| `src/Controller/Admin/LudothequeController.php` | `approve()` needs a `LoanLog` insert added; `delete()` (lines 190-210) becomes `archive()` |
| `src/Controller/UserDashboardController.php` | `requestLoan()` (343-371) and `unregister()` (255-313) are the direct precedents for the two new member actions; `requestLoan()` itself also needs the archived-guard fix (Task 1a) |
| `templates/admin/ludotheque/index.html.twig` | Add overdue indicator; swap delete button/CSRF token for archive |
| `templates/admin/ludotheque/_form.html.twig`, `new.html.twig`, `edit.html.twig` | Unchanged — catalog form fields aren't affected by this spec |
| `templates/user_dashboard/index.html.twig` | Ludothèque section (lines ~153-220 per current diff) needs: overdue styling, cancel button on own `pending` row, rate control (gated on eligibility), average rating display |
| `tests/Unit/BoardGameTest.php`, `tests/Unit/BoardGameRepositoryTest.php` | Existing tests to extend minimally (`archived` default `false`) — don't restructure, mirror their existing style |
| `_bmad-output/implementation-artifacts/spec-ludotheque-management.md` | Prior frozen spec — most boundaries still hold (one active loan per member, admin sets due date, admin-only return). Its `Never: no loan-history entity` boundary is explicitly renegotiated by `LoanLog` (see Problem Statement) — a changelog entry documenting this must be appended to that file as part of this work, per its own established changelog convention |

### Technical Decisions

- **Extend, not replace**: confirmed after discovering `BoardGame` + full workflow already implemented and tested. No `Game`/`Loan` entity split; `BoardGame` remains the single source of truth for current status.
- **`category`/`condition` stay as-is** (freeform text / hardcoded `ChoiceType`) — already match the locked condition values exactly; converting to PHP backed enums considered and dropped as unrequested rework.
- **`LoanLog` is a minimal, insert-only, never-displayed loan-history log** — its only job is answering "has user X ever had game Y on loan" for `Review` eligibility. One row inserted per admin `approve()` call, not shown in any UI, not paginated/listed anywhere. This is a deliberate, explicit renegotiation of the frozen prior spec's "no loan-history entity" boundary (see Problem Statement) — narrower in surface area than a full audit trail (no admin UI, no timestamps beyond `loanedAt`), but functionally the same category of thing that boundary banned, and treated as such rather than downplayed.
- **`Review` eligibility gate**: `ReviewRepository`/controller checks `LoanLogRepository::hasBorrowed($game, $user)` before allowing create-or-update of a `Review`. Without at least one `LoanLog` row for that (game, user) pair, the rate action is refused.
- **`archived` replaces hard-delete, and is enforced everywhere a game could otherwise become active**: guarded on `archive()` itself (only while `status === available`, same as the current `delete()`) — but archiving alone is not sufficient. `requestLoan()` (existing action, Task 1a) must also refuse when `boardGame.isArchived()`, otherwise a member can still request an archived game directly via its `{id}`, flip it to `pending`/`loaned`, and — because `findAllOrderByTitleQb()` now filters out archived games everywhere (Task 6) — that active loan becomes invisible to both the admin and the member's own dashboard with no UI path to resolve it. The `requestLoan()` guard is what actually makes archiving safe; the `archive()`-time guard alone is not enough. `Review`/`LoanLog` rows for an archived game are preserved (never cascade-deleted), since average ratings should survive a game leaving active circulation.
- **Per-member active-loan cap stays application-level** (`findActiveForUser()`), not upgraded to a DB constraint — consistent with the "extend, minimal change" direction and with how similar capacity checks (`Inscription`/`Activity` max participants) already work elsewhere in this codebase without DB-level enforcement.
- **`Review` upsert has the same check-then-act shape as `findActiveForUser()`, and is disclosed the same way**: `rateGame()`'s "look up existing `Review`, else create" (Task 11) is not atomic — two near-simultaneous first-time ratings from the same user for the same game can both pass the "no existing review" check and then collide on the `(board_game_id, user_id)` unique constraint, one of the two flushes throwing a `Doctrine\DBAL\Exception\UniqueConstraintViolationException`. Task 11 now catches that specific exception around the flush and treats it as "someone already rated this — reload and try again" rather than letting it surface as an uncaught `500`. This is the same class of narrow race as `findActiveForUser()`'s (accepted, not DB-constrained, per the point above) but here it's caught defensively because an uncaught exception is a worse failure mode than a stale read.
- **`archive()`'s guard ordering matches the majority pattern (CSRF before status), not `delete()`'s**: `approve()`/`reject()`/`return()` all check CSRF first, then status; the existing `delete()` (this spec's stated "same shape" precedent) checks status first, then CSRF — an inconsistency in the code being extended, not something to propagate. `archive()` uses CSRF-then-status, matching the majority of existing actions, and AC 8a below covers the combined case explicitly so this isn't left ambiguous for whoever implements it.
- **Overdue is derived, not stored**: `status === loaned && returnDueAt < now`, computed at render time in both the admin index and the member dashboard — no new column needed.
- **N+1 queries accepted, not optimized, for this iteration**: Task 12 fires up to 3 queries per game per `/mon-espace` render (`averageFor`/`hasBorrowed`/`findOneForUserAndGame`). At this project's scale (a single association's game catalog, unpaginated on the member dashboard) this is judged acceptable rather than worth a batched-query rewrite now; flagged here explicitly (not silently) as a spot to revisit if the catalog grows materially.

## Implementation Plan

### Tasks

- [x] Task 1: Add `archived` flag to `BoardGame`
  - File: `src/Entity/BoardGame.php`
  - Action: Add `#[ORM\Column(options: ['default' => false])] private bool $archived = false;` with `isArchived(): bool` / `setArchived(bool $archived): static` getter/setter, placed after `getUpdatedAt()`, matching the file's existing getter/setter style.
  - Notes: No lifecycle-callback change needed.

- [x] Task 1a: Block loan requests on archived games (required for archiving to actually work)
  - File: `src/Controller/UserDashboardController.php`
  - Action: In `requestLoan()` (lines 344-371), add a guard immediately alongside the existing `$boardGame->getStatus() !== BoardGame::STATUS_AVAILABLE` check (line 358): `if ($boardGame->isArchived()) { $this->addFlash('error', 'Ce jeu n\'est plus disponible.'); return $this->redirectToRoute('app_user_dashboard'); }` — same flash text as the existing not-available case (an archived game should read to the member exactly like an unavailable one, no need for a distinct message).
  - Notes: Without this task, `archived` only hides a game from listings (Task 6) — it does NOT stop a member from requesting it directly via `/mon-espace/ludotheque/{id}/emprunter`, which would flip it to `pending` and then make that active loan invisible everywhere (since Task 6's filter also hides it from the admin list). This task is what makes the `archived` flag actually mean "retired," not just "hidden."

- [x] Task 2: Create `LoanLog` entity (insert-only, never displayed)
  - File: `src/Entity/LoanLog.php` (new)
  - Action: `id`; `boardGame` (`ManyToOne` → `BoardGame`, `nullable: false`, `onDelete: 'CASCADE'`); `user` (`ManyToOne` → `User`, `nullable: false`, `onDelete: 'CASCADE'`); `loanedAt` (`DATETIME_IMMUTABLE`, not nullable, set via `#[ORM\PrePersist]` — mirror `Inscription`'s `setCreatedAtValue()` shape exactly but named `setLoanedAtValue()`). `#[ORM\Entity(repositoryClass: LoanLogRepository::class)] #[ORM\HasLifecycleCallbacks]`, never `final`.
  - Notes: Deliberately CASCADE (not `SET NULL` like `BoardGame::borrower`/`Activity::proposedBy`) — a log row with a null user is meaningless and should disappear with the user.

- [x] Task 3: Create `LoanLogRepository`
  - File: `src/Repository/LoanLogRepository.php` (new)
  - Action: `extends ServiceEntityRepository` (mirror `CarouselSlideRepository`'s minimal shape); add `hasBorrowed(BoardGame $boardGame, User $user): bool` — `createQueryBuilder('l')->select('COUNT(l.id)')->andWhere('l.boardGame = :game')->andWhere('l.user = :user')->setParameter('game', $boardGame)->setParameter('user', $user)->getQuery()->getSingleScalarResult() > 0`.

- [x] Task 4: Create `Review` entity
  - File: `src/Entity/Review.php` (new)
  - Action: `id`; `boardGame` (`ManyToOne` → `BoardGame`, `nullable: false`, `onDelete: 'CASCADE'`); `user` (`ManyToOne` → `User`, `nullable: false`, `onDelete: 'CASCADE'`); `rating` (`Types::SMALLINT`, not nullable); `setRating(int $rating): static` throws `\InvalidArgumentException` when outside `1..5` (mirror `BoardGame::setStatus()`'s validation shape/message style); `createdAt`/`updatedAt` with `PrePersist`/`PreUpdate` (mirror `BoardGame` exactly, both timestamps). Add `#[ORM\UniqueConstraint(name: 'uniq_review_board_game_user', columns: ['board_game_id', 'user_id'])]` on the entity.
  - Notes: Never `final`.

- [x] Task 5: Create `ReviewRepository`
  - File: `src/Repository/ReviewRepository.php` (new)
  - Action: `averageFor(BoardGame $boardGame): ?float` — `select('AVG(r.rating)')->andWhere('r.boardGame = :game')->setParameter('game', $boardGame)->getQuery()->getSingleScalarResult()`, cast to `(float)` or return `null` if the scalar result is `null` (no reviews yet); `findOneForUserAndGame(BoardGame $boardGame, User $user): ?Review` — `andWhere` both, `getOneOrNullResult()`.

- [x] Task 6: Exclude archived games from catalog queries
  - File: `src/Repository/BoardGameRepository.php`
  - Action: Add `->andWhere('b.archived = false')` inside `findAllOrderByTitleQb()` (the single QueryBuilder both `LudothequeController::index()` and `UserDashboardController::index()` consume) — one change covers both admin and member listings, per the "no archived-browsing UI" scope decision.
  - Notes: **This breaks two existing passing tests** — `BoardGameRepositoryTest::testFindAllOrderByTitleQbBuildsQuery()` and `testFindAllOrderByTitleReturnsResults()` mock the `QueryBuilder` and only stub `leftJoin`/`addSelect`/`orderBy`/`getQuery` with `willReturnSelf()`; the new `andWhere()` call is unstubbed and will break the fluent chain. As part of this task, update both tests' mock setup to also stub `andWhere()` (`willReturnSelf()`) and, where the test asserts call strings, add the expected `andWhere('b.archived = false')` assertion — do not leave these tests red.

- [x] Task 6a: Add `hasBorrowed()`-style repository test coverage for the zero-review path
  - File: `tests/Unit/ReviewRepositoryTest.php` (created in Task 16 — this task's requirement folds into that one; listed separately here so it isn't lost)
  - Action: The mocked-QueryBuilder test for `averageFor()` must explicitly cover `getSingleScalarResult()` returning `null` (zero reviews) and assert the method returns `null`, not `0.0` — this is the exact path AC 10 depends on, and it's easy to only test the happy (non-null) case.

- [x] Task 7: Generate the migration
  - File: `migrations/VersionYYYYMMDDHHMMSS.php` (generated, do not hand-write)
  - Action: Run `php bin/console doctrine:migrations:diff` after Tasks 1-4 are in place. Verify the generated SQL: `ALTER TABLE board_game ADD archived TINYINT(1) DEFAULT 0 NOT NULL`; `CREATE TABLE loan_log (...)` with FKs to `board_game`/`user` both `ON DELETE CASCADE`; `CREATE TABLE review (...)` with the same FK shape plus a unique index on `(board_game_id, user_id)`.

- [x] Task 8: Log the loan on approval
  - File: `src/Controller/Admin/LudothequeController.php`
  - Action: In `approve()` (lines 94-129), right after `$boardGame->setReturnDueAt($returnDueAt);` and before `$this->entityManager->flush();`, construct `$loanLog = new LoanLog(); $loanLog->setBoardGame($boardGame); $loanLog->setUser($boardGame->getBorrower()); $this->entityManager->persist($loanLog);` — one flush covers both writes. Add `use App\Entity\LoanLog;` import.

- [x] Task 9: Replace hard-delete with archive
  - File: `src/Controller/Admin/LudothequeController.php`
  - Action: Replace the `delete()` method (lines 190-210) with `archive()`: change the route to `#[Route('/{id}/archiver', name: 'archive', requirements: ['id' => '\d+'], methods: ['POST'])]`, CSRF token id from `'delete' . $boardGame->getId()` to `'archive' . $boardGame->getId()`, replace `$this->entityManager->remove($boardGame);` with `$boardGame->setArchived(true);`, update the flash message to `'Le jeu « ' . $title . ' » a été archivé.'`. Keep the existing `status !== STATUS_AVAILABLE` guard and its message text unchanged, but **reorder the guards: check CSRF first, then status** — matching `approve()`/`reject()`/`return()`'s order, not `delete()`'s (which checks status before CSRF). This is a deliberate deviation from the `delete()` precedent; see AC 8a.

- [x] Task 10: Member cancels their own pending request
  - File: `src/Controller/UserDashboardController.php`
  - Action: New action inserted immediately after `requestLoan()`'s closing brace (line 371) and before the blank line 372 / `deleteAccount()`'s docblock (line 373) — i.e. between the two methods, not inside either: `#[Route('/mon-espace/ludotheque/{id}/annuler', name: 'app_user_ludotheque_cancel', requirements: ['id' => '\d+'], methods: ['POST'])] public function cancelLoanRequest(BoardGame $boardGame, Request $request, EntityManagerInterface $em): Response`. CSRF check `'cancel_loan' . $boardGame->getId()` (same error-flash-redirect shape as other actions). Guard: `$boardGame->getStatus() !== BoardGame::STATUS_PENDING || $boardGame->getBorrower()?->getId() !== $this->getUser()->getId()` → flash `'Cette demande ne peut plus être annulée.'`, redirect. Otherwise: `setStatus(BoardGame::STATUS_AVAILABLE)`, `setBorrower(null)`, `setRequestedAt(null)`, flush, flash success `'Votre demande d\'emprunt pour « ' . $boardGame->getTitle() . ' » a été annulée.'`, redirect to `app_user_dashboard`.

- [x] Task 11: Member rates a game
  - File: `src/Controller/UserDashboardController.php`
  - Action: New action: `#[Route('/mon-espace/ludotheque/{id}/noter', name: 'app_user_ludotheque_rate', requirements: ['id' => '\d+'], methods: ['POST'])] public function rateGame(BoardGame $boardGame, Request $request, EntityManagerInterface $em, LoanLogRepository $loanLogRepository, ReviewRepository $reviewRepository): Response`. CSRF `'rate_game' . $boardGame->getId()`. Guard eligibility: `!$loanLogRepository->hasBorrowed($boardGame, $user)` → flash `'Vous devez avoir emprunté ce jeu pour le noter.'`, redirect. Read `$rating = $request->request->getInt('rating')`; if not in `1..5`, flash `'La note doit être comprise entre 1 et 5.'`, redirect. Look up `$review = $reviewRepository->findOneForUserAndGame($boardGame, $user)`; if null, create new `Review`, set `boardGame`/`user`, `persist()`. Call `$review->setRating($rating)`; wrap the `flush()` in `try { $em->flush(); } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException) { $this->addFlash('error', 'Votre note a déjà été enregistrée, veuillez réessayer.'); return $this->redirectToRoute('app_user_dashboard'); }` to handle the concurrent-first-rating race (two simultaneous first-time ratings both passing the null check); on success, flash `'Votre note pour « ' . $boardGame->getTitle() . ' » a été enregistrée.'`.

- [x] Task 12: Feed ratings/eligibility/overdue data to the dashboard
  - File: `src/Controller/UserDashboardController.php`
  - Action: Add `ReviewRepository $reviewRepository, LoanLogRepository $loanLogRepository` params to `index()`. After `$boardGames = $boardGameRepository->findAllOrderByTitle();`, build `$averageRatings = []; $canRate = []; $myRatings = [];` and loop `foreach ($boardGames as $bg) { $averageRatings[$bg->getId()] = $reviewRepository->averageFor($bg); $canRate[$bg->getId()] = $loanLogRepository->hasBorrowed($bg, $user); $myReview = $reviewRepository->findOneForUserAndGame($bg, $user); $myRatings[$bg->getId()] = $myReview?->getRating(); }`. Pass `averageRatings`, `canRate`, `myRatings` to the template alongside the existing `boardGames`/`activeBoardGame`.

- [x] Task 13: Admin template — overdue indicator + archive button
  - File: `templates/admin/ludotheque/index.html.twig`
  - Action: In both the mobile "Emprunté" badge (line 30) and the desktop status cell (line 97), add an overdue variant: when `boardGame.status == 'loaned' and boardGame.returnDueAt and boardGame.returnDueAt < date()`, render a red/distinct badge (e.g. "En retard depuis le {{ boardGame.returnDueAt|date('d/m/Y') }}") instead of the plain "Emprunté" one. In both the mobile delete form (lines 65-68) and desktop delete form (lines 127-130), change `action` to `app_admin_ludotheque_archive`, CSRF token to `csrf_token('archive' ~ boardGame.id)`, button label to "Archiver", confirm dialog text to `'Archiver ce jeu ?'`.

- [x] Task 14: Member template — cancel, rate, average rating, overdue
  - File: `templates/user_dashboard/index.html.twig`
  - Action: In the `pending` + `isOwn` branch (current "Demande en attente" badge, ~lines 191-195 of the ludothèque section), add a cancel form: POST to `app_user_ludotheque_cancel`, CSRF `cancel_loan`, small "Annuler" button next to the badge. In the `loaned` + `isOwn` branch (~lines 197-201), add an overdue variant of the badge when `boardGame.returnDueAt < date()` (mirror the admin template's red styling). Below the status block for every card, show `averageRatings[boardGame.id]` when not null (e.g. "★ {{ averageRatings[boardGame.id]|number_format(1) }}"); when `canRate[boardGame.id]` is true, add a small 1-5 rating control (radio buttons or a `select`, POST to `app_user_ludotheque_rate`, CSRF `rate_game`, pre-selected to `myRatings[boardGame.id]` if set).

- [x] Task 15: Tests for `LoanLog`
  - Files: `tests/Unit/LoanLogTest.php`, `tests/Unit/LoanLogRepositoryTest.php` (new)
  - Action: Mirror `InscriptionEntityTest`/`InscriptionRepositoryTest`'s style exactly (simple `PrePersist`-only entity: assert `loanedAt` is null before, `\DateTimeImmutable` after `setLoanedAtValue()`; repository test mocks `createQueryBuilder()` via the project's anonymous-subclass pattern, asserts `hasBorrowed()`'s `andWhere`/`setParameter` call strings, `#[AllowMockObjectsWithoutExpectations]` as needed).

- [x] Task 16: Tests for `Review`
  - Files: `tests/Unit/ReviewTest.php`, `tests/Unit/ReviewRepositoryTest.php` (new)
  - Action: Mirror `BoardGameTest`/`BoardGameRepositoryTest`'s style: `setRating()` accepts `1`/`5`, throws `\InvalidArgumentException` for `0` and `6`; `PrePersist`/`PreUpdate` timestamp tests identical in shape to `BoardGame`'s. Repository test mocks the QueryBuilder for `averageFor()`/`findOneForUserAndGame()` — **must explicitly include the case where `getSingleScalarResult()` returns `null` (zero reviews), asserting `averageFor()` returns `null`, not `0.0`** (this is the exact path AC 10 depends on; see Task 6a).

- [x] Task 17: Extend `BoardGameTest` for `archived`
  - File: `tests/Unit/BoardGameTest.php`
  - Action: Add `testArchivedDefaultsFalse()` and a set/get round-trip test, matching the file's existing test style (no new helper/builder).

### Acceptance Criteria

- [x] AC 1: Given an admin approves a `pending` request, when the flush completes, then a `LoanLog` row exists for that (game, borrower) pair.
- [x] AC 1a: Given a game with `archived === true`, when a member submits the loan-request action on it directly (its `{id}`, regardless of whether it appears in any listing), then the request is refused with the same "Ce jeu n'est plus disponible." flash used for a non-`available` game, and `status` stays unchanged.
- [x] AC 2: Given a member who has never had a `LoanLog` row for a game, when they submit the rate action, then the request is refused with the French flash "Vous devez avoir emprunté ce jeu pour le noter." and no `Review` is created.
- [x] AC 3: Given a member with an eligible `LoanLog` row for a game, when they submit a rating of `4`, then a `Review` is created with `rating = 4`; when they submit `2` again for the same game, then the existing `Review` is updated to `2`, not duplicated (unique constraint holds).
- [x] AC 4: Given a rating outside `1..5` is submitted, when the action runs, then it's refused with a French flash and no `Review` is created/changed.
- [x] AC 5: Given a member has a `pending` request on a game, when they submit the cancel action, then the game returns to `available`, `borrower`/`requestedAt` are cleared, and a French success flash is shown.
- [x] AC 6: Given a `pending`/`loaned` game (not their own, or not `pending`), when a member submits the cancel action on it, then it's refused with a French flash and no state changes.
- [x] AC 7: Given a game with `status === available`, when an admin submits the archive action, then `archived` becomes `true` and the game disappears from both the admin list and the member catalog.
- [x] AC 8: Given a game with `status === pending` or `loaned`, when an admin submits the archive action, then it's refused with the existing French flash and `archived` stays `false`.
- [x] AC 8a: Given a game with `status === pending`/`loaned` AND an invalid/missing CSRF token, when the archive action is submitted, then the response is the CSRF flash ("Jeton de sécurité invalide."), not the status-guard flash — i.e. CSRF is checked first, matching `approve()`/`reject()`/`return()`, not `delete()`'s ordering.
- [x] AC 9: Given a `loaned` game whose `returnDueAt` is in the past, when the admin list or the member's own dashboard renders, then an overdue indicator is shown (distinct from the plain "Emprunté" state) in both places.
- [x] AC 10: Given a game with at least one `Review`, when the catalog renders (admin or member), then the average rating is displayed; given a game with zero `Review`s, then no average is shown (not "0" or an error).
- [x] AC 11: Given an invalid or missing CSRF token on any new action (`archive`, `cancel`, `rate`), when submitted, then the action is refused with the standard "Jeton de sécurité invalide." flash and no state change occurs.

## Additional Context

### Dependencies

- No new Composer packages — everything uses already-installed Symfony/Doctrine/KnpPaginator components.
- Tasks 1-6 (entities/repositories) must land before Task 7 (migration diff); Task 8 depends on Task 2 (`LoanLog` must exist); Tasks 10-12 depend on Tasks 2-6 (repositories injected into the controller); Tasks 13-14 (templates) depend on Task 12 (controller must pass the new template variables first, or the templates will error on undefined variables).

### Testing Strategy

- **Unit tests**: Tasks 15-17 cover the new/changed entities and repositories, following the project's existing two-tier convention (direct-instantiation entity tests, QueryBuilder-mock repository tests) — no functional/`WebTestCase` coverage exists in this project and none is introduced here, consistent with current practice.
- **Manual verification** (documented in Verification below) is required for the controller actions and Twig changes, since this project has zero functional/CSRF/authz test coverage today (per `project-context.md`) — this spec doesn't change that baseline.
- Run `php bin/phpunit` after Tasks 15-17; the suite's strict settings (`failOnDeprecation`/`failOnNotice`/`failOnWarning`) will catch any Doctrine mapping issues from the new entities immediately.

### Notes

- **Known limitation carried forward**: the one-active-loan-per-member check (`findActiveForUser()`) remains an application-level check-then-act, not a DB constraint — a genuine (if narrow) race condition exists if the same member double-submits a request in quick succession. This is a pre-existing characteristic of the code being extended, not a regression introduced here; flagged for future hardening, not fixed in this spec. (The structurally identical race on `Review` upsert, by contrast, IS mitigated in this spec — Task 11 — because an uncaught exception there is a worse failure mode than a stale read here.)
- **Known limitation, explicitly out of scope**: archived games become invisible everywhere (no admin "view archived" or "reactivate" UI). If that's needed later, it's an additive change (a filter toggle on the existing admin index), not a rework.
- **Risk flagged during design**: `Review`/`LoanLog` both cascade-delete when their `user` is removed (account deletion via `UserDashboardController::deleteAccount()`) — a member's reviews and loan-eligibility history vanish with their account. This mirrors the "no orphaned personal data" spirit of the rest of the codebase (e.g. `Inscription` is *not* cascade-deleted with the user, but `Review`/`LoanLog` are user-opinion/user-specific data, closer to account data than to `Activity` participation records) — worth a sanity check with the user if this surprises them at review time.
- **Future consideration, explicitly out of scope now**: no email notification when a request is approved/rejected/returned; no admin moderation of reviews; no multi-copy inventory.

## Spec Change Log

- 2026-07-19: Adversarial review (`review-adversarial-general` task, run via an isolated subagent with no session context) found 13 issues against the first drafted version of this spec — 3 Critical, 4 High, 4 Medium, 2 Low. Fixed here: added Task 1a (archived games must be blocked in `requestLoan()`, not just hidden from listings — closes the Critical archiving-doesn't-work + invisible-stuck-loan pair); corrected two fabricated/wrong code citations for `unregister()` (was quoting a line that doesn't exist in that method, and citing the wrong line range — now `255-313`, verified against the file); added the missing test-fix instruction to Task 6 so it no longer silently breaks `BoardGameRepositoryTest`; corrected Task 10's insertion point (was pointing mid-docblock of `deleteAccount()`); added race-condition handling to Task 11's `Review` upsert (`UniqueConstraintViolationException` catch); made `archive()`'s CSRF/status guard ordering explicit and added AC 8a to cover it; explicitly named the frozen `spec-ludotheque-management.md`'s "no loan-history entity" boundary as being renegotiated by `LoanLog`, rather than passing over it silently — see the changelog entry required in that file (Files to Reference). N+1 queries (Task 12) and the zero-review-average test path were disclosed/strengthened rather than left implicit.
- 2026-07-19: Post-implementation adversarial + edge-case review (two isolated subagents, no session context, run against the full diff) found one real defect surviving deduplication: `LudothequeController::approve()` constructs a `LoanLog` with `setUser($boardGame->getBorrower())`, and `LoanLog.user` is `nullable: false` — but `BoardGame.borrower` is nulled via `onDelete: 'SET NULL'` if the borrower's account is deleted (via the pre-existing, unguarded `UserDashboardController::deleteAccount()`) while their request is still `pending`. Approving such an orphaned request threw an uncaught `NOT NULL` constraint violation. Classified as `patch` (trivial, unambiguous fix matching the method's existing guard-then-flash-then-redirect shape) rather than `bad_spec` — fixed by adding a null-borrower guard in `approve()` before constructing the `LoanLog`. All other findings were pre-existing check-then-act races, N+1 query patterns, and validation gaps already explicitly disclosed and accepted in this spec's own Technical Decisions/Notes sections, or matched established codebase conventions (route-level-only authorization, no functional test coverage) — routed to `reject` (noise/duplicate-of-disclosed) or `defer` (see `deferred-work.md` for the two genuinely pre-existing, undisclosed items: `BoardGame` numeric-field validation gaps, and `approve()`'s unbounded `returnDueAt`). No `intent_gap`/`bad_spec` findings survived triage, so no loopback to step-02/step-03 was needed.

## Suggested Review Order

**Data model: the new eligibility/rating layer**

- Entry point — the insert-only log whose sole job is answering "did this user ever borrow this game", deliberately minimal (no timestamps beyond `loanedAt`, no UI).
  [`LoanLog.php:19`](../../src/Entity/LoanLog.php#L19)
- Upsertable 1-5 rating, gated on loan history via a DB unique constraint rather than app logic alone.
  [`Review.php:19`](../../src/Entity/Review.php#L19)
- Rating bounds enforced in the setter (mirrors `BoardGame::setStatus()`'s validation shape), not just in the controller.
  [`Review.php:97`](../../src/Entity/Review.php#L97)

**Loan lifecycle: closing the archive/orphan gaps**

- `archived` flag added after the existing timestamp getters — no lifecycle-callback change needed.
  [`BoardGame.php:88`](../../src/Entity/BoardGame.php#L88)
- `requestLoan()` refuses archived games directly — without this guard, archiving alone doesn't stop a new loan (Task 1a).
  [`UserDashboardController.php:379`](../../src/Controller/UserDashboardController.php#L379)
- `approve()` now writes the `LoanLog` row — and, post-review, refuses to approve an orphaned request whose borrower was deleted mid-flight.
  [`LudothequeController.php:116`](../../src/Controller/Admin/LudothequeController.php#L116)
- `archive()` replaces hard-delete; CSRF is checked before the status guard, deliberately deviating from `delete()`'s prior ordering (AC 8a).
  [`LudothequeController.php:214`](../../src/Controller/Admin/LudothequeController.php#L214)

**Member self-service actions**

- New action: member cancels their own `pending` request — ownership + status guard before mutation.
  [`UserDashboardController.php:401`](../../src/Controller/UserDashboardController.php#L401)
- New action: member rates a game, gated on `LoanLogRepository::hasBorrowed()`, with a `UniqueConstraintViolationException` catch for the concurrent-first-rating race.
  [`UserDashboardController.php:432`](../../src/Controller/UserDashboardController.php#L432)
- Dashboard now feeds `averageRatings`/`canRate`/`myRatings` per game to the template — the source of the N+1 pattern disclosed in Technical Decisions.
  [`UserDashboardController.php:46`](../../src/Controller/UserDashboardController.php#L46)

**Query/repository layer**

- Catalog query excludes archived games — the single filter point covering both admin and member listings.
  [`BoardGameRepository.php:35`](../../src/Repository/BoardGameRepository.php#L35)
- `averageFor()` returns `null` (not `0.0`) on zero reviews — the exact path AC 10 depends on.
  [`ReviewRepository.php:24`](../../src/Repository/ReviewRepository.php#L24)
- `hasBorrowed()` — the single query backing both the rate-gate and the dashboard's `canRate` map.
  [`LoanLogRepository.php:25`](../../src/Repository/LoanLogRepository.php#L25)

**Peripherals**

- Migration adds `board_game.archived` plus `loan_log`/`review` tables with cascading FKs and the rating unique index.
  [`Version20260719101848.php`](../../migrations/Version20260719101848.php)
- Admin/member templates: overdue badge, archive button swap, cancel button, rating control.
  [`templates/admin/ludotheque/index.html.twig`](../../templates/admin/ludotheque/index.html.twig) · [`templates/user_dashboard/index.html.twig`](../../templates/user_dashboard/index.html.twig)
- New/extended unit tests mirroring the project's two-tier convention (entity + QueryBuilder-mock repository tests).
  [`tests/Unit/LoanLogTest.php`](../../tests/Unit/LoanLogTest.php) · [`tests/Unit/ReviewTest.php`](../../tests/Unit/ReviewTest.php) · [`tests/Unit/LoanLogRepositoryTest.php`](../../tests/Unit/LoanLogRepositoryTest.php) · [`tests/Unit/ReviewRepositoryTest.php`](../../tests/Unit/ReviewRepositoryTest.php)
