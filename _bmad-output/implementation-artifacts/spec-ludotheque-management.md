---
title: 'Ludothèque management (admin CRUD + member loan requests + approval workflow)'
type: 'feature'
created: '2026-07-19'
status: 'in-progress'
review_loop_iteration: 0
context: []
baseline_commit: '5aeb98cb22e17a35d30f94d3a743e85494d8c225'
---

<frozen-after-approval reason="human-owned intent — do not modify unless human renegotiates">

## Intent

**Problem:** The association has no catalog of its board games, and no way for members to request one on loan or for admins to track/approve who currently has a game out.

**Approach:** Add a `BoardGame` entity with a three-state status (`available` / `pending` / `loaned`, mirroring the existing `Activity::status` pending/published pattern) plus an admin CRUD section (`/admin/ludotheque`) to manage the catalog and approve/reject/return loans, and a member-facing catalog on `/mon-espace` where members can request to borrow an available game.

## Boundaries & Constraints

**Always:**
- Status lifecycle: `available` → (member requests) → `pending` → (admin approves, sets return-due date) → `loaned` → (admin marks returned) → `available`. Admin can also `reject` a `pending` request back to `available`.
- A member may hold at most **one active** game at a time (a `pending` request or a `loaned` game) — a new request is rejected while they already have one outstanding, per `BoardGameRepository::findActiveForUser()`.
- Only an admin can mark a game returned (member-side has no return action).
- Borrower is an existing registered `User` (ManyToOne, mirrors `Activity::proposedBy` — nullable, `onDelete: 'SET NULL'`), meaningful only while status is `pending`/`loaned`.
- Admin CRUD lives under `/admin/ludotheque`, route name prefix `app_admin_ludotheque_` — already covered by the existing `^/admin` → `ROLE_ADMIN` access_control rule. Member request action lives under `/mon-espace/...` — already covered by `^/mon-espace` → `ROLE_USER`. No new `security.yaml` entries needed.
- Member dashboard never shows another member's identity — only "own request/loan" state is personalized; other members' `pending`/`loaned` games show generically as unavailable.
- Follow existing conventions: PrePersist/PreUpdate lifecycle callbacks, status setter validates against allowed values (mirrors `Activity::setStatus`), manual per-action CSRF tokens (`'approve'.$id`, `'reject'.$id`, `'return'.$id`, `'delete'.$id`, `'request_loan'.$id`), Turbo `422`/`303` convention on form submit, French flash messages, entity NOT `final`.
- A game can only be deleted while `available` (not `pending` or `loaned`).

**Ask First:**
- None — the approval workflow now mirrors the existing `Activity` propose/approve/reject pattern closely enough that no further human-gated decision is anticipated during execution.

**Never:**
- No CSV export, no loan-history entity (only current status/dates on `BoardGame`), no per-copy quantity tracking, no non-member borrowers, no due-date email reminders, no member-initiated return, no waitlist for a `pending`/`loaned` game.

## I/O & Edge-Case Matrix

| Scenario | Input / State | Expected Output / Behavior | Error Handling |
|----------|--------------|---------------------------|----------------|
| Create game | title="Catan", category="Stratégie", maxPlayers=4 | Persisted, status=`available`, visible in admin list and member catalog | N/A |
| Member requests a loan | game status=`available`, member has no other active game | status→`pending`, borrower=member, requestedAt=now; member sees "Demande en attente"; admin sees it awaiting validation | N/A |
| Member requests while already holding one | member already has a `pending`/`loaned` game elsewhere | Request rejected, no state change | Flash: "Vous avez déjà un jeu en cours d'emprunt ou en attente de validation." |
| Admin approves | game status=`pending`, admin submits with a return-due date | status→`loaned`, loanedAt=now, returnDueAt=submitted date | N/A |
| Admin rejects | game status=`pending` | status→`available`, borrower/requestedAt cleared | N/A |
| Admin marks returned | game status=`loaned` | status→`available`, borrower/loanedAt/returnDueAt cleared | N/A |
| Delete while pending/loaned | status≠`available`, admin submits delete | Deletion rejected, game unchanged | Flash: "Ce jeu est en attente ou en cours d'emprunt, il doit être disponible pour être supprimé." |
| Invalid CSRF on any admin/member loan action | forged/missing `_token` | Action rejected | Flash: "Jeton de sécurité invalide.", redirect back |

