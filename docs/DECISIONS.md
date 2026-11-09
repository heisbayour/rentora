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

## Phase 2

17. **Fortify registration and passkeys are off.** Staff and tenants are invited, so public sign-up is never needed; Fortify 1.41's passkeys are not in the spec. Its publish-time passkeys and two-factor column migrations were removed; the 2FA columns live in the users migration.
18. **Current organization is a scoped service** (`App\Support\CurrentOrganization`) set by `SetCurrentOrganization` before route model binding (it is placed ahead of `SubstituteBindings` in the middleware priority list), so bound models are always organization scoped and a record from another organization is a 404. With no current organization (console, installer) queries are unscoped; scheduler tasks will set it per organization.
19. **Guests use the first organization in single-org mode.** In `RENTORA_MULTI_ORG=true` mode guests have no organization; resolving one by domain is SaaS work in the Section 18 roadmap.
20. **Unauthorised areas return 403**, for example a tenant opening /app or staff opening /portal. Records belonging to another organization return 404.
21. **Staff invitations use a signed 7-day link** (`/invite/staff/{user}`) rather than a password reset token, matching the tenant invite in spec 4.3. The link carries an HMAC of the user id and current password hash, so it stops working once a password is set.
22. **Invitation emails use Laravel's mail channel until Phase 8**, when every outbound message moves onto the outbox channels.
23. **The installer checks the PDO extension of the configured database driver** instead of always `pdo_mysql`, so the same check works for MySQL in production and SQLite in tests and local development.
24. **The install lock path is configurable** (`RENTORA_INSTALL_LOCK`, relative to `storage/`). Tests use `storage/framework/testing/installed.lock` so the suite never touches a local install's lock. The local seeder writes the lock and marks onboarding complete.
25. **First-run wizard is a step registry** (`App\Services\Onboarding`, `App\Onboarding\*Step`). Phase 2 ships the organization step; the property, units and invite-tenant steps are registered when those modules exist (Phases 3 and 4). Only the owner is redirected to it.
26. **TOTP 2FA is enforced as owner/manager only** by overriding Fortify's `EnableTwoFactorAuthentication` action, not only by hiding the UI.
27. **The `local` disk no longer has Laravel 12's `serve` option**, so private files can only be reached through the authorising file controller (Phase 3). Public images use a separate `uploads` disk rooted at `public/uploads`.
28. **Gates for area-level permissions** (`manage-settings`, `manage-staff`, `manage-money`, `manage-portfolio`, `view-system-health`, `view-audit-log`, `reverse-payments`, `use-maintenance`) live in `AppServiceProvider`; per-record rules go in Policies as each model arrives.

## Phase 3

29. **`App\Support\Money` was built in Phase 3**, ahead of Phase 5, because unit rents, service charges and deposits are displayed from this phase on. It never uses floats: parsing is string based, formatting splits whole and minor units with integer maths, and percentages use basis points.
30. **Public photos are re-encoded server side to JPEG** (max 1600px, quality 80) even though the browser already compresses them. This guarantees the spec's `public/uploads/units/{uuid}.jpg` format and strips EXIF metadata such as GPS coordinates from tenants' and landlords' phones.
31. **Unit photos are reordered with move-earlier / move-later buttons** rather than drag and drop: keyboard accessible, works without JavaScript and needs no extra library.
32. **An enforced morph map** gives polymorphic columns short names (`property`, `unit`, ...). Every new model must be added to `AppServiceProvider`; a missing one throws rather than silently storing a class name.
33. **A unit that is not vacant or reserved is automatically unlisted** when saved, and the Listings page refuses to list one, so the public site never shows an occupied unit.
34. **Deleting a property soft deletes its units too.** The active-lease guard (spec 8.3) is added in Phase 4 with leases; the property page's income and open-repairs figures arrive with payments (Phase 6) and maintenance (Phase 7).
35. **Caretakers can open the units list and unit pages read-only** but not properties, listings or documents, matching "read-only unit and tenant contact info" in spec 1.2.
36. **The first-run wizard gained the "first property" and "units" steps**; the units step reuses the quick-add table. Neither is skippable; the tenant-invite step (Phase 4) will be.

## Phase 4

