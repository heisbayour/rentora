# Decisions

Judgement calls made while building from `docs/BUILD_SPEC.md`, newest last. Review these.

## Phase 0

1. **Composer platform pinned to PHP 8.2.0.** The local PHP is 8.4, which would let Composer pick dependencies (for example Symfony 8 components) that need PHP 8.4. Pinning `config.platform.php` keeps the lock file installable on 8.2 hosts, as Section 3 requires.
2. **Tailwind 3.4 replaces the skeleton's Tailwind 4.** Laravel 12's skeleton ships Tailwind 4 with `@tailwindcss/vite`; the spec pins 3.4, so the project uses the classic PostCSS setup (`tailwind.config.js`, `postcss.config.js`) plus `@tailwindcss/forms`.
3. **axios removed.** The app is server-rendered with Alpine and no API, so the skeleton's axios bootstrap was dropped.
4. **`laravel/sail` removed.** Docker is not part of either hosting target and Sail pulled in packages only resolvable on PHP 8.4.
5. **Primary colour as CSS variables.** `primary-50/600/700` are RGB-triplet CSS variables, so the org's `primary_color` can override them at runtime without recompiling Tailwind. The layout derives the 700 and 50 shades from the org colour.
6. **Pint adds `declare_strict_types`.** Section 12 requires strict types in all PHP files; making it a Pint rule means `composer lint` enforces it.
7. **Local development uses SQLite.** The local PHP has no `pdo_mysql` extension and no MySQL server is installed. Production `.env.example` defaults stay on MySQL. The Section 13 MySQL migration check is scheduled for Phase 13 and needs `pdo_mysql` enabled plus a MySQL or MariaDB server.
8. **Shared-hosting defaults in `.env.example`.** `SESSION_DRIVER=file`, `CACHE_STORE=file`, `QUEUE_CONNECTION=sync`, `LOG_CHANNEL=daily` with 14 days, matching Section 16.3.
9. **The spec is copied into the repo** as `docs/BUILD_SPEC.md` so a new session can resume from the repository alone.

## Phase 1

10. **Layouts are anonymous components in `resources/views/layouts`**, registered under the `layouts` namespace (`<x-layouts::admin>`), matching the spec's folder structure. All of them wrap `layouts/base`.
11. **The styleguide is reachable in `local` and `testing`**, not only `local`, so a feature test renders every component and layout on every run. It returns 404 in any other environment. The Phase 13 access-matrix test must skip `app.styleguide`.
12. **Layouts take a `brandColor` prop that falls back to `config('rentora.defaults.primary_color')`.** Phase 2 passes the organization's `primary_color` once organizations exist.
13. **Submit buttons are disabled by one global listener** (`resources/js/forms.js`) rather than per form, so every form meets the "disabled submit while submitting" rule by default. Forms opt out with `data-no-guard`.
14. **Admin navigation is a PHP definition** (`App\Support\AdminNavigation`) holding the role visibility rules; items whose routes do not exist yet render disabled. Caretakers see maintenance, units, tenants and vendors (read-only contact info, spec 1.2) and no money items.
15. **Alpine focus plugin (`@alpinejs/focus`) added** for focus trapping in modals, slide-overs and menus (spec 6.4 keyboard-operable overlays).
16. **No visual browser check in Phase 1.** The Chrome extension was not connected in this session; the components are covered by rendering tests only. A visual pass is part of `docs/SMOKE_TEST.md` in Phase 13.
