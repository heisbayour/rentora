# Build progress

Source: `docs/BUILD_SPEC.md`. Phases run in order (Section 15). Each phase ends with `composer test`, `composer lint`, `composer analyse` passing, this file updated, then commit `phase N: <summary>` and push. A new session resumes from the first unticked item.

## Phase 0 - Environment and scaffold

- [x] Tools verified: PHP 8.4.25, Composer 2.10.3, Node 25.9.0, npm 11.12.1, git 2.47.1, gh 2.98.0 (logged in as heisbayour)
- [x] Laravel 12 project created in `rentora/`
- [x] Packages installed: Fortify, dompdf, php-qrcode, blade-heroicons, Pest 3, Pest Laravel plugin, Larastan, Pint
- [x] Pest, Pint, Larastan configured; composer scripts `test`, `lint`, `lint:fix`, `analyse`, `deploy:build`
- [x] Tailwind 3.4, Alpine 3, Vite, Inter, Chart.js installed and `npm run build` passes
- [x] `CLAUDE.md`, `docs/PROGRESS.md`, `docs/DECISIONS.md`, `.env.example`, `config/rentora.php` (1.0.0)
- [x] `git init`, first commit, `gh repo create heisbayour/rentora --public` and push

## Phase 1 - Design system

- [x] Tailwind tokens (6.1) and org primary colour override
- [x] Form components: button, input, select, textarea, money-input, date-input, checkbox, toggle, file-dropzone (with client-side compression)
- [x] Display components: card, stat-card, status-chip, table (stacked under 768px), empty-state, avatar, timeline, skeleton, pagination, page-header, alert
- [x] Overlay and navigation components: modal, slide-over, stepper, tabs, dropdown, toast, confirm
- [x] Layouts: admin, portal, public, auth (plus install)
- [x] `/app/_styleguide` route (local only) rendering every component
- [x] Gates pass, commit, push

## Phase 2 - Organization, auth, roles

- [x] Core migrations: organizations, users, settings, sequences, system_state, activity_logs
- [x] `BelongsToOrganization` trait and `OrganizationScope`
- [x] Fortify with custom views: login, logout, forgot/reset, confirm password, TOTP 2FA for owner/manager
- [x] Role middleware, active-user check, login throttle 5/min per email+IP, role-based redirect after login
- [x] Policies scaffolding
- [x] Staff management (invite, role, deactivate) and profile page
- [x] Sequence service (INV/RCT/MR numbers with row lock)
- [x] Activity logging service
- [x] Settings service with encryption
- [x] Installer (`/install`) and upgrader (`/system/upgrade`)
- [x] First-run wizard (organization step; property, units and tenant steps are added in Phases 3 and 4)
- [x] Tests: 8.1 access and throttle; 8.16 installer and upgrade guards (caretaker-vs-money test lands with the money routes in Phase 5)
- [x] Gates pass, commit, push

## Phase 3 - Properties and units

- [ ] Property CRUD with cover image, detail page tabs
- [ ] Unit CRUD, photos (reorder, delete), status, listing toggle
- [ ] Quick-add up to 50 units
- [ ] Delete blocked with active lease, soft delete otherwise
- [ ] Documents module and `FileController`
- [ ] Tests: 8.3 criteria
- [ ] First-run wizard steps: first property and quick-add units
- [ ] Gates pass, commit, push

## Phase 4 - Tenants and leases

- [ ] Tenant CRUD and profile tabs
- [ ] Invite flow (signed 7-day link, email/SMS, resend)
- [ ] Lease wizard (6.5.2)
- [ ] Activate, renew, end, terminate; unit status sync
- [ ] Lease agreement document upload
- [ ] First-run wizard step: invite first tenant (skippable)
- [ ] Tests: 8.4 criteria and 8.3 status sync
- [ ] Gates pass, commit, push

## Phase 5 - Billing

