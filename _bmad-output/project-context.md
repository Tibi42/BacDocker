---
project_name: 'BMAD'
user_name: 'Guill'
date: '2026-07-19'
sections_completed: ['technology_stack', 'language_rules', 'framework_rules', 'testing_rules', 'code_quality_rules', 'workflow_rules', 'critical_rules']
status: 'complete'
rule_count: 34
optimized_for_llm: true
---

# Project Context for AI Agents

_This file contains critical rules and patterns that AI agents must follow when implementing code in this project. Focus on unobvious details that agents might otherwise miss._

---

## Technology Stack & Versions

**Core:**
- PHP 8.4
- Symfony 8.0.* (framework-bundle, security-bundle, form, validator, twig-bundle, serializer, mailer, notifier, messenger+doctrine-transport, rate-limiter, asset-mapper)
- Doctrine ORM ^3.6.2 + doctrine-bundle ^3.2.2 + doctrine-migrations-bundle ^4.0
- MySQL (Laragon local)

**Key dependencies:**
- `symfony/stimulus-bundle` ^2.34, `symfony/ux-turbo` ^2.34 — Turbo Drive is active; form responses must follow the Turbo HTTP status convention (see Framework rules)
- `symfonycasts/tailwind-bundle` ^0.12.0 — no npm/webpack; CSS built via `php bin/console tailwind:build`
- `knplabs/knp-paginator-bundle` ^6.10
- `symfonycasts/reset-password-bundle` ^1.24
- Dev: `phpunit/phpunit` ^12.5.14; `symfony/browser-kit` + `symfony/css-selector` are installed but unused — only `tests/Unit/` exists, no functional tests yet

## Critical Implementation Rules

### Language-Specific Rules (PHP)

- No `declare(strict_types=1)` anywhere in the codebase — do not add it, it's not the project convention
- `final class` on Controllers, Commands, Fixtures, EventSubscribers, Security classes — but **not** on Entities (Doctrine proxy generation needs non-final entities)
- Constructor-promoted `private readonly` for injected dependencies
- `\DateTimeImmutable` everywhere for dates, never mutable `\DateTime`; setters accepting `\DateTimeInterface` normalize to immutable via `DateTimeImmutable::createFromInterface()`
- Native backed-string PHP enums (e.g. `ActivityKind`) with a `label(): string` (French label) and a static `values(): array` used for filter validation
- Native exceptions (`\InvalidArgumentException`) rather than custom exception classes; `catch (\Throwable) {}` intentionally silent in bulk-email-send loops so one failure doesn't block the main action

### Framework-Specific Rules (Symfony/Turbo)

- No i18n system: `translations/` contains only `.gitignore`. All UI text (labels, flash messages, errors) is hardcoded in **French directly in PHP/Twig** — don't introduce translation keys unless explicitly asked
- Turbo Drive is active: form controllers must return `Response::HTTP_UNPROCESSABLE_ENTITY` (422) when submitted-but-invalid, and `HTTP_SEE_OTHER` (303) on success redirects — Turbo breaks otherwise
- FormType: Tailwind utility classes are hardcoded per-field in `attr`/`label_attr` (no central form theme); French labels/constraint messages (e.g. `NotBlank(message: '...')`) live in the FormType, not the entity; custom options via `configureOptions()` (e.g. `is_admin` on `ActivityType`) toggle available choices/fields between public and admin context
- CSRF on non-form actions (approve/reject/delete): manual `isCsrfTokenValid('actionName' . $id, $token)` with a hidden `_token` field — follow this per-action+entity token-id pattern, don't reuse a generic token
- Routing: attribute-based `#[Route]` only; admin controllers always prefixed `/admin/xxx` with class-level `name: 'app_xxx_'` + short per-action name
- `security.yaml` has only 3 `access_control` rules today (`/admin`, `/mon-espace`, activity registration) — any new protected area needs an explicit entry; role hierarchy is `ROLE_SUPER_ADMIN > ROLE_ADMIN > ROLE_USER`
- Stimulus is minimal (only `carousel_preview`, `csrf_protection`, `hello` controllers) — most JS is standalone modules loaded via importmap (`assets/*.js`), not necessarily Stimulus controllers

### Testing Rules

