# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

Website for **La Boite Chimère**, a gaming association (board games, RPG, tabletop miniatures, LARP). Symfony 8.0 app running locally on Laragon (MySQL).

## Common Commands

```bash
# Install PHP dependencies
composer install

# Run database migrations
php bin/console doctrine:migrations:migrate

# Load fixtures (dev data)
php bin/console doctrine:fixtures:load

# Generate a new migration after entity changes
php bin/console doctrine:migrations:diff

# Watch and build Tailwind CSS
php bin/console tailwind:build --watch

# Build Tailwind CSS once
php bin/console tailwind:build

# Install JS importmap packages
php bin/console importmap:install

# Clear cache
php bin/console cache:clear

# Run tests
php bin/phpunit

# Create a new entity or controller
php bin/console make:entity
php bin/console make:controller
```

## Architecture

**Backend**: Symfony 8.0, PHP 8.4, Doctrine ORM with lifecycle callbacks for `createdAt`/`updatedAt`.

**Frontend**: No webpack/npm. Uses Symfony **AssetMapper** with **importmap** for JS modules and **symfonycasts/tailwind-bundle** for CSS. Stimulus controllers live in `assets/controllers/`. Standalone JS modules in `assets/`: `carousel.js`, `mobile_menu.js`, `reveal.js`, `theme_toggle.js`, `join_panel.js`.

**Templates**: Twig. `templates/base.html.twig` is the main layout. `templates/home/index.html.twig` is the public homepage. Admin templates are under `templates/admin/`.

### Entities

- **Activity** — association events (title, description, startAt, endAt, location, type). Type is a free string (e.g. "JDS", "JDR", "GN").
- **User** — auth entity; login by `email`, `ROLE_USER` always included, `ROLE_ADMIN` for admins.
- **CarouselSlide** — homepage hero carousel entries (tag, title, date label, button). Falls back to hardcoded slides if table is empty.
- **Inscription** — activity registrations by users.

### Routes

| Path | Controller | Notes |
|------|-----------|-------|
| `/` | `HomeController::index` | Public homepage with calendar and carousel |
| `/login`, `/logout` | `SecurityController` | Form login with CSRF |
| `/admin/activites` | `Admin\ActivityController` | Activity CRUD |
| `/admin/carousel` | `Admin\CarouselController` | Carousel CRUD |
| `/admin/inscriptions` | `Admin\ActivityRegisterController` | Registration management |

**Security note**: `/admin/*` requires `ROLE_ADMIN` and `/mon-espace` requires `ROLE_USER` via `access_control` in `security.yaml`.

### Key Patterns

- Repositories use custom query methods (e.g. `ActivityRepository::findBetween($start, $end)`).
- The homepage calendar filters activities by month/year/day via query params.
- CSRF tokens for forms use Symfony's built-in CSRF; the login CSRF token is passed from the controller (`authenticate` token id).
- Migrations are date-versioned under `migrations/`.