- [ ] `Money` value object
- [ ] Period calculator (frequency, anchor day, month ends, leap years)
- [ ] Invoice generator (idempotent) and first invoices at activation
- [ ] Manual invoices and void
- [ ] Late fees
- [ ] Credit balance application
- [ ] Invoice detail page and PDF
- [ ] Tests: 8.5 criteria; unit tests for Money and periods; caretaker cannot access money routes
- [ ] Gates pass, commit, push

## Phase 6 - Payments

- [ ] `PaymentGateway` interface and Paystack gateway
- [ ] Initialize, callback, webhook (gated), reconcile, check status
- [ ] Transfer proofs (submit, approve, reject)
- [ ] Staff-recorded payments
- [ ] Allocation service and reversal
- [ ] Receipt PDF with QR, public verify page
- [ ] Statements (screen and PDF)
- [ ] Tests: 8.6 criteria; unit test for allocation
- [ ] Gates pass, commit, push

## Phase 7 - Maintenance and vendors

- [ ] Vendors CRUD
- [ ] Requests, transitions, thread with internal notes, attachments
- [ ] Assignment, vendor signed link (14 days)
- [ ] SLA indicators, emergency handling
- [ ] Kanban and table views
- [ ] Rating and auto-close; cost to expense link
- [ ] Tests: 8.7 criteria
- [ ] Gates pass, commit, push

## Phase 8 - Messaging and scheduler

- [ ] Outbox table, email and SMS channels, drain rules
- [ ] SMS drivers: log, termii, twilio; phone normalizer; segment counter
- [ ] Message templates and editor with preview
- [ ] Notification classes for every event in 9.3
- [ ] Announcements and direct messages
- [ ] WhatsApp click-to-send buttons
- [ ] In-app inbox
- [ ] Lazy scheduler, task registry, all tasks in 10.1, `rentora:tick`, `/system/tick`
- [ ] Tests: 8.8 and 10.2 criteria; unit tests for normalizer, renderer, segment counter
- [ ] Gates pass, commit, push

## Phase 9 - Tenant portal and PWA

- [ ] Portal screens per 8.13
- [ ] Payment wizard and repair wizard
- [ ] Notices
- [ ] Manifests, service worker, offline page, install prompt
- [ ] Gates pass, commit, push

## Phase 10 - Dashboard, reports, expenses, search, audit

- [ ] Dashboard KPIs, charts, lists (caretaker variant)
- [ ] Expenses CRUD
- [ ] Six reports with CSV and PDF
- [ ] Global search command palette
- [ ] Audit log
- [ ] Tests: 8.2 KPIs, 8.10 arrears aging
- [ ] Gates pass, commit, push

## Phase 11 - Public website

- [ ] Home, listings, unit page, inquiry form
- [ ] Inquiry admin inbox
- [ ] SEO, sitemap.xml, robots.txt, site toggle
- [ ] Gates pass, commit, push

## Phase 12 - Settings and system health

- [ ] All settings tabs with test buttons
- [ ] System health page
- [ ] Gates pass, commit, push

## Phase 13 - Hardening and release

- [ ] Security headers, rate limits
- [ ] Access matrix test, cross-tenant and cross-organization tests
- [ ] Migrations run on MySQL
- [ ] Demo seeder (Section 14)
- [ ] `scripts/build-deploy.php` and `deploy/` templates
- [ ] README, DEPLOYMENT, USER_GUIDE, SMOKE_TEST, CHANGELOG
- [ ] Final gates, tag `v1.0.0`, push tags

## Section 17 - Final verification

- [ ] All phases complete, all [T] criteria covered by passing tests
- [ ] `composer test`, `composer lint`, `composer analyse` pass with zero failures
- [ ] Migrations verified on MySQL
- [ ] Seeded demo works for all four demo logins
- [ ] No em dash character anywhere in repo
- [ ] No secrets in git history
- [ ] `composer deploy:build` zip installs and runs via `/install` on a fresh Apache folder and DB
- [ ] README, DEPLOYMENT, USER_GUIDE, SMOKE_TEST, CHANGELOG written
- [ ] Repo public at `github.com/heisbayour/rentora`, tagged `v1.0.0`