37. **`leases.renewal_of_id` was added to the schema.** Renewal creates a draft lease starting the day after the current end date; the link lets ending a lease activate its renewal straight away (unit stays occupied) and lets the `EndExpiredLeases` task tell "ended with no renewal" apart (spec 10.1). Terminating early discards a prepared renewal.
38. **Tenant status meaning:** `pending_invite` until the tenant accepts a portal invite, `active` once they have a portal account (or on a new active lease after accepting), `former` once no active lease covers them.
39. **"End lease" is available only on or after the end date**; ending sooner is "Terminate early", which requires a reason. Both release the unit (vacant) and the tenant (former) unless another active lease still covers them.
40. **Activation locks the unit row** (`lockForUpdate`) before checking for another active lease, so two concurrent activations cannot both succeed (spec 5.3 business rule).
41. **Tenant invitation links are bound to `invite_sent_at` and the portal account**: resending invalidates older links and an accepted link stops working. A tenant invited without an email chooses their login email when accepting.
42. **Child records take their parent's organization** (`parentOrganizationId()` on Lease, Unit and Document) when no organization was set, so actions run from the console or scheduler file records in the right organization.
43. **The lease wizard keeps its draft in the session** and refuses deep links past the first incomplete step. Choosing a different unit resets the terms and deposit to that unit's defaults. Late fees use kobo for a fixed amount and basis points (1000 = 10%) for a percentage.
44. **The "issue first invoices" option and the termination "final invoice" option are recorded in the UI now** and acted on by the billing module in Phase 5.
45. **A WhatsApp click-to-send button on the tenant profile was added early** (plain `wa.me` link, local numbers converted to 234...). Phase 8 adds prefilled messages and the full phone normalizer.
46. **Caretakers see a tenant's overview with contact details only**; leases, documents and activity tabs are owner/manager only. Unit pages show the current tenant's contacts to caretakers.

## Phase 5

47. **The payments and payment_allocations tables were created in Phase 5.** A tenant's credit is the unallocated part of their successful payments, and applying credit to a new invoice is recorded as an allocation from those payments, so statements (Phase 6) explain every naira. `tenants.credit_balance` is a cache recomputed from payments and allocations after every change.
48. **Billing periods:** period k starts on A + k periods, where A is the billing anchor day on or before the lease start. The first period starts on the lease start date and the last stops at the lease end. A short first period (for example a lease starting on the 30th with anchor 28) is billed in full; proration is not in the spec.
49. **The due date of a rent or service charge invoice is the first day of its period.** Invoices are issued `invoice_lead_days` before that.
50. **Late fees exclude deposits and late fees themselves**, need a lease with a late fee configured, and are charged once the day after due date + grace days has started. A percentage fee is taken on the amount still outstanding. A unique index on `parent_invoice_id` guarantees at most one late fee per invoice.
51. **Voiding an invoice also voids its unpaid late fee.** An invoice with any allocation cannot be voided.
52. **First invoices at activation** (first period rent and service charge regardless of lead time, plus the deposit if marked unpaid) are issued when the lease wizard activates the lease with that option on. Leases activated later from the lease page rely on the daily generator, which catches up any due periods.
53. **The early-termination final invoice takes an amount entered by staff** (type "other"), since the spec does not define how a final amount is calculated.

## Phase 6

54. **One `PaymentVerifier` confirms every online payment**: the redirect callback, the webhook, "Check status" and reconciliation all call it. It locks the payment row and returns early for anything not pending, so a duplicate callback or webhook cannot allocate twice or issue a second receipt.
55. **The webhook looks the payment up by reference before checking the signature**, because Paystack keys are stored per organization in Settings. An invalid signature still gets 401 and nothing is changed. On a valid `charge.success` event the transaction is re-verified with the API rather than trusting the payload.
56. **Verification codes use 31 unambiguous characters** (no 0/O or 1/I/L) so a code typed from a printed receipt is not misread. Receipt QR codes are embedded as PNG data URIs (GD), so the PDF has no remote images.
57. **Amount in words is generated in house** (British style, "one thousand and five") instead of PHP's intl NumberFormatter, which shared hosts may not have.
58. **A failed or abandoned Paystack checkout (Paystack 404 on verify) is marked failed**; "pending", "ongoing" and connection errors leave the payment pending for reconciliation, which gives up after 48 hours.
59. **Rejected and reversed payments both end in status `rejected`** (the spec has no separate "reversed" status); the activity log records which happened and why.
60. **The Paystack callback currently returns the tenant to the portal home with a toast**; Phase 9 adds the receipt page it will land on.
61. **Statements list invoices on their issue date and payments on their paid date**; void invoices and non-successful payments are excluded. On the same day, charges are listed before payments.
