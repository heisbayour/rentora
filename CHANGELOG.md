# Changelog

All notable changes are listed here. Versions follow semantic versioning (see `docs/RELEASING.md`).

## [1.0.4] - 2026-11-28

Website only: no new Android app. Has a database migration: run `/system/upgrade?key=...` after uploading.

### Fixed

- Inviting a staff member answered with a 500 error on a site without email, and no link was shown although the account had been created. Staff invites, resends and password reset emails were sent at once with the `.env` mailer, which has no server on the live site. Now no invite, resend or reset fails because email or SMS is missing or broken: email is only tried when it is set up, any SMTP error is caught and reported, and the link is always shown.
- Staff invitations and password reset emails now use the SMTP server saved in Settings > Email (before, they ignored it and used the `.env` mailer).
- Forgot password with email off now says "Email is not set up. Ask the property owner for a reset link." instead of failing; a failing SMTP server gets a similar message.
- The outbox no longer spends the scheduler's time trying to reach an SMTP server that is not set up: such emails fail at once with "Email is not set up" and can be requeued with Retry failed once email works.

### Added

- Share panel after every invite and link: Copy link, WhatsApp, SMS, Email and the phone's share sheet (where the browser has one), with a plain line for each channel saying whether it was sent, queued, not set up or failed. Shown on the staff list, the tenant page, the lease page after the lease wizard and the dashboard after the first-run wizard.
- **Get invite link** on every staff row whose invite has not been accepted, and on tenants without a portal account: a fresh link, nothing sent (a new tenant link replaces the old one).
- **Get password reset link** for owners and developers, on any staff member (Edit) or tenant with a portal account (... menu). The normal 60 minute reset link, shown to share. Owners cannot get one for a developer, and nobody for themselves.
- **Developer (support)** role: every owner permission plus the system pages (upgrade, reset, health). Can change anyone's role and remove any account, including owners. Owners cannot demote, deactivate or remove a developer, and cannot give the role. Shown as "Developer (support)". `/system/reset` keeps developer accounts. While there is no active owner, developers receive the owner notifications. The migration makes the original developer's account a developer; `php artisan rentora:make-developer {email}` does it for others.
- Owners can remove staff accounts (Edit > Remove account). An account with history (audit entries, payments, repairs, uploads, announcements) is deactivated and anonymised so the history stays; one without history is deleted. Nobody removes themselves and the last active owner can never be demoted, deactivated or removed.
- Staff list: "Invite not accepted" status for staff who have not set a password.
- System health **Connections** card: Email (SMTP), SMS (Termii) and Paystack, each Not set up, Working (with the last check time) or Failing (with the error and time), and a Test now button. Checked once a day by the scheduler without sending anything: an SMTP connect and login, a Termii balance call and a Paystack `GET /bank`. Owners and developers see a warning on the dashboard when a set-up connection is failing.
- System health explains what Drain now and Retry failed do.

### Database

- `users.password_set_at` (null while an invite is pending; backfilled from the last login, and set for every tenant) and `users.removed_at`.

## [1.0.3] - 2026-11-28

### Fixed

- Android app: an owner, manager or caretaker who signed in with "Remember me" got "403 FORBIDDEN" every time the app reopened, because the app started on the tenant-only `/portal`. The app now starts on `/start`, which sends staff to the console, tenants to the portal and guests to the login page.
- Anywhere on the site, a signed-in staff member who opens a `/portal` page is sent to `/app`, and a tenant who opens an `/app` page is sent to `/portal`, instead of a bare 403. Form posts into the other area and permission limits inside an area (for example a caretaker on Payments) are still refused with 403.
- Android app: after being offline, Retry showed "Connecting" and then a blank white page. Capacitor's page proxy turned a connection that dropped mid-page into an empty page with no error. Site pages now load directly in the WebView, Retry first checks that the site answers and then reloads the last page (or the start page), and any main page that fails, takes longer than 30 seconds to show anything or comes back blank shows the "No connection" screen again with Retry. When mobile data or Wi-Fi comes back while that screen is showing, the app reconnects by itself.

- Sign-in pages (login, forgot and reset password, two-factor, invitations) looked like a small desktop card on phones and in the Android app. Below 640px they are now full width on white with no card frame, start near the top, and use a larger logo and heading and 48px fields and buttons with 16px text. Tablets and desktops are unchanged.

### Added

- `/system/reset`: clean-slate reset for handing the system over to the client. Developer only, guarded like `/system/upgrade` (signed-in owner plus `?key=RENTORA_INSTALL_KEY`) and not linked from the console. Shows what will be deleted with counts, asks for RESET and the owner's password, deletes all properties, units, tenants, leases, money records, repairs, vendors, expenses, messages, inquiries, documents, the audit log, their uploaded files and every non-owner account, and restarts invoice, receipt and repair numbers at 000001. Keeps the organization, all settings, owner accounts and the install lock. Can create an extra owner account with a temporary password shown once. Runs in one transaction and writes one `system.reset` audit entry. See "Handing over to the client" in `docs/DEPLOYMENT.md`.
- `/start` entry route.

### Android app (versionCode 4, versionName 1.0.3)

- Starts on `/start`; `/start` counts as a home page for the back button.
- Offline handling as described above: native connectivity check before Retry, 30 second load watchdog, blank page check, automatic reconnect when the network returns, pull to refresh on the offline screen does the same as Retry.

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

### Android app (versionCode 3, versionName 1.0.2)

- The app is now a Capacitor WebView instead of a Trusted Web Activity: it opens the portal full screen with no address bar, also on InfinityFree. It installs as an update over the old app (same package name and signing key).
- Native splash screen and launcher icons, `#1A73E8` status bar on every Android version.
- Back button goes back through pages and closes the app only from the home page.
- Photo fields offer the camera and the gallery; the camera permission is asked for when first needed.
- Receipts, invoices and statements download to Downloads and open in the PDF viewer.
- WhatsApp, phone, email and other sites open in their apps; Paystack checkout stays in the app.
- "No connection" screen with Retry, and pull down to refresh.
- Build steps and versioning: `android-app/README.md`. The old TWA files (`android/`) are removed.
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
