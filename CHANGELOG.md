# Changelog

All notable changes are listed here. Versions follow semantic versioning (see `docs/RELEASING.md`).

## [1.0.1] - 2026-11-25

### Fixed

- After login, `/app` (the console) and vendor job links (`/vendor/job/...`) answered with the host's 403 page on InfinityFree. The deploy package put Laravel's code folders (`app/`, `vendor/` ...) in the document root, each with a "Require all denied" `.htaccess`, and Apache applies a real folder's access rule before any rewrite runs.

### Changed

- New deploy layout: `dist/htdocs/` holds the contents of `public/` at the top level and the application in `_rentora/` (denied to the web). `index.php` loads `_rentora/` and sets the public path to the document root, so uploads and built assets resolve there. The root rewrite `.htaccess` is gone; the standard Laravel public `.htaccess` is used. Local development and artisan are unchanged.
- The deploy build fails if any top-level folder has the same name as a route prefix.
- Existing installs move to the new layout without reinstalling: see "Moving to the 1.0.1 layout" in `docs/DEPLOYMENT.md`.
- Android app: host set to `rentora-housing.infinityfreeapp.com`, version 1.0.1 (code 2).

### Added

- "Copy invite link" and "Share on WhatsApp" after inviting or re-inviting a tenant or staff member, so invites work before email or SMS is set up.

## [1.0.0] - 2026-11-24

First release.

### Added

- Landlord console with owner, manager and caretaker roles, two-factor sign-in for owners and managers, staff invitations.
- Properties, units (photos, quick-add, public listing), documents.
- Tenants, portal invitations by email or SMS, lease wizard, renewals, ending and termination.
- Invoice generation per lease period, manual invoices, late fees, credit balances, invoice PDFs.
- Paystack payments with server-side verification and reconciliation, bank transfer proofs with approval, recorded payments, reversals, receipts with QR verification, statements.
- Repair requests with photos, assignment to staff and vendors, statuses with SLA flags, ratings, automatic closing.
- Email and SMS outbox with retries, Termii SMS, in-app inbox, rent reminders, lease expiry alerts, announcements, WhatsApp buttons.
- Lazy scheduler for hosts without cron, `php artisan rentora:tick` and `/system/tick`.
- Dashboard KPIs and charts, rent roll, arrears aging and collections reports with CSV, expenses, audit log.
- Tenant portal with Pay screen, invoices, payments, repairs, notices, inbox, documents and account; installable PWA.
- Public website with listings, filters, unit pages and inquiries.
- Settings with encrypted secrets and test buttons, system health, version in every footer.
- Web installer and upgrader, deploy package builder, security headers and rate limits.
- Android app package (Trusted Web Activity) configuration and Digital Asset Links.
