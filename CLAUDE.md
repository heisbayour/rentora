# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

Rentora is a rental property management system (landlord console, tenant portal PWA, public listings) built from `docs/BUILD_SPEC.md`. That spec is the source of truth; this file condenses its Sections 3, 4, 6, 12 and 13. Build state and the resume point live in `docs/PROGRESS.md`; every judgement call goes in `docs/DECISIONS.md`.

## Hard rules

- Never use the em dash character (U+2014) anywhere: code, comments, views, docs, seed data, commit messages. Use hyphens, commas, colons or full stops.
- Never commit secrets. `.env` is git-ignored; only `.env.example` is committed, and every new env key is documented there.
- Every phase ends with `composer test`, `composer lint` and `composer analyse` all passing, then `git commit -m "phase N: <summary>"` and `git push`.
- Code must run unchanged on InfinityFree (free) and cPanel (paid) hosting. Host differences are `.env` flags only (`RENTORA_SCHEDULER_MODE`, `RENTORA_PAYSTACK_WEBHOOKS`).
- Do not build anything in the spec's Section 18 roadmap.

## Stack (pinned)

PHP 8.2+ (local PHP is 8.4, but Composer `platform.php` is pinned to 8.2.0: no syntax or functions newer than 8.2). Laravel 12, Fortify with custom Blade views, MySQL 8 / MariaDB 10.4+ in production, SQLite in-memory for tests (so migrations must be portable: no vendor-specific SQL, no DB enums, `Schema::defaultStringLength(191)`). Blade + Alpine.js 3, Tailwind CSS 3.4 (not 4), Chart.js 4, Inter via `@fontsource-variable/inter`, `blade-ui-kit/blade-heroicons` (outline 24 for nav, mini 20 inline), `barryvdh/laravel-dompdf`, `chillerlan/php-qrcode` (GD output). Pest 3, Pint (laravel preset plus `declare_strict_types`), Larastan level 5. No runtime CDN: every asset is bundled by Vite.

## Hosting constraints that shape the code

- No SSH, Composer or artisan on the server: deploy is a pre-built folder (`composer deploy:build`); migrations run through the web installer/upgrader.
- No cron: the lazy scheduler runs due tasks in a terminable middleware after the response, guarded by an atomic DB lock and a time budget. `cron` mode instead uses `php artisan rentora:tick` or `/system/tick?key=`.
- No queue workers: `QUEUE_CONNECTION=sync`. Email and SMS go to the `outbound_messages` table, drained in small batches by the scheduler.
- No PHP `mail()`: SMTP only. No symlinks: public images go straight to `public/uploads`, private files to `storage/app/private` served by an authorizing controller.
- Non-browser requests are blocked on InfinityFree: no separate API, Paystack confirmed by redirect callback plus server-side verify; webhook route exists but is off by default.
- Never run `config:cache`, `route:cache` or `view:cache` in the deploy build.

## Architecture

- Thin controllers: Form Request validates, Policy authorizes, an Action or Service does the work, controller returns view or redirect. Business logic lives in `app/Actions` (invokable, single `handle()` or `__invoke()`) and `app/Services/{Billing,Payments,Messaging,Scheduling,Reports,Search}`.
- Controllers are split by area: `Http/Controllers/{Admin,Portal,Public,System}`. Routes: `/app/*` staff console, `/portal/*` tenant portal, `/` public site, `/install` and `/system/*` system.
- Money is integer minor units (kobo) in `BIGINT UNSIGNED`, never floats; use `App\Support\Money` for arithmetic and formatting (`₦1,250,000.00`).
- Every money write runs in `DB::transaction()` with `lockForUpdate()` on the rows changed. Financial and security actions write `activity_logs`.
- Every business table has `organization_id`; models use `Models\Concerns\BelongsToOrganization` (global scope plus auto-fill). Tenant isolation is enforced three times: that scope, Policies, and `tenant_id` scoping in the portal.
- Status and type columns are strings backed by PHP enums in `app/Enums`, each with `label()` and `color()` so `<x-status-chip :status="...">` needs no extra code.
- Times stored in UTC, shown in the org timezone (default `Africa/Lagos`). Dates display as `12 Nov 2026`.
- Gateway, SMTP and SMS credentials live encrypted in the `settings` table (env values as fallback); never echo a secret back in full (last 4 chars only). Never change `APP_KEY` after install.
- Product name strings come from `config('rentora.name')`; nothing else hard-codes "Rentora".

## Coding conventions

- `declare(strict_types=1);` in every PHP file.
- Form Requests for all input; never `$request->all()` into a model. Explicit `$fillable` everywhere.
- No business logic in Blade: controllers or view models prepare data.
- Eager load relationships; `Model::preventLazyLoading()` is on outside production.
- Read config through `config()`, never `env()` outside `config/`.
- External HTTP only through the Laravel HTTP client, so tests use `Http::fake()`.
- User-facing strings go in `lang/en/*.php`.
- Factories for every model.

## Design system

Calm, white, one accent. Tokens are in `tailwind.config.js`: `primary-{50,600,700}` are CSS variables (`--color-primary-*`, RGB triplets) so the org `primary_color` can override them; `canvas` page background, `surface` cards, `border`, `ink` and `ink-muted` text, and `success`/`warning`/`danger`/`info` each with a `-bg` variant. Radius `rounded-card` (12px) and `rounded-control` (8px), `shadow-card` on cards only, 8px spacing grid, type scale 12/14/16/20/24/32, `tabular-nums` on every money column.

Use the Blade components in `resources/views/components` (`x-button`, `x-input`, `x-money-input`, `x-table`, `x-status-chip`, `x-empty-state`, `x-modal`, `x-confirm` and the rest of spec 6.3) rather than raw markup. Layouts: `admin` (256px sidebar collapsible to a 72px rail), `portal` (mobile-first, bottom nav under 768px, 720px max width), `public`, `auth` (centred 400px card), `install`.

UX rules on every screen: list pages have search, filter chips, sort, 25 per page and an empty state with a primary action; destructive actions confirm and state consequences; forms keep input and show inline errors and disable submit while submitting; success shows a toast and redirects to the most useful next screen; visible 2px primary focus ring, labelled inputs, keyboard-operable menus and modals, `aria-live` toasts.

## Testing

- Pest tests live in `tests/Feature/{Module}` and `tests/Unit`. Feature tests get `RefreshDatabase` automatically (`tests/Pest.php`) on SQLite in-memory (`phpunit.xml`).
- Every acceptance criterion marked [T] in the spec needs a passing test. Unit tests cover `Money`, `PhoneNormalizer`, period calculation, allocation, the template renderer and the SMS segment counter.
- A role/route access matrix test iterates every named route under `/app` and `/portal`.
- Zero failures and zero skipped tests. Run a single test with `php vendor/bin/pest --filter "name"`.
- Before release, migrations must also be run on real MySQL (`php artisan migrate:fresh --seed`).

## Local environment quirks

- Local `.env` uses SQLite (`database/database.sqlite`). For MySQL checks use XAMPP's MariaDB (`C:\xampp\mysql\bin\mysqld.exe --defaults-file=C:\xampp\mysql\bin\my.ini --standalone`, root with no password, database `rentora`) and run artisan with `DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_DATABASE=rentora DB_USERNAME=root DB_PASSWORD=` overrides.
- If Composer fails with "Permission denied" writing `vendor/composer/tmp-*.zip`, run `composer clear-cache` and retry.
