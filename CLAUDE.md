# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Stack

- Laravel **13.7** on PHP **^8.3** (local: 8.4)
- **Breeze** (Blade stack) — auth + profile (the original `/dashboard` is removed; `/stats` is the post-login landing page); Tailwind v4 + Alpine.js
- **MySQL** for app DB (`amo_point` locally); tests use SQLite `:memory:` (see `phpunit.xml`)
- Vite (`laravel-vite-plugin`) for asset bundling

## Common commands

```bash
# Full local dev stack (server + queue:listen + pail logs + vite) in one command
composer dev

# Individually
php artisan serve              # http://127.0.0.1:8000
php artisan queue:listen       # background jobs
php artisan pail               # tail logs
npm run dev                    # Vite HMR
npm run build                  # production assets

# Tests (clears config first, then runs artisan test → PHPUnit)
composer test
php artisan test --filter=ProfileTest                 # single test class
php artisan test tests/Feature/Auth/RegistrationTest.php

# Code style — Laravel Pint (PHP-CS-Fixer wrapper)
./vendor/bin/pint              # fix
./vendor/bin/pint --test       # check only

# Initial setup on a fresh clone
composer setup                 # install + .env + key:generate + migrate + npm build

# DB
php artisan migrate            # MySQL `amo_point`
php artisan migrate:fresh --seed
php artisan tinker
```

## Architecture notes

This is the **Laravel 11+ minimal skeleton** — there is no `app/Http/Kernel.php`, no `app/Console/Kernel.php`, no `app/Exceptions/Handler.php`. All bootstrapping happens in `bootstrap/app.php` via `Application::configure()->withRouting()->withMiddleware()->withExceptions()`. Register middleware and exception handlers there, not in separate Kernel classes.

**Routing:**
- `routes/web.php` — public + profile routes, requires `routes/auth.php` at the bottom
- `routes/auth.php` — Breeze auth routes (login, register, password reset, email verification)
- `routes/console.php` — Artisan closure commands
- Health check exposed at `/up` (configured in `bootstrap/app.php`)

**Auth (Breeze, Blade):** controllers live in `app/Http/Controllers/Auth/`; views in `resources/views/auth/`; layouts in `resources/views/layouts/` (`app.blade.php`, `guest.blade.php`); the corresponding Blade components are `app/View/Components/AppLayout.php` and `GuestLayout.php`.

**Frontend pipeline:** `resources/js/app.js` boots Alpine. Tailwind v4 is wired through `@tailwindcss/vite` (Vite plugin) — CSS lives in `resources/css/app.css`. Note: `resources/js/bootstrap.js` does **not** exist (removed in Laravel 11+); do not re-import it.

**Testing:** PHPUnit (not Pest). `phpunit.xml` overrides `DB_CONNECTION=sqlite` + `DB_DATABASE=:memory:` so feature tests don't touch MySQL. `BCRYPT_ROUNDS=4` in tests for speed. `Tests\TestCase` is the base class.

**Sessions/cache/queue** use the **database** driver by default (see `.env`) — these tables are part of the initial migrations (`0001_01_01_000001_create_cache_table.php`, `0001_01_01_000002_create_jobs_table.php`).

## Dev-only packages worth knowing

- `laravel/pail` — real-time log tailing (`php artisan pail`)
- `laravel/pao` — page rendering for browser-based testing helpers
- `laravel/pint` — opinionated code style fixer (use before committing)
- `nunomaduro/collision` — pretty CLI errors

## Role System

This project uses role-based skills to separate concerns. Activate a role before working:

| Command | Role | Scope |
|---------|------|-------|
| `/coder` | Developer | Full-stack: PHP, Blade, Tailwind, Alpine, tests, configs, migrations. |
| `/reviewer` | Reviewer | Code review by checklist. Read-only except lint/test. |
| `/consultant` | Consultant | Read-only: explanations, analysis. |

**Without an active role**: answer questions, read files, suggest approaches — but do not modify files or run state-changing commands. Recommend the appropriate role for the task.

## Git Safety

**Non-negotiable rules:**

1. **Commit to the current branch** — including `main`. Do not switch branches automatically.
2. **Create a new branch only on explicit request.** If a new branch is needed, suggest it and wait for confirmation.
3. **Before any git write operation** (commit, push, merge, rebase, tag) — ask for explicit approval.
4. **Never force-push** without explicit request and confirmation.
5. **Never amend published commits.**
6. **Commit messages**: concise, English, imperative mood. Prefix: `feat:`, `fix:`, `refactor:`, `docs:`, `chore:`, `test:`.
7. **If a new branch is requested**, naming convention: `feature/<name>`, `fix/<name>`, `refactor/<name>`, `docs/<name>`.

**Hosted on GitHub** (`github.com:mazurok86/amo-point`).

## Architectural Decision Protocol

For structural changes — check project guardrails first. If a change violates guardrails, state the violation explicitly and get human approval. No silent deviations.

## Documentation Maintenance

When making code changes, check if documentation needs updating:

| Change type | Docs to check |
|-------------|---------------|
| New/changed route or controller action | `routes/web.php` comments, API contract docs (when added) |
| New env variable | `.env.example`, this CLAUDE.md if architecturally significant |
| DB schema change | Migration files, model relationships |
| Architectural change | This CLAUDE.md (`Architecture notes`), `docs/` (when added) |
| New artisan command | `routes/console.php`, command's own `--help` description |
| New queue / scheduled job | `bootstrap/app.php` (`withSchedule`), this CLAUDE.md if it changes runtime topology |
