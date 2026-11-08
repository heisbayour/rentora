# RENTORA - Rental Property Management System
## Complete Build Specification for Claude Code

> Product name "Rentora" is a working name. To rename, change `APP_NAME` in `.env` and the strings in `config/rentora.php`. Nothing else is hard-coded.

---

## 0. INSTRUCTIONS TO CLAUDE CODE (READ FIRST)

You are building a production-grade rental property management system from this document. Follow these rules for the entire session:

1. Read this whole file before writing any code.
2. Build in the exact phase order in Section 15. Do not skip phases. Do not stop between phases to ask for confirmation.
3. Only ask the user a question if you are truly blocked (missing local tool, failed GitHub auth). Otherwise make the most reasonable decision, record it in `docs/DECISIONS.md`, and continue.
4. Maintain `docs/PROGRESS.md` as a checklist of every phase and sub-task. Tick items as they are completed. If the session is interrupted, a new session must be able to resume from this file alone.
5. At the end of every phase: run `composer test`, `composer lint`, and `composer analyse`. All must pass before committing. Then `git commit` with message `phase N: <summary>` and `git push`.
6. Never commit secrets. `.env` is git-ignored. Only `.env.example` is committed.
7. Never use the em dash character (U+2014) anywhere in code, comments, views, docs, seed data or commit messages. Use hyphens, commas, colons or full stops.
8. Every feature in Section 8 is mandatory. "Acceptance criteria" lines are the definition of done and must be covered by automated tests where marked [T].
9. The code must run unchanged on both free hosting (InfinityFree) and paid shared hosting (cPanel). All host differences are controlled by `.env` flags described in Section 4.
10. Create `CLAUDE.md` in the repo root at Phase 0 containing a condensed version of Sections 3, 4, 6, 12 and 13 so future sessions keep the same conventions.

---

## 1. PRODUCT OVERVIEW

Rentora lets a landlord (or property management company) run rental properties end to end: properties, units, tenants, leases, rent billing, online and offline payments, receipts, repair requests, notifications, reports and a public listings website. Tenants get a mobile-first portal (installable as an app via PWA) to pay rent, download receipts, log repairs and receive notices.

### 1.1 Client brief mapped to features

| Client requirement | Delivered by |
|---|---|
| App to manage rental properties | Landlord console (Sections 8.2 to 8.12) + installable PWA (8.14) |
| Tenant access for payment | Tenant portal with Paystack online payment and bank-transfer proof upload (8.6, 8.13) |
| Tenants log repair requests | Maintenance module with photos, priority, live status timeline (8.7) |
| Landlord manages website or app | Settings, branding, public website editor, listings (8.11, 8.12) |
| Record tenant payment history | Ledger, invoices, payments, allocations, statements, receipts (8.5, 8.6) |
| Notify tenants via app, email, phone, website | Notification engine: in-app, email (SMTP), SMS (Termii/Twilio), WhatsApp click-to-send, announcements (8.8) |
| "Etc." | Leases, automated reminders, late fees, vendors, expenses, reports, audit log, global search, system health (8.4, 8.9, 8.10, 8.15) |

### 1.2 Roles

| Role | Who | Access |
|---|---|---|
| `owner` | Landlord / company admin | Everything, including settings, staff, gateway keys |
| `manager` | Property manager staff | Everything except settings, staff management, system health |
| `caretaker` | On-site caretaker | Maintenance module only, read-only unit and tenant contact info |
| `tenant` | Tenant | Own portal only: own lease, invoices, payments, requests, documents, notices |
| Vendor (no account) | External artisan (plumber, electrician) | One maintenance job via expiring signed link |

### 1.3 Multi-organization ready

Every business table has `organization_id`. The default install runs in single-organization mode (`RENTORA_MULTI_ORG=false`). This makes the codebase resellable as SaaS later without schema changes.

---

## 2. HOSTING REALITY (CRITICAL, DRIVES ARCHITECTURE)

The user may deploy on InfinityFree. These are hard constraints of that host and the architecture must work within them:

| InfinityFree constraint | Architectural response |
|---|---|
| MySQL only accepts connections from InfinityFree's own servers (no remote DB) | App and DB must live on the same hosting account. Do not design a split frontend/DB setup. |
| A JavaScript browser-check security system blocks non-browser requests (API clients, webhooks, external cron pingers) | No separate mobile API. The "app" is a PWA served from the same domain. Paystack is confirmed via browser redirect callback plus server-side verify. Webhook route exists but is toggled off. |
| No SSH, no Composer, no artisan on server | Deploy a pre-built folder (vendor and compiled assets included). Migrations run through a protected web installer/upgrader (8.16). |
| No cron jobs | "Lazy scheduler" runs due tasks after responses are sent, with a DB lock and time budget (Section 10). Real cron supported on paid hosting via flag. |
| PHP `mail()` disabled | Email via SMTP only (Brevo, Gmail app password, Zoho, or host SMTP). |
| No symlinks (`storage:link` impossible) | Public images saved directly into `public/uploads`. Private files in `storage/app/private`, streamed through an authorized controller. |
| No long-running workers | `QUEUE_CONNECTION=sync`. Outbound messages go to an `outbound_messages` table drained in small batches by the lazy scheduler. |
| Execution time and file count limits | Batches capped (10 messages, 8 second budget per tick). Deploy excludes tests, node_modules, docs. |

### 2.1 Recommendation recorded for the user

InfinityFree is acceptable for demo and client review. For production with real money, use paid shared cPanel hosting (cron, webhooks, reliable SMTP, no suspension risk). Switching requires only `.env` changes:

```
RENTORA_SCHEDULER_MODE=lazy        # lazy | cron
RENTORA_PAYSTACK_WEBHOOKS=false    # true on paid hosting
```

---

## 3. TECH STACK (PINNED)

| Layer | Choice | Notes |
|---|---|---|
| Language | PHP 8.2+ (target 8.3) | Must not use features beyond 8.2 |
| Framework | Laravel 12.x | |
| Auth | `laravel/fortify` with custom Blade views | Login, logout, password reset, password confirm, optional 2FA (TOTP) for owner/manager |
| Database | MySQL 8 / MariaDB 10.4+ | `Schema::defaultStringLength(191)`; no DB-vendor-specific SQL |
| Test DB | SQLite in-memory | Migrations must be portable |
| Views | Blade + Blade components | Server-rendered, no SPA |
| Interactivity | Alpine.js 3 | Bundled via Vite |
| CSS | Tailwind CSS 3.4 | Compiled locally, `public/build` shipped in deploy |
| Charts | Chart.js 4 | Bundled, no CDN |
| Font | Inter via `@fontsource-variable/inter` | Self-hosted, no Google CDN dependency |
| Icons | `blade-ui-kit/blade-heroicons` | Outline 24 for nav, mini 20 for inline |
| PDF | `barryvdh/laravel-dompdf` | Receipts, statements, reports |
| QR codes | `chillerlan/php-qrcode` | Receipt verification QR (GD output) |
| Payments | Paystack REST API via Laravel HTTP client | Behind `PaymentGateway` interface |
| SMS | Driver interface: `log` (default), `termii`, `twilio` | |
| Tests | Pest 3 | |
| Lint | Laravel Pint (preset laravel) | |
| Static analysis | Larastan level 5 | |