- Only `tests/Unit/` exists — no functional/`KernelTestCase`/`WebTestCase` tests despite `symfony/browser-kit` + `symfony/css-selector` being installed dev-only
- PHPUnit is strict: `failOnDeprecation="true"`, `failOnNotice="true"`, `failOnWarning="true"` — any Doctrine deprecation or stray PHP notice/warning fails the build, not just logs it
- Entity tests: instantiate directly (`new Activity()`), assert getters/setters, `expectException()` for setter validation — no builder/factory
- Repository tests mock the `QueryBuilder` via an anonymous subclass overriding `createQueryBuilder()`, asserting `andWhere()`/`setParameter()` call strings (requires `#[AllowMockObjectsWithoutExpectations]`). These verify query-building intent only, not correctness against real Doctrine metadata — a renamed field or broken DQL won't fail them. This boilerplate is duplicated per-file with no shared base test class — expect to repeat it, not reuse a harness
- No security/CSRF/authz behavior (`SecurityHeadersSubscriber`, admin `access_control`, CSRF token actions) has test coverage today
- If a future task needs a functional test: `config/packages/doctrine.yaml` already defines `dbname_suffix: '_test%env(default::TEST_TOKEN)%'` under `when@test` — use that existing hook rather than inventing a new test-DB scheme. Also watch for `login_throttling` (5 attempts/15min in `security.yaml`) if a test logs in repeatedly
- When touching an enum `match()` (e.g. `ActivityKind::label()`) or an allowlist (e.g. `HtmlSanitizer::ALLOWED_TAGS`/`ALLOWED_ATTRS`), check whether the existing test walks every case/value — several only spot-check a few, so a new case/value can silently escape coverage
- Naming: `tests/Unit/{ClassNameOrDomain}Test.php`, roughly mirroring `src/`, `testXxx(): void` methods

### Code Quality & Style Rules

- No static analysis or style tooling configured: no `phpstan.neon`, no `.php-cs-fixer.php`. `phpstan/phpdoc-parser` in `composer.json` is a transitive dependency (used by `property-info`/`serializer`), **not** PHPStan itself — don't assume static analysis runs
- CI (`.github/workflows/docker-image.yml`) only builds the Docker image — no tests, lint, or static analysis run in CI. `php bin/phpunit` must be run manually; nothing blocks a merge on a broken test
- `.editorconfig`: 4-space indent, LF, UTF-8, except `compose*.yaml` (2-space) and `.md` (no trailing-whitespace trim)
- Twig templates: `snake_case` folder/file naming (`activity_register`, `nos_soiree_biheb`, `modal_form_page.html.twig`), partials prefixed `_` (`_form.html.twig`)
- Docblocks in French, "why" not "what" (see Language rules)
- Standard Symfony autowiring — no custom DI naming convention; services auto-registered from `src/` via `config/services.yaml`

### Development Workflow Rules

- Work happens mostly directly on `main` — no strict branch convention; the only non-main branches observed follow a `worktree-{slug}` pattern (from the `superpowers` git-worktree workflow), merged then deleted
- Commit messages are not uniform: a mix of Conventional Commits (`feat:`, `fix:`) and free-form French messages ("MAJ lighthouse", "Proto BaC mobile"). Don't enforce a strict format the history doesn't follow, but prefer `feat:`/`fix:` for clear changes, matching the more descriptive recent commits
- Spec/plan docs follow `docs/superpowers/plans/{date}-{slug}.md` + `docs/superpowers/specs/{date}-{slug}-design.md` — when a task fits a plan-then-design-then-code workflow, follow this naming convention
- Deployment: VPS OVH + Nginx (source of truth in `deploy/nginx/guillaumepecquet.ovh.conf`, guide in `docs/deploy-ovh.md`), HTTPS via Certbot at the Nginx layer (consistent with the `security.yaml` note), MySQL with `utf8mb4_unicode_ci`, PHP 8.4 required server-side
- Docker exists (`Dockerfile`, `compose.yaml`, `compose.override.yaml`) but CI only builds the image without deploying — actual deployment follows the manual OVH guide, not an automated pipeline

### Critical Don't-Miss Rules

- CSP is hardcoded in a single file (`SecurityHeadersSubscriber`) — any new external resource (CDN, embed, third-party script) must be added to that CSP string or the browser silently blocks it (no PHP error, only a console CSP violation)
- `location` is not an Enum unlike `type`/`ActivityKind`: the valid-locations list (`'L'auberge de jeunesse Yves Robert'`, `'Le Natema'`) is a hardcoded array duplicated across at least two methods in the same controller (`index()`, `exportCsv()`) — adding a location means updating every duplicate, not one central place
- Migrations are always generated via `doctrine:migrations:diff`, never hand-written — the repo has 20 dated migrations; an entity change requires regenerating the migration, not simulating it
- Email-send failure never blocks a deletion: `reject()`/`delete()` on `Activity` remove the entity even if `sendCancellationEmails()` fails silently (`catch (\Throwable) {}`) — don't assume a notification failure blocks the business action
- Never mark an Entity `final` — unlike Controllers/Commands/Subscribers, Doctrine needs to generate proxies on entities

---

## Usage Guidelines

**For AI Agents:**
- Read this file before implementing any code
- Follow ALL rules exactly as documented
- When in doubt, prefer the more restrictive option
- Update this file if new patterns emerge

**For Humans:**
- Keep this file lean and focused on agent needs
- Update when technology stack changes
- Review quarterly for outdated rules
- Remove rules that become obvious over time

Last Updated: 2026-07-19
