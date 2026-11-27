# Changelog

All notable changes are listed here. Versions follow semantic versioning (see `docs/RELEASING.md`).

## [1.0.2] - 2026-11-25

### Fixed

- Signed links (tenant invites, staff invites, vendor job links) failed with "403 Invalid signature" on InfinityFree. The host's browser check appends `?i=1` to the URL and its proxy terminates HTTPS. All proxies are now trusted, URLs use https whenever `APP_URL` does (also when the proxy forwards no scheme), and every signed route ignores the `i` parameter. A tampered link still fails.
- Admin console on desktop: the page content sat under the sidebar, hiding the left edge of every page, with the sidebar expanded or collapsed. The sidebar sizes were defined for widths only, so the content offset classes (`lg:pl-sidebar`, `lg:pl-rail`) were never generated. They are now spacing tokens, and the expanded offset is applied before JavaScript starts.
- Lease page: the late fee shows its value ("10% of the invoice", "₦5,000.00 fixed" or "No late fee") instead of only the type.
- Tenant portal: the navigation marked Home, Pay, Inbox and Account as active on every page; only the current section is highlighted now.
- Phones: cards on the dashboard and other two-column pages could be wider than the screen when a line of text was long. Grids now have an explicit single column on small screens.
- Public site and other pages: the white page background stopped after the first screen.
- Maintenance board: columns wrap onto new rows instead of hiding off the right edge; tabs (tenant profile, settings) wrap instead of scrolling out of sight; the payments table no longer cuts off the Status column at 1024px (long references wrap); search boxes keep their width next to filter chips.

### Changed

- Inside the Android app (user agent containing `RentoraApp`) the portal no longer shows the "Install app" card and does not register the service worker, so the app's own offline screen is used.

### Added

- `scripts/screenshots.mjs`: screenshots of the main screens at 1920, 1366, 1024 and 390 pixels wide, with automated checks for content under the sidebar and anything past the right edge.

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