Composer scripts required in `composer.json`:

```json
"scripts": {
  "test": "pest",
  "lint": "pint --test",
  "lint:fix": "pint",
  "analyse": "phpstan analyse --memory-limit=1G",
  "deploy:build": "php scripts/build-deploy.php"
}
```

Required PHP extensions (checked by installer): `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `json`, `fileinfo`, `gd`, `curl`, `bcmath`.

---

## 4. ARCHITECTURE

### 4.1 Principles

- Thin controllers. Business logic lives in `app/Services` and `app/Actions`. Controllers validate (Form Requests), authorize (Policies), call an action, return a view or redirect.
- All money stored as integers in minor units (kobo for NGN) in `BIGINT UNSIGNED` columns. Never floats. A `Money` value object handles formatting and arithmetic.
- All writes that touch money run inside `DB::transaction()` with `lockForUpdate()` on the rows being changed.
- Every financial or security-relevant action writes to `activity_logs`.
- Tenant isolation enforced at three layers: global `OrganizationScope`, Policies, and query scoping by `tenant_id` in the portal.
- Times stored in UTC, displayed in org timezone (default `Africa/Lagos`).

### 4.2 Folder structure

```
app/
  Actions/            # single-purpose operations (RecordPayment, IssueInvoice, ...)
  Enums/              # backed enums for every status/type column
  Http/
    Controllers/
      Admin/          # landlord console (/app)
      Portal/         # tenant portal (/portal)
      Public/         # website, listings, receipt verify, vendor job link
      System/         # installer, upgrader, tick endpoint, webhooks
    Middleware/
    Requests/
  Models/
    Concerns/BelongsToOrganization.php
  Notifications/      # one class per event (Section 9.3)
  Policies/
  Services/
    Billing/          # InvoiceGenerator, LateFeeService, AllocationService
    Payments/         # PaymentGateway interface, PaystackGateway, PaymentVerifier
    Messaging/        # Outbox, EmailChannel, SmsManager, drivers, TemplateRenderer
    Scheduling/       # LazyScheduler, TaskRegistry, tasks
    Reports/
    Search/
  Support/Money.php
  View/Components/
config/rentora.php
resources/views/
  components/         # design-system Blade components
  layouts/            # admin, portal, public, auth, install
  admin/ portal/ public/ auth/ install/ pdf/ emails/
