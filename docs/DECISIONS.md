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
