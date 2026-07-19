# Deferred Work

Issues surfaced incidentally during quick-dev reviews, not caused by the triggering story, collected here for later focused attention.

- source_spec: `_bmad-output/implementation-artifacts/tech-spec-ludotheque-catalog-loans-ratings.md`
  summary: `BoardGame::setMaxPlayers()`/`setDurationMinutes()` accept any integer including zero/negative, and `category`/`notes` have no length limits — only `title` has server-side `NotBlank` validation via `BoardGameType`.
  evidence: Confirmed by reading `src/Form/BoardGameType.php` and `src/Entity/BoardGame.php` — pre-existing from the prior session's `BoardGame` implementation, not introduced or touched by this story's diff.

- source_spec: `_bmad-output/implementation-artifacts/tech-spec-ludotheque-catalog-loans-ratings.md`
  summary: `LudothequeController::approve()` accepts any parseable `returnDueAt` with no sanity bound — an admin can set a due date in the past (game is "overdue" the instant it's approved) or arbitrarily far in the future.
  evidence: Confirmed by reading `LudothequeController.php:116-127` (`approve()`) — this date-parsing logic is pre-existing, unmodified by this story except for the adjacent `LoanLog` insert added right after it.