scripts/build-deploy.php
deploy/               # templates: root .htaccess, deny .htaccess, .env.production.example
docs/                 # PROGRESS.md, DECISIONS.md, DEPLOYMENT.md, USER_GUIDE.md
```

### 4.3 Route map

| Prefix | Middleware | Purpose |
|---|---|---|
| `/` | web | Public site: home, listings, unit detail, inquiry, login links |
| `/login`, `/forgot-password`, ... | guest | Fortify auth |
| `/app/*` | auth, role:owner,manager,caretaker, active | Landlord console |
| `/portal/*` | auth, role:tenant, active | Tenant portal |
| `/invite/{tenant}` | signed | Tenant account activation |
| `/vendor/job/{request}` | signed | Vendor job view and status update |
| `/r/{code}` | throttle | Public receipt verification |
| `/pay/callback` | auth, role:tenant | Paystack redirect callback |
| `/webhooks/paystack` | no CSRF, `RENTORA_PAYSTACK_WEBHOOKS` gate | Paystack webhook |
| `/system/tick` | `key` query param must equal `RENTORA_TICK_KEY` | External cron trigger |
| `/install`, `/system/upgrade` | installer guards (8.16) | Web installer and upgrader |

After login: owner/manager/caretaker redirect to `/app`, tenant to `/portal`.

### 4.4 `.env` keys specific to Rentora (all documented in `.env.example`)

```
RENTORA_MULTI_ORG=false
RENTORA_SCHEDULER_MODE=lazy
RENTORA_TICK_KEY=change-me-long-random
RENTORA_INSTALL_KEY=change-me-long-random
RENTORA_PAYSTACK_WEBHOOKS=false
RENTORA_OUTBOX_BATCH=10
RENTORA_TICK_BUDGET_SECONDS=8
RENTORA_TICK_INTERVAL_MINUTES=5
SMS_DRIVER=log
```

Gateway keys, SMTP and SMS credentials are entered in Settings by the owner and stored encrypted in the `settings` table (8.12), with `.env` values as fallback.

---

## 5. DATA MODEL

All tables have `id` (BIGINT), `created_at`, `updated_at` unless noted. `org` = `organization_id` FK with index. Status/type columns use string columns backed by PHP enums (not DB enums, for portability). Soft deletes where marked (SD).

### 5.1 Core

**organizations**: name, slug (unique), logo_path, primary_color (default `#1A73E8`), currency (default `NGN`), timezone (default `Africa/Lagos`), email, phone, address, website_headline, website_about.

**users**: org, name, email (unique), phone, password, role, is_active (bool), last_login_at, two_factor columns (Fortify), email_verified_at, remember_token. A tenant user links to `tenants.user_id`.

**settings**: org, key, value (text), is_encrypted (bool). Unique (org, key).

**sequences**: org, key (`invoice`, `receipt`, `maintenance`), year, next_value. Unique (org, key, year). Numbers generated with row lock. Formats: `INV-2026-000001`, `RCT-2026-000001`, `MR-2026-000001`.

**system_state**: key (unique), value, updated_at. Used for scheduler lock and last-run times.

**activity_logs**: org, user_id (nullable), action (string, e.g. `payment.approved`), subject_type, subject_id, properties (json), ip, user_agent, created_at only.

### 5.2 Property

**properties** (SD): org, name, code (unique per org), type (`residential|commercial|mixed`), address, city, state, country (default `Nigeria`), description, cover_image_path, is_listed (bool).

**units** (SD): org, property_id, label (e.g. "Flat 3B"), type (`flat|self_contain|room|duplex|bungalow|shop|office|warehouse|other`), bedrooms, bathrooms, size_sqm (nullable), rent_amount, rent_frequency (`monthly|quarterly|biannual|annual`), service_charge_amount, caution_deposit_amount, status (`vacant|occupied|reserved|under_maintenance`), is_listed (bool), description. Unique (property_id, label).

**unit_photos**: unit_id, path, sort_order.

### 5.3 People and leases

**tenants** (SD): org, user_id (nullable), full_name, email (nullable), phone, alt_phone, occupation, employer, id_type, id_number, emergency_contact_name, emergency_contact_phone, notes, status (`pending_invite|active|former`), invite_sent_at, notification_prefs (json: `{"sms":true,"email":true}`), credit_balance (BIGINT, default 0).

**leases**: org, unit_id, tenant_id, start_date, end_date, rent_amount, rent_frequency, service_charge_amount, deposit_amount, deposit_status (`unpaid|held|refunded|forfeited`), billing_anchor_day (1 to 28), invoice_lead_days (default: monthly 7, quarterly 14, biannual 21, annual 30), grace_days (default 5), late_fee_type (`none|fixed|percent`), late_fee_value, status (`draft|active|ended|terminated`), terminated_at, termination_reason. Business rule: at most one `active` lease per unit (enforced in action plus test).

**vendors**: org, name, trade, phone, email, notes, is_active.

### 5.4 Billing

**invoices**: org, number (unique per org), lease_id, tenant_id, unit_id, type (`rent|service_charge|deposit|late_fee|utility|other`), description, period_start, period_end (nullable), amount, amount_paid, due_date, status (`unpaid|partial|paid|void`), voided_at, void_reason, parent_invoice_id (for late fees), issued_at. Index (tenant_id, status), (due_date, status). Unique (lease_id, type, period_start) to make generation idempotent.

**payments**: org, tenant_id, lease_id (nullable), reference (unique), receipt_number (nullable, unique), verification_code (unique, 10 char), amount, method (`paystack|bank_transfer|cash|pos|cheque|other`), status (`pending|successful|failed|rejected`), gateway, gateway_reference, gateway_payload (json), proof_path, paid_at, recorded_by, approved_by, approved_at, rejection_reason, note, last_verified_at.

**payment_allocations**: payment_id, invoice_id, amount. Unique (payment_id, invoice_id).

**expenses**: org, property_id, unit_id (nullable), maintenance_request_id (nullable), category (`repairs|utilities|cleaning|security|taxes|insurance|salaries|other`), amount, incurred_on, description, receipt_path, recorded_by.

**reminder_logs**: invoice_id, kind (`pre_due_7|pre_due_3|due_today|overdue_3|overdue_7|overdue_14`), sent_at. Unique (invoice_id, kind).

### 5.5 Maintenance

**maintenance_requests**: org, number, unit_id, tenant_id (nullable), reported_by_user_id, category (`plumbing|electrical|carpentry|painting|appliance|roofing|security|pest_control|cleaning|other`), title, description, priority (`low|normal|high|emergency`), status (`submitted|acknowledged|scheduled|in_progress|on_hold|resolved|closed|cancelled`), assigned_user_id, vendor_id, scheduled_for, preferred_time, access_instructions, resolved_at, closed_at, cost_amount, tenant_rating (1 to 5), tenant_feedback, first_response_at.

**maintenance_attachments**: maintenance_request_id, path, uploaded_by_user_id (nullable), uploaded_by_vendor (bool).

**maintenance_updates**: maintenance_request_id, user_id (nullable), vendor_id (nullable), type (`comment|status_change|assignment|schedule`), body, old_status, new_status, is_internal (bool).

### 5.6 Communication and content

**announcements**: org, title, body, audience (`all_tenants|property|units|tenants`), audience_ids (json), channels (json: subset of `in_app,email,sms`), sent_at, recipient_count, created_by.

**notifications**: Laravel default database notifications table (in-app inbox).

**outbound_messages**: org, channel (`email|sms`), recipient, recipient_name, subject (nullable), body (text), html_body (nullable), status (`queued|sending|sent|failed`), attempts, last_error, related_type, related_id, send_after, sent_at. Index (status, send_after).

**message_templates**: org, event_key, channel, subject, body, is_active. Unique (org, event_key, channel). Seeded defaults.

**documents**: org, documentable_type, documentable_id, name, path, mime, size, visibility (`internal|tenant`), uploaded_by.

**inquiries**: org, unit_id (nullable), name, email, phone, message, status (`new|contacted|closed`), ip.

---

## 6. DESIGN SYSTEM

Goal: Google-grade clarity. Calm, white, generous spacing, one accent color, information first.

### 6.1 Tokens (Tailwind theme extension)

| Token | Value | Use |
|---|---|---|
| primary-600 | `#1A73E8` | Primary buttons, links, active nav (overridable by org `primary_color` via CSS variable) |
| primary-700 | `#1765CC` | Hover |
| primary-50 | `#E8F0FE` | Selected rows, active nav background |
| surface | `#FFFFFF` | Cards, sheets |
| canvas | `#F8F9FA` | Page background |
| border | `#E3E6EA` | Dividers, inputs |
| text | `#202124` | Body |
| text-muted | `#5F6368` | Secondary |
| success | `#188038` / bg `#E6F4EA` | Paid, resolved |
| warning | `#B06000` / bg `#FEF7E0` | Partial, pending, due soon |
| danger | `#D93025` / bg `#FCE8E6` | Overdue, failed, emergency |
| info | `#1967D2` / bg `#E8F0FE` | Scheduled, in progress |

Radius: cards 12px, inputs and buttons 8px, chips full. Shadow: `0 1px 2px rgba(60,64,67,.15), 0 1px 3px 1px rgba(60,64,67,.08)` on cards only. Spacing on an 8px grid. Type scale (Inter): 12, 14 (body), 16, 20, 24, 32. Weights 400, 500, 600. Tabular numbers (`font-variant-numeric: tabular-nums`) for all money columns.

### 6.2 Layouts

- **Admin (`/app`)**: left sidebar 256px (collapsible to 72px icon rail, state remembered in localStorage), top bar with global search (Ctrl/Cmd+K), notification bell with unread count, user menu. Sidebar groups: Overview (Dashboard), Portfolio (Properties, Units, Listings), People (Tenants, Leases, Vendors), Money (Invoices, Payments, Expenses), Operations (Maintenance, Announcements, Inbox), Insights (Reports), Admin (Staff, Settings, Message Templates, Audit Log, System Health). Items hidden by role.
- **Portal (`/portal`)**: mobile-first. Bottom navigation on screens under 768px: Home, Pay, Repairs, Inbox, Account. Top navigation on desktop. Max content width 720px.
- **Public**: clean marketing header, hero with org headline, listings grid, footer with contact.
- **Auth**: centered 400px card on canvas background, org logo above.

### 6.3 Blade component library (build in Phase 1, use everywhere)

`x-button` (variants: primary, secondary, ghost, danger; sizes sm, md; loading state), `x-input`, `x-select`, `x-textarea`, `x-money-input` (formats with thousands separators, submits minor units via hidden field), `x-date-input`, `x-checkbox`, `x-toggle`, `x-file-dropzone` (drag and drop, previews, client-side image compression to max 1600px JPEG 0.8 before upload), `x-card`, `x-stat-card` (label, value, delta, icon), `x-status-chip` (auto color from enum), `x-table` (sticky header, responsive: becomes stacked cards under 768px), `x-empty-state` (icon, title, text, CTA), `x-modal`, `x-slide-over`, `x-stepper` (wizard progress), `x-tabs`, `x-dropdown`, `x-toast` (session flash driven), `x-alert`, `x-avatar` (initials fallback), `x-timeline`, `x-skeleton`, `x-pagination`, `x-page-header` (title, breadcrumbs, actions), `x-confirm` (typed confirmation for destructive actions).

Every enum implements `label()` and `color()` so `x-status-chip :status="$invoice->status"` works with no extra code.

### 6.4 UX rules

- Every list page: search box, filters as chips, sort, pagination (25 per page), empty state with a primary action.
- Every destructive action: confirmation modal stating consequences.
- Every form: inline validation messages, preserved input on error, disabled submit while submitting.
- Every success: toast plus redirect to the most useful next screen.
- Money displayed as `₦1,250,000.00` using org currency symbol. Dates as `12 Nov 2026`. Relative times ("2 hours ago") only in activity feeds.
- Accessibility: WCAG AA contrast, visible focus ring (2px primary), all inputs labelled, keyboard-operable modals and menus, `aria-live` on toasts.
- Loading: skeletons on dashboard charts, spinners on buttons.

### 6.5 Guided wizards (step-by-step flows)

1. **First-run setup** (after install): Organization details and logo, then first property, then units (add several in a quick-add table), then invite first tenant (skippable). Progress stored so it resumes.
2. **Add tenant and lease**: Tenant details, then select unit (only vacant/reserved), then lease terms (prefilled from unit rent, editable), then deposit and first invoice options, then review and confirm (option to send invite now).
3. **Tenant repair request**: Category (icon grid), then details and photos, then access and preferred time, then review and submit.
4. **Tenant payment**: Select invoices to pay (oldest preselected) or enter custom amount, then choose method (card/bank via Paystack, or "I paid by transfer"), then confirm.

---

## 7. NON-FUNCTIONAL REQUIREMENTS

- Page server response under 300ms locally for lists of 1,000 rows (eager loading, indexes, no N+1; add `Model::preventLazyLoading()` in non-production).
- Works on latest Chrome, Safari, Firefox, Edge, and Android Chrome on a 360px wide screen.
- No external CDN dependencies at runtime (all assets bundled), except Paystack checkout redirect.
- All user-facing strings in `lang/en/*.php` to enable translation later.
- Logs: daily channel, 14 days retention.

---

## 8. FEATURE SPECIFICATION

### 8.1 Authentication and accounts
- Email + password login, remember me, forgot/reset password by email, logout.
- Login throttling: 5 attempts per minute per email+IP.
- Inactive users cannot log in (clear message).
- Optional TOTP 2FA for owner and manager (Fortify), with recovery codes.
- Profile page: name, phone, password change, notification preferences (tenant).
- Acceptance: [T] tenant cannot access `/app`, staff cannot access `/portal`, caretaker cannot access money routes, inactive user is rejected, throttle triggers.

### 8.2 Dashboard (landlord)
- KPI cards: Expected this month, Collected this month, Collection rate %, Total arrears, Occupancy % (occupied/total units), Vacant units, Open repairs (with emergency count), Leases expiring in 60 days.
- Charts: Collections last 12 months (bar: expected vs collected), Arrears aging donut (0 to 30, 31 to 60, 61 to 90, 90+ days).
- Lists: Recent payments (5), Pending payment proofs awaiting approval (with Approve/Reject quick actions), Newest repair requests (5), Upcoming due invoices (7 days).
- Caretaker sees only maintenance widgets.
- Acceptance: [T] KPI calculations correct against seeded fixture data.

### 8.3 Properties and units
- CRUD properties with cover image. Property detail page: units table, occupancy, income this year, open repairs, documents tab.
- CRUD units, multiple photos (reorder, delete), status, listing toggle, rent defaults.
- Quick-add units: table form to add up to 50 units at once (label, type, rent, frequency).
- Cannot delete a unit or property with an active lease (clear message). Soft delete otherwise.
- Acceptance: [T] delete blocked with active lease; [T] unit status auto-changes to `occupied` when lease activates and `vacant` when lease ends or terminates.

### 8.4 Tenants and leases
- Tenant CRUD, profile page with tabs: Overview (balance, lease, contacts), Invoices, Payments, Maintenance, Documents, Messages (log of all outbound messages to this tenant), Activity.
- Invite: sends email and/or SMS containing a signed link valid 7 days to set password. Resend invite action. Tenant without email can be invited by SMS.
- Lease creation through wizard 6.5.2. Activate, renew (creates a new lease starting day after end date with editable rent), end (on end date), terminate early (reason required, optional final invoice).
- Lease expiry alerts at 60 and 30 days to owner/manager and tenant.
- Lease agreement upload (PDF) as a document visible to tenant.
- Former tenants remain in history with full ledger.
- Acceptance: [T] cannot create second active lease on a unit; [T] invite link expires; [T] renew creates correct dates.

### 8.5 Billing (invoices)
- Automatic generation: daily task creates the next rent invoice (and service charge invoice if amount > 0) for every active lease when today >= next period start minus `invoice_lead_days`. Period length by frequency (1, 3, 6, 12 months), anchored to `billing_anchor_day`. Idempotent via unique key.
- First invoice(s) optionally created at lease activation (rent for first period, deposit).
- Manual invoices: utility, other, with description.
- Void invoice (reason required, only if no allocations; otherwise must refund/reallocate first).
- Late fees: daily task, after `due_date + grace_days`, if lease has late fee configured and invoice still unpaid/partial, create one `late_fee` invoice linked by `parent_invoice_id`. Never more than one per parent invoice.
- Tenant credit balance auto-applied to new invoices at creation.
- Invoice detail page and PDF download.
- Acceptance: [T] monthly, quarterly, annual periods generate correctly across month ends and leap years; [T] running generation twice creates no duplicates; [T] late fee created once only; [T] credit balance applied.

### 8.6 Payments, receipts and statements
**Online (Paystack):**
1. Tenant chooses invoices/amount (minimum ₦100; cannot exceed outstanding unless `allow_overpayment` setting is on).
2. Server creates `pending` payment with unique reference `RNT-{ulid}`, calls `POST /transaction/initialize` with amount in kobo, email (tenant email or generated `tenant{id}@{domain}` placeholder if none), reference, `callback_url = route('pay.callback')`, metadata (payment_id, tenant_id, invoice_ids).
3. Redirect to `authorization_url`.
4. Callback: call `GET /transaction/verify/{reference}`. Mark successful only if `data.status == success`, amount matches exactly, currency matches. Otherwise failed with reason.
5. Processing is idempotent: lock payment row; if already successful, just show receipt.
6. Webhook `/webhooks/paystack` (when enabled): verify `x-paystack-signature` HMAC SHA512 of raw body with secret key, then run the same `PaymentVerifier`.
7. Reconciliation task every 10 minutes: re-verify `pending` Paystack payments between 10 minutes and 48 hours old. After 48 hours still pending, mark failed. Tenant and admin also have a "Check status" button.

**Bank transfer proof (tenant):** amount, date paid, bank/sender name, proof upload (image or PDF). Creates `pending` payment. Owner/manager approves (allocation runs, receipt issued) or rejects with reason (tenant notified). Org bank account details shown on the Pay screen (from settings).

**Recorded by staff:** cash, POS, cheque, transfer. Immediately successful. Optional proof upload.

**Allocation:** default oldest due invoice first; tenant or staff can target specific invoices. Remainder after all outstanding becomes tenant `credit_balance`. Invoice `amount_paid` and status updated atomically.

**Reversal:** owner only, can reverse a successful manual payment (reason required): deletes allocations, restores invoice balances, marks payment `rejected`, logs activity. Paystack payments cannot be reversed in-app (refunds handled in Paystack dashboard; record a negative adjustment invoice if needed).

**Receipts:** receipt number issued on success. PDF with org branding, tenant, unit, amount in figures and words, method, allocations table, verification QR pointing to `/r/{verification_code}`. Public verification page shows: valid/invalid, receipt number, amount, date, masked tenant name (e.g. "Ad*** Ol***"), org name.

**Statements:** tenant statement for any date range (opening balance, invoices, payments, running balance, closing balance) as screen view and PDF. Available to staff and to the tenant for themselves.

**Acceptance:** [T] verify success path; [T] amount mismatch marks failed; [T] duplicate callback processed once; [T] invalid webhook signature rejected 401; [T] allocation oldest-first, partial, overpayment to credit; [T] proof approve and reject flows; [T] reversal restores balances; [T] receipt verify page shows masked data and returns 404-style "not found" for bad codes; [T] tenant cannot view another tenant's receipt.

### 8.7 Maintenance (repair requests)
- Tenant submits via wizard 6.5.3 (up to 5 photos). Staff can also log requests on behalf of a tenant or for a vacant unit.
- Statuses and allowed transitions:
  - submitted -> acknowledged, cancelled
  - acknowledged -> scheduled, in_progress, on_hold, cancelled
  - scheduled -> in_progress, on_hold, cancelled
  - in_progress -> resolved, on_hold
  - on_hold -> in_progress, scheduled, cancelled
  - resolved -> closed, in_progress (reopen)
  - Tenant can cancel only while `submitted`. Tenant can confirm `resolved` -> `closed` and rate 1 to 5 with feedback. Auto-close resolved requests after 7 days.
- Assign to staff user and/or vendor. Assigning a vendor sends SMS/email with a signed link (valid 14 days) where vendor can view job details, unit address, tenant contact (if setting allows), post updates, upload photos, mark in_progress/resolved.
- Thread: comments with `is_internal` flag (internal notes never visible to tenant).
- SLA indicators: time since submitted, first response time. Emergency requests highlighted red and notify owner/manager by SMS immediately.
- Cost recording on resolve creates an expense linked to the request (optional).
- Kanban view (columns by status) and table view for staff.
- Acceptance: [T] invalid transitions rejected; [T] internal comments hidden from tenant; [T] vendor link works only with valid signature and only for that request; [T] tenant sees only own requests; [T] status change notifies tenant.

### 8.8 Notifications and communication
- In-app inbox for every user (bell dropdown with latest 10, full inbox page, mark read, mark all read).
- Email and SMS via the outbox (Section 9). Respect tenant `notification_prefs` for non-critical messages; critical messages (payment receipt, invite, password reset) always send by email if available.
- **Announcements:** compose title and body, choose audience (all tenants, by property, by units, selected tenants), choose channels, preview recipient count and SMS segment count (160 chars per segment, 153 when multipart), send now. Appears in tenant portal "Notices" and inbox.
- **Direct message:** from a tenant profile, send a one-off message via chosen channels.
- **WhatsApp click-to-send:** on tenant profile and invoice pages, a button opens `https://wa.me/{intl_phone}?text={urlencoded prefilled message}` (reminder, receipt link). No API cost.
- **Message templates:** editable per event and channel with placeholders: `{tenant_name}`, `{unit}`, `{property}`, `{amount}`, `{balance}`, `{due_date}`, `{invoice_number}`, `{receipt_number}`, `{link}`, `{org_name}`, `{request_number}`, `{status}`. Live preview with sample data. Reset to default button.
- Acceptance: [T] announcement audience resolution correct for each type; [T] prefs respected; [T] template placeholders rendered and unknown placeholders left blank safely.

### 8.9 Expenses
- CRUD expenses per property/unit with receipt upload, category, date.
- Used in Income vs Expenses report.

### 8.10 Reports (screen, CSV export, PDF)
1. Rent roll: every unit, tenant, rent, frequency, lease end, balance.
2. Arrears aging: tenant, unit, buckets 0 to 30 / 31 to 60 / 61 to 90 / 90+, total.
3. Collections: payments in date range by method and property.
4. Occupancy: per property, occupied/vacant/total, vacancy duration.
5. Income vs expenses: per property per month.
6. Maintenance: counts by status/category, average first response and resolution time, average rating.
All reports filterable by property and date range. CSV streamed (no memory blowup).
- Acceptance: [T] arrears aging buckets correct on fixture.

### 8.11 Public website and listings
- Home: org logo, headline, about text, featured vacant listings, contact details, tenant login button.
- Listings page: filter by property, type, bedrooms, max rent. Only units with `is_listed = true` and status vacant/reserved.
- Unit page: photo gallery, details, rent, inquiry form (honeypot field + throttle 3 per hour per IP). Inquiry notifies owner/manager.
- Admin inbox for inquiries with status.
- Basic SEO: title, meta description, Open Graph tags, `sitemap.xml`, `robots.txt`.
- Owner can toggle the public site off (then `/` redirects to login).

### 8.12 Settings (owner only)
Tabs: Organization (name, logo, color, contact, timezone, currency), Website (headline, about, toggle), Billing (default lead days, grace days, late fee defaults, allow overpayment, bank account details for transfers), Payments (Paystack public/secret keys, test/live mode, "Test connection" button that calls Paystack `GET /bank` with the key), Email (SMTP host, port, encryption, username, password, from name/address, "Send test email"), SMS (driver, Termii API key and sender ID and channel `generic|dnd`, Twilio SID/token/from, "Send test SMS"), Notifications (which events send SMS by default), Staff (invite staff by email, set role, deactivate). Secrets stored encrypted and never echoed back in full (show last 4 chars).

### 8.13 Tenant portal
- Home: greeting, current balance card with "Pay now", next due date, lease summary (unit, dates, rent), open repair requests, latest notices.
- Pay: wizard 6.5.4, org bank details for transfers, pending proofs with status.
- Payments: history with receipt download, statement generator.
- Invoices: list and detail, PDF.
- Repairs: list with status chips, detail with timeline and comments, new request wizard, rate when resolved.
- Inbox and Notices.
- Documents: tenant-visible documents (lease agreement etc.).
- Account: profile, password, notification preferences, 2FA not required for tenants.

### 8.14 PWA ("the App")
- `manifest.webmanifest` with org name, theme color, icons (192, 512, maskable), `start_url: /portal` for tenants (`/app` variant for staff via a second manifest chosen by layout).
- Service worker: cache-first for `/build/*` and icons, network-first for navigations with an offline fallback page. Never cache authenticated HTML or API responses.
- In-portal "Install app" prompt (beforeinstallprompt on Android, instructions sheet on iOS Safari).
- Acceptance: Lighthouse PWA installability passes locally.

### 8.15 Global search, audit log, system health
- **Global search** (Ctrl/Cmd+K command palette): searches tenants (name, phone, email), units (label), properties, invoices (number), payments (reference, receipt number), requests (number, title). Max 5 per group, keyboard navigable. Also lists quick actions ("Record payment", "New tenant", "New announcement").
- **Audit log** (owner): filterable table of `activity_logs`.
- **System health** (owner): PHP version, required extensions, storage writable, DB connection, app version, pending migrations count, scheduler mode and last tick time, last run of each task, outbox counts by status with "Retry failed" and "Drain now", failed messages with errors, test buttons (email, SMS, Paystack).

### 8.16 Installer and upgrader
- `/install` available only when `storage/app/installed.lock` does not exist. Requires `?key=` matching `RENTORA_INSTALL_KEY`.
- Steps: requirements check (PHP version, extensions, writable dirs), DB connection test (uses `.env` values, shows clear error), run `migrate --force`, seed default message templates, create organization and owner account, write lock file containing version and timestamp, redirect to login and then to first-run wizard.
- `/system/upgrade`: owner logged in plus `?key=`. Shows current version (`config('rentora.version')`) vs installed version, list of pending migrations, button to run them, clears view/route cache via `Artisan::call('optimize:clear')`, updates lock file.
- Acceptance: [T] installer refuses without key; [T] installer refuses after lock exists; [T] upgrade requires owner and key.

---

## 9. MESSAGING ENGINE

### 9.1 Flow
Event -> Laravel Notification class -> channels: `database` (immediate), `OutboxEmailChannel`, `OutboxSmsChannel`. The outbox channels render the template and insert rows into `outbound_messages` with status `queued`. The scheduler drains the outbox.

### 9.2 Drain rules
- Pick up to `RENTORA_OUTBOX_BATCH` rows where status `queued` and `send_after <= now`, oldest first, mark `sending` atomically (update with where status = queued to avoid double send).
- Send; on success `sent`. On failure increment attempts, store error, back off `send_after` by 5, 15, 60 minutes; after 4 attempts mark `failed`.
- Stop when time budget is exhausted.
- Phone numbers normalized to E.164 (default country Nigeria +234: `0803...` becomes `+234803...`). Invalid numbers fail immediately with clear error.
- SMS drivers implement `SmsDriver::send(string $to, string $message): SmsResult`. `LogDriver` writes to log (default for local/testing). Termii: `POST https://v3.api.termii.com/api/sms/send` (base URL configurable in settings since Termii assigns per-account base URLs). Twilio: Messages API.
- Email uses Laravel mailer configured at runtime from settings (SMTP), falling back to `.env`.

### 9.3 Events
| Event key | Recipients | Default channels |
|---|---|---|
| tenant.invited | Tenant | email, sms |
| invoice.issued | Tenant | in_app, email |
| invoice.reminder_pre_due (7d, 3d) | Tenant | in_app, email, sms |
| invoice.due_today | Tenant | in_app, email, sms |
| invoice.overdue (3d, 7d, 14d) | Tenant | in_app, email, sms |
| payment.received | Tenant + owner/manager | in_app, email (tenant gets receipt link) |
| payment.proof_submitted | Owner/manager | in_app, email |
| payment.proof_rejected | Tenant | in_app, email, sms |
| payment.failed | Tenant | in_app |
| maintenance.created | Owner/manager/caretaker | in_app, email (sms if emergency) |
| maintenance.status_changed | Tenant | in_app, email |
| maintenance.comment | Other party (non-internal only) | in_app |
| maintenance.vendor_assigned | Vendor | sms, email |
| lease.expiring (60d, 30d) | Tenant + owner/manager | in_app, email |
| announcement.published | Audience | as chosen |
| inquiry.received | Owner/manager | in_app, email |

---

## 10. SCHEDULER

### 10.1 Tasks
| Task | Frequency |
|---|---|
| GenerateInvoices | daily (after 06:00 org time) |
| ApplyLateFees | daily |
| SendRentReminders | daily (after 09:00 org time), idempotent via `reminder_logs` |
| LeaseExpiryAlerts | daily, idempotent via activity log key |
| EndExpiredLeases | daily (lease status `active` with end_date < today and no renewal -> `ended`, unit -> vacant) |
| AutoCloseResolvedRequests | daily |
| ReconcilePendingPayments | every 10 minutes |
| DrainOutbox | every tick |
| PruneOldLogs | weekly |

### 10.2 Modes
- `lazy`: a terminable middleware on all web routes calls `LazyScheduler::tick()` after the response is sent, only if `last_tick_at` is older than `RENTORA_TICK_INTERVAL_MINUTES`. Lock acquired by atomic `UPDATE system_state SET value=? WHERE key='scheduler_lock' AND (value IS NULL OR value < ?)`; only proceed if 1 row affected. Lock expires after 2 minutes. Each task records its own `last_run_at`. Whole tick respects time budget and catches all exceptions (logged, never breaks the user request).
- `cron`: lazy middleware disabled; server cron calls `php artisan rentora:tick` every 5 minutes, or curls `/system/tick?key=...`.
- Command `php artisan rentora:tick` always available for local testing.
- Acceptance: [T] tick runs due tasks only; [T] concurrent tick blocked by lock; [T] task exception does not stop other tasks.

---

## 11. SECURITY

- CSRF on all forms (webhook route excluded). 
- Policies for every model; controllers call `$this->authorize()` or use `can` middleware. Tenant portal queries always constrained to the authenticated tenant id; route model binding scoped.
- `BelongsToOrganization` trait: global scope plus auto-fill `organization_id` on create.
- Mass assignment: explicit `$fillable` everywhere.
- Uploads: validate mime by content (`mimes:jpg,jpeg,png,webp,pdf`), max 8MB, random filenames, private files outside `public`, served by `FileController` with policy checks and `Content-Disposition` headers. Public unit photos in `public/uploads/units/{uuid}.jpg` only.
- Rate limits: login 5/min, inquiry 3/hour/IP, receipt verify 30/min/IP, payment initialize 10/min/user.
- Security headers middleware: `X-Frame-Options: SAMEORIGIN`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy: camera=(), microphone=(), geolocation=()`. HSTS only when request is HTTPS and `APP_ENV=production`.
- Session cookies `secure` when HTTPS, `same_site=lax`.
- Secrets in settings encrypted with `Crypt`. Document clearly: never change `APP_KEY` after install.
- `APP_DEBUG=false` in production; installer warns if true.
- Signed URLs for invites and vendor links, with expiry.
- Passwords: minimum 8 characters, `Password::defaults()` with mixed case and numbers for staff.
- Audit logging for: login, payment recorded/approved/rejected/reversed, invoice voided, lease terminated, settings changed, staff role changed.
- Acceptance: [T] cross-organization access returns 404; [T] tenant A cannot access tenant B invoice, payment, receipt PDF, request, document (all 403/404); [T] private file URL without auth redirects to login.

---

## 12. CODING CONVENTIONS

- PSR-12 via Pint. Strict types in all new PHP files (`declare(strict_types=1);`).
- Enums in `app/Enums` with `label()` and `color()`.
- Form Requests for all input. Never `$request->all()` into models.
- Actions are invokable classes with a single `handle()` or `__invoke()` method, injected via container.
- No business logic in Blade. View models or controller-prepared data only.
- Eager load relationships; `preventLazyLoading` on in local/testing.
- Factories for every model. Feature tests use `RefreshDatabase`.
- External HTTP calls only through the Laravel HTTP client so tests can `Http::fake()`.
- Every config value read through `config()`, never `env()` outside config files.

---

## 13. TESTING AND QUALITY GATES

- Pest feature tests for every [T] acceptance criterion in this document. Group by module in `tests/Feature/{Module}`.
- Unit tests for `Money`, `PhoneNormalizer`, period calculation, allocation, template renderer, SMS segment counter.
- Role/route access matrix test: iterate every named route under `/app` and `/portal` and assert expected status for each role.
- `composer test`, `composer lint`, `composer analyse` must all pass at the end of every phase and at the end of the project with zero failures and zero skipped tests.
- Before final commit also run locally against MySQL (`php artisan migrate:fresh --seed` on MySQL) to confirm migrations work on MySQL, not only SQLite.
- Manual smoke script in `docs/SMOKE_TEST.md`: a numbered list the user can click through in 10 minutes covering install, wizard, tenant invite, invoice, Paystack test payment, proof approval, repair request, announcement, reports.

---

## 14. SEED AND DEMO DATA

`php artisan db:seed` (local only, never by installer) creates:
- Organization "Demo Properties Ltd".
- Users: `owner@demo.test`, `manager@demo.test`, `caretaker@demo.test`, `tenant@demo.test` (all password `password`).
- 3 properties (Lagos, Abuja, Minna), 24 units with mixed types and frequencies, 18 occupied.
- 18 tenants with active leases, 12 months of invoices and payments with realistic arrears (some 0 to 30, some 90+ days), 3 pending transfer proofs.
- 15 maintenance requests across all statuses, 4 vendors, expenses, 2 announcements, 3 inquiries.
- Default message templates.
Seeder must be deterministic (fixed Faker seed).

---

## 15. BUILD PHASES

Each phase ends with: tests, lint, analyse passing, `docs/PROGRESS.md` updated, commit, push.

**Phase 0 - Environment and scaffold**
- Verify local tools: `php -v` (>= 8.2), `composer -V`, `node -v` (>= 20), `npm -v`, `git --version`, `gh --version` and `gh auth status`. If a tool is missing, tell the user exactly what to install and stop.
- Create Laravel 12 project in a new folder `rentora`. Install all packages from Section 3. Configure Pest, Pint, Larastan, Tailwind, Alpine, Vite, Inter font, Chart.js.
- Create `CLAUDE.md`, `docs/PROGRESS.md`, `docs/DECISIONS.md`, `.env.example` with all keys, `config/rentora.php` (version `1.0.0`).
- `git init`, first commit, then: `gh repo create heisbayour/rentora --public --source=. --remote=origin --push --description "Rental property management system: tenants, rent, payments, repairs, notifications"`. If `gh` is not authenticated, ask the user to run `gh auth login` and continue locally meanwhile.

**Phase 1 - Design system:** Tailwind tokens, all components in 6.3, the four layouts, a hidden `/app/_styleguide` route (local env only) rendering every component.

**Phase 2 - Organization, auth, roles:** migrations for core tables, Fortify views, role middleware, `BelongsToOrganization`, policies scaffolding, staff management, profile, 2FA, sequences service, activity logging, settings service with encryption, installer and upgrader, first-run wizard.

**Phase 3 - Properties and units:** CRUD, photos with client-side compression, quick-add, listings toggle, documents module (shared by all later modules), file controller.

**Phase 4 - Tenants and leases:** CRUD, invite flow, lease wizard, activation/renew/end/terminate, unit status sync, tenant profile tabs.

**Phase 5 - Billing:** Money, period calculator, invoice generator, manual invoices, void, late fees, credit application, invoice PDF.

**Phase 6 - Payments:** gateway interface, Paystack gateway, initialize/callback/webhook/reconcile, transfer proofs, staff recording, allocation, reversal, receipts PDF with QR, verify page, statements.

**Phase 7 - Maintenance and vendors:** full module per 8.7 including kanban, vendor signed link, ratings, expenses link.

**Phase 8 - Messaging and scheduler:** outbox, channels, SMS drivers, phone normalizer, templates editor, all notification classes, announcements, WhatsApp buttons, in-app inbox, lazy scheduler, all tasks, `rentora:tick`, `/system/tick`.

**Phase 9 - Tenant portal and PWA:** complete portal screens, payment wizard, repair wizard, notices, install prompt, manifests, service worker, offline page.

**Phase 10 - Dashboard, reports, expenses, search, audit:** per 8.2, 8.9, 8.10, 8.15.

**Phase 11 - Public website:** home, listings, unit page, inquiries, SEO files, toggle.

**Phase 12 - Settings and system health:** all tabs in 8.12 with test buttons, health page.

**Phase 13 - Hardening and release**
- Security headers, rate limits, access matrix test, cross-tenant tests, MySQL migration run.
- Seeder per Section 14.
- `scripts/build-deploy.php` per Section 16.
- Docs: `README.md` (overview, screenshots placeholder section, features, local setup, testing, deploy summary, license MIT), `docs/DEPLOYMENT.md` (Section 16 in full), `docs/USER_GUIDE.md` (landlord and tenant guides in plain language), `docs/SMOKE_TEST.md`, `CHANGELOG.md`.
- Final full run of tests, lint, analyse. Tag `v1.0.0` and push tags.
- Print a final summary for the user: what was built, how to run locally, how to build the deploy package, any decisions in `DECISIONS.md` they should review.

---

## 16. DEPLOYMENT

### 16.1 Build script `scripts/build-deploy.php` (cross-platform, run locally)
1. Run `npm ci` and `npm run build`.
2. Copy project into `dist/rentora/` excluding: `.git`, `node_modules`, `tests`, `dist`, `docs`, `.env`, `storage/logs/*`, `storage/framework/{cache,sessions,views}/*` (keep `.gitignore` placeholders), `.github`, `phpunit.xml`, `*.md` except `README.md`.
3. In `dist/rentora/` run `composer install --no-dev --optimize-autoloader --no-interaction`.
4. Copy `deploy/root.htaccess` to `dist/rentora/.htaccess`.
5. Write a `deny.htaccess` (`Require all denied`) into `app`, `bootstrap`, `config`, `database`, `lang`, `resources`, `routes`, `storage`, `vendor`, `scripts`, `deploy`.
6. Copy `deploy/.env.production.example` to `dist/rentora/.env.production.example`.
7. Zip to `dist/rentora-v{version}.zip`. Print file count (warn if above 25,000).
8. Do NOT run `config:cache`, `route:cache` or `view:cache` (cached absolute paths would break on the server).

### 16.2 `deploy/root.htaccess`
```
Options -Indexes
<FilesMatch "^(\.env.*|composer\.(json|lock)|artisan|package(-lock)?\.json|vite\.config\.js)$">
  Require all denied
</FilesMatch>
RewriteEngine On
RewriteCond %{REQUEST_URI} !^/public/
RewriteRule ^(.*)$ public/$1 [L]
```

### 16.3 InfinityFree steps (to be written in `docs/DEPLOYMENT.md`)
1. Create hosting account and domain/subdomain. In the control panel set PHP version to 8.3 (or highest available, minimum 8.2).
2. Create MySQL database in the control panel. Note host (e.g. `sqlXXX.infinityfree.com`), DB name, user, password.
3. Locally generate an app key: `php artisan key:generate --show`.
4. Run `composer deploy:build`.
5. Rename `.env.production.example` to `.env` inside `dist/rentora/` and fill: `APP_NAME`, `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://yourdomain`, `APP_KEY`, DB values, `RENTORA_INSTALL_KEY`, `RENTORA_TICK_KEY`, `RENTORA_SCHEDULER_MODE=lazy`, `RENTORA_PAYSTACK_WEBHOOKS=false`, `SESSION_DRIVER=file`, `QUEUE_CONNECTION=sync`, `CACHE_STORE=file`.
6. Upload everything inside `dist/rentora/` (including `.htaccess` and `.env`) into `htdocs/` using FileZilla (FTP details in control panel). Uploading the zip and extracting through the file manager is faster if supported.
7. Enable free SSL in the control panel and wait for it to be active.
8. Visit `https://yourdomain/install?key=YOUR_INSTALL_KEY`, complete the installer.
9. Log in, finish first-run wizard, enter SMTP, SMS and Paystack keys in Settings, use the test buttons.
10. In Paystack dashboard set Callback URL to `https://yourdomain/pay/callback`. Do not set a webhook URL on InfinityFree.

### 16.4 Paid cPanel hosting steps
Same as above, plus:
- Point document root to `public/` if the host allows (then root `.htaccess` is unused but harmless).
- Set `RENTORA_SCHEDULER_MODE=cron` and add cron: `*/5 * * * * cd /home/USER/rentora && php artisan rentora:tick >> /dev/null 2>&1`.
- Set `RENTORA_PAYSTACK_WEBHOOKS=true` and Paystack webhook URL `https://yourdomain/webhooks/paystack`.