</frozen-after-approval>

## Code Map

- `src/Entity/Activity.php` + `src/Controller/Admin/ActivityController.php` (`approve`/`reject` actions, `STATUS_PUBLISHED`/`STATUS_PENDING`) -- direct precedent for the status lifecycle, validated setter, and per-action CSRF pattern to replicate
- `src/Controller/Admin/CarouselController.php` + `templates/admin/carousel/*` -- simplest existing CRUD (index/new/edit/delete) reference
- `templates/admin/layout.html.twig` (nav block, ~lines 20-64) -- nav-item markup to replicate for "Ludothèque", active state via `current starts with 'app_admin_ludotheque'`
- `templates/user_dashboard/index.html.twig` + `src/Controller/UserDashboardController.php:40-73` (`index()`) -- section layout pattern and where to inject `BoardGameRepository` + the new `requestLoan` action
- `src/Repository/CarouselSlideRepository.php` -- minimal `ServiceEntityRepository` boilerplate
- `src/Form/ActivityType.php` -- FormType pattern: hardcoded Tailwind `attr`/`label_attr`, inline `ChoiceType` choices (for `condition`)
- `config/routes.yaml`, `config/services.yaml` -- confirm: attribute routing + autowiring, no manual registration needed

## Tasks & Acceptance

**Execution:**
- [x] `src/Entity/BoardGame.php` -- new entity: `title`(NotBlank), `category`(freeform), `maxPlayers`(int,nullable), `durationMinutes`(int,nullable), `condition`(string,32,nullable), `notes`(text,nullable), `status`(string,16,default `available`; constants `STATUS_AVAILABLE`/`STATUS_PENDING`/`STATUS_LOANED`; `setStatus()` throws `\InvalidArgumentException` on invalid value, mirrors `Activity`), `borrower`(ManyToOne User,nullable,SET NULL), `requestedAt`/`loanedAt`/`returnDueAt`(DateTimeImmutable,nullable), `createdAt`/`updatedAt` lifecycle callbacks -- core data model + status lifecycle
- [x] `src/Repository/BoardGameRepository.php` -- `findAllOrderByTitleQb()`+`findAllOrderByTitle()` (admin, KnpPaginator, mirrors `ActivityRepository`), `findActiveForUser(User $user): ?BoardGame` (status IN pending/loaned AND borrower=user) -- enforces one-active-game-per-member
- [x] `migrations/VersionYYYYMMDDHHMMSS.php` -- generate via `doctrine:migrations:diff` -- creates `board_game` table
- [x] `src/Form/BoardGameType.php` -- catalog fields only (no status/borrower/dates); `condition` as `ChoiceType` inline `['Neuf','Bon état','Usé','Abîmé']`; Tailwind `attr` matching `ActivityType`
- [x] `src/Controller/Admin/LudothequeController.php` -- `#[Route('/admin/ludotheque', name: 'app_admin_ludotheque_')]`; `index`(GET,paginated,title asc, shows status+borrower), `new`/`edit`(GET/POST), `approve`(POST `{id}/valider`, requires `returnDueAt` input, guards status===pending), `reject`(POST `{id}/rejeter`, guards status===pending), `return`(POST `{id}/retourner`, guards status===loaned), `delete`(POST, guards status===available) -- CRUD + approval workflow
- [x] `templates/admin/ludotheque/index.html.twig` -- status badge, borrower name/email (admin-visible), per-row approve+due-date-field/reject (pending), return (loaned), edit/delete (available)
- [x] `templates/admin/ludotheque/new.html.twig`, `edit.html.twig`, `_form.html.twig` -- mirror carousel CRUD templates
- [ ] `templates/admin/layout.html.twig` -- add "Ludothèque" nav entry
- [ ] `src/Controller/UserDashboardController.php` -- inject `BoardGameRepository`; add `requestLoan` action `#[Route('/mon-espace/ludotheque/{id}/emprunter', name: 'app_user_ludotheque_request', methods: ['POST'])]` guarded by `findActiveForUser()===null` and game status===available; CSRF `'request_loan'.$id`
- [ ] `templates/user_dashboard/index.html.twig` -- read-only "Ludothèque" section: per game show title/category/maxPlayers/duration/condition + status-aware state (`available` → "Emprunter" button, disabled+note if member already has an active game elsewhere; own `pending` → "Demande en attente"; own `loaned` → "Emprunté jusqu'au {date}"; others' pending/loaned → "Indisponible", no identity)
- [ ] `tests/Unit/BoardGameTest.php` -- default status, `setStatus()` validation/rejection, lifecycle callback timestamps
- [ ] `tests/Unit/BoardGameRepositoryTest.php` -- mock-based test of `findAllOrderByTitleQb()` and `findActiveForUser()`, mirrors `ActivityRepositoryTest`

**Acceptance Criteria:**
- Given an admin logged in, when they create a valid board game, then it appears in the admin list and the member catalog as `available`.
- Given an available game, when a member (with no other active game) requests it, then status becomes `pending` and the admin sees it awaiting validation.
- Given a member with an existing `pending`/`loaned` game, when they request another game, then the request is rejected with a French flash error and no state changes.
- Given a `pending` game, when the admin approves it with a return-due date, then status becomes `loaned`, `loanedAt`/`returnDueAt` are set, and the member's dashboard shows "Emprunté jusqu'au {date}".
- Given a `pending` game, when the admin rejects it, then status returns to `available` and borrower/requestedAt clear.
- Given a `loaned` game, when the admin marks it returned, then status returns to `available` and loan fields clear; only the admin can perform this (no member-facing return control exists).
- Given a `pending` or `loaned` game, when an admin attempts to delete it, then deletion is blocked with a French flash error.
- Given a `ROLE_USER` member on `/mon-espace`, then they see the catalog with per-game status and can only act via the request-loan control on `available` games — no edit/approve/reject/return/delete controls are exposed.

## Spec Change Log

- 2026-07-19: Replaced the original "admin directly assigns a loan" mechanism with a member-initiated request + admin approve/reject/return workflow (mirroring `Activity`'s propose/approve/reject pattern), after the human clarified members must be able to request a loan themselves, limited to one active game at a time, with only admins able to mark a return. This is a full replacement of the loan-mechanism boundaries, I/O matrix, and related tasks from the initial draft — nothing else changed.
- 2026-07-19: **Boundary renegotiated** — the `Never: ... no loan-history entity` line above is superseded. A follow-up session (`tech-spec-ludotheque-catalog-loans-ratings.md`) added member ratings on games, which requires verifying a member actually borrowed a game before letting them rate it; `BoardGame::return()` clears `borrower`, so no historical record survives a return without one. The human confirmed adding a minimal, insert-only `LoanLog` (game, user, loanedAt — never surfaced in any UI, no admin browsing, existing solely to back this eligibility check) rather than dropping the rating-eligibility requirement or the archived-only "last borrower" workaround. Every other boundary in this document (one active loan per member, admin sets due date, admin-only return, no email notifications, no multi-copy inventory) is unchanged. See the linked tech-spec for full detail.

## Design Notes

- Status lifecycle deliberately reuses `Activity`'s pending/published shape (`available`/`pending`/`loaned` + validated setter) since it's an established, well-tested pattern in this codebase for propose→admin-decide flows.
- `category` stays freeform text (like `Activity::location`); `condition` uses a small hardcoded `ChoiceType` list rather than a PHP enum — same rationale as before (open-ended/short closed vocab, no fixed list requested).
- No loan-history entity — only current status/dates on `BoardGame`, consistent with the "inventaire avec suivi d'emprunt" ask rather than a full audit log.
- The admin sets `returnDueAt` at approval time (not the member at request time), since duration is an admin/lending-policy decision, not a member choice — analogous to admin owning `Activity::status` transitions.

## Verification

**Commands:**
- `php bin/console doctrine:migrations:diff && php bin/console doctrine:migrations:migrate` -- expected: new `board_game` table, migration file added
- `php bin/phpunit tests/Unit/BoardGameTest.php tests/Unit/BoardGameRepositoryTest.php` -- expected: all green
- `php bin/console cache:clear` -- expected: no errors (routes/services/forms load cleanly)

**Manual checks (if no CLI):**
- As admin: visit `/admin/ludotheque`, create a game, then as a member request it, then as admin approve (with due date)/reject/return/delete at the appropriate status.
- As a `ROLE_USER` member: visit `/mon-espace`, request an available game, confirm a second request elsewhere is blocked while the first is outstanding, and confirm no admin controls are exposed.