### 16.5 Updating a live site
1. Pull latest code locally, run `composer deploy:build`.
2. Upload new files over the old ones EXCEPT `.env` and the `storage/` folder (never overwrite those).
3. Visit `https://yourdomain/system/upgrade?key=YOUR_INSTALL_KEY` while logged in as owner and run pending migrations.

### 16.6 Recommended external services
- Email: Brevo SMTP (free tier, daily limit) or Zoho Mail, or Gmail with an app password for low volume.
- SMS (Nigeria): Termii with a registered sender ID; use the `dnd` channel for transactional delivery to DND numbers. International: Twilio.
- Payments: Paystack (test keys during development, live keys after business verification).

---

## 17. FINAL VERIFICATION CHECKLIST (Claude Code must tick all in PROGRESS.md)

- [ ] All phases complete, all [T] criteria covered by passing tests
- [ ] `composer test`, `composer lint`, `composer analyse` pass with zero failures
- [ ] Migrations verified on MySQL
- [ ] Seeded demo works for all four demo logins
- [ ] No em dash character anywhere in repo (`grep -rn $'\u2014' --exclude-dir=vendor --exclude-dir=node_modules .` returns nothing)
- [ ] No secrets in git history
- [ ] `composer deploy:build` produces a zip that, extracted into a fresh Apache folder with a fresh DB, installs and runs via `/install`
- [ ] README, DEPLOYMENT, USER_GUIDE, SMOKE_TEST, CHANGELOG written
- [ ] Repo public at `github.com/heisbayour/rentora`, tagged `v1.0.0`

---

## 18. ROADMAP (NOT IN v1, DO NOT BUILD)

Native mobile apps (Capacitor wrapper of the PWA, requires non-InfinityFree hosting for API), tenant e-signature for leases, move-out inspection checklists with photos, utility meter tracking, multi-currency per property, Flutterwave and Stripe gateways, SaaS billing for multi-org mode, dark mode, WhatsApp Business API, AI assistant for drafting notices and summarizing maintenance trends, bank statement import for auto-matching transfers.
