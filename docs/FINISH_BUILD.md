# RENTORA - FINISH BUILD (single run)

Drop this file into `C:\Rentora\rentora\docs\FINISH_BUILD.md`. It overrides `docs/BUILD_SPEC.md` wherever the two conflict. Goal: a working, deployable v1.0.0 in one run. Good enough to ship, then improve through versioned updates.

---

## 0. Rules for this run

1. Scope is the original client brief only. Do NOT build registry, estate agent, cross-landlord tenant ID or platform-admin features.
2. Work without stopping for confirmation. Only stop if a tool is truly missing and cannot be found or installed. Before saying a tool is missing, search the disk (XAMPP is at `C:\xampp`).
3. Speed over polish. Use the "Build" lists below; everything under "Deferred" is skipped and written to `docs/ROADMAP.md`.
4. Gates before each commit: `composer test` and `composer lint` must pass. `composer analyse` must pass too; if a Larastan error costs more than 10 minutes, add a narrow baseline entry and note it in DECISIONS.md.
5. New tests only for money, access control and idempotency. Do not write tests for pure display pages.
6. Commit and push after each section below (`v1 finish: <section>`). Update `docs/PROGRESS.md` as you go so a new session can resume.
7. Keep the multi-organization foundation intact. Do not touch any `rentora-local` folder or branch, or `C:\fdrsk`.
8. Never use the em dash character anywhere.
9. Never commit the Android keystore, its passwords, or any `.env` file. The repo is public.

---

## 1. Resume point

Read `docs/PROGRESS.md` and `docs/DECISIONS.md`. Finish and commit any item in progress. Mark in PROGRESS.md which parts of Phases 8 to 13 already exist, then continue below, skipping whatever is already done.

---

## 2. Messaging and scheduler (Phase 8)

**Build**
- `outbound_messages` outbox with batch drain, retries (5, 15, 60 min, then failed), E.164 phone normalizing (Nigeria default).
- Email channel via SMTP (settings, falling back to `.env`). SMS drivers: `log` (default) and `termii`. Skip Twilio.
- Database notifications plus in-app inbox: bell dropdown with unread count, inbox page, mark read and mark all read.
- Notifications: tenant invited, invoice issued, rent reminders (7 days, 3 days, due today, overdue 3 and 7 days, idempotent via `reminder_logs`), payment received, proof submitted, proof rejected, maintenance created and status changed, lease expiring (60 and 30 days).
- Announcements: audience all tenants, by property, or selected tenants; channels in-app, email, SMS; shows recipient count before sending.
- WhatsApp click-to-send button on tenant profile and invoice pages (`wa.me` link with a prefilled message).
- Lazy scheduler (terminable middleware, DB lock, time budget) plus `php artisan rentora:tick` plus `/system/tick?key=`. Tasks: generate invoices, late fees, reminders, lease expiry alerts, end expired leases, auto-close resolved repairs, reconcile pending Paystack payments, drain outbox.

**Deferred:** template editor UI (message text lives in notification classes and lang files), Twilio, segment counter, notification preferences UI (the stored default prefs are still respected).

**Tests:** reminders sent once per kind, scheduler lock blocks a second tick, outbox retry and fail path, tenant sees only own notifications.

---

## 3. Tenant portal and PWA (Phase 9)

**Build**
- Portal home: balance card with Pay now, next due date, lease summary, open repairs, latest notices.
- Pay screen: select invoices (oldest preselected) or a custom amount, Paystack when configured, otherwise org bank details plus transfer proof upload. Show pending proofs with status.
- Payments list with receipt PDF download; statement for a date range.
- Invoices list and detail with PDF.
- Notices (announcements), inbox, documents, account (profile, password).
- Bottom navigation on mobile: Home, Pay, Repairs, Inbox, Account.
- PWA: `manifest.webmanifest` (name, short name, theme `#1A73E8`, icons 192, 512 and maskable 512, `start_url /portal`, `display standalone`), a service worker caching only `/build/*` and icons plus an offline fallback page, and an install prompt on Android.

**Tests:** tenant cannot open another tenant's invoice, receipt or statement; the pay flow creates a pending payment for the right tenant.

---

## 4. Dashboard, reports, expenses (Phase 10)

**Build**
- Dashboard KPI cards: expected this month, collected this month, collection rate, total arrears, occupancy, vacant units, open repairs, leases expiring in 60 days.
- Charts (Chart.js, bundled): collections for the last 12 months (expected vs collected) and an arrears aging donut.
- Dashboard lists: pending proofs with Approve and Reject, recent payments, new repair requests.
- Reports with filters and CSV export: rent roll, arrears aging, collections.
- Expenses CRUD with receipt upload.
- Audit log page (owner): filterable table.

**Deferred:** occupancy, income vs expenses and maintenance reports, PDF report exports, global search (Ctrl+K).

**Tests:** arrears aging buckets correct on a fixture; caretaker cannot open money pages.

---

## 5. Public website (Phase 11)

**Build:** home (org logo, headline, about, featured vacant units, contact, tenant login), listings with filters (property, type, bedrooms, max rent), unit page with gallery and inquiry form (honeypot plus 3 per hour per IP), admin inquiries inbox, toggle to turn the site off (then `/` goes to login).

**Deferred:** sitemap and Open Graph tags.

---

## 6. Settings, system health, app version (Phase 12)

**Build**
- Settings tabs (owner only): Organization, Website, Billing (lead days, grace days, late fee defaults, bank details for transfers), Payments (Paystack keys, mode, Test connection), Email (SMTP, Send test email), SMS (driver, Termii key, sender ID, base URL, Send test SMS), Staff.
- Secrets encrypted, shown only as the last 4 characters.
- System health (owner): PHP version, extensions, storage writable, DB connection, pending migrations, scheduler mode and last tick, outbox counts with Retry failed and Drain now.

**App version (required)**
- Single source of truth: a `VERSION` file in the repo root containing `1.0.0`, read by `config('rentora.version')`.
- The deploy build script writes `build-info.json` (version, short git commit hash, build date in UTC).
- Show the version in the footer of the admin, portal and public layouts as `Rentora v1.0.0 (abc1234)`, plus a full version block on the Settings page and System health page.
- The PWA manifest and the Android package versionName use the same version.
- Versioning rules, written into `docs/RELEASING.md`: semantic versioning (patch for fixes, minor for features, major for breaking changes). Each release updates `VERSION`, adds a `CHANGELOG.md` entry, gets a git tag `vX.Y.Z`, and increments the Android `versionCode` by 1.

---

## 7. Release (Phase 13)

**Build**
- Demo seeder per the spec, kept smaller: 2 properties, 12 units, 9 tenants, 6 months of invoices and payments with some arrears, 2 pending proofs, 6 repairs across statuses, 2 announcements, 2 inquiries. Demo logins: `owner@demo.test`, `manager@demo.test`, `caretaker@demo.test`, `tenant@demo.test`, password `password`.
- `php artisan migrate:fresh --seed` must work on MySQL (start XAMPP MySQL yourself if it is not running).
- `scripts/build-deploy.php` per spec Section 16, plus `build-info.json`.
- Security headers middleware and rate limits per spec Section 11.
- Docs: `README.md` (what it is, features, local setup, demo logins, deploy summary, version), `docs/DEPLOYMENT.md`, `docs/USER_GUIDE.md` (short), `docs/RELEASING.md`, `docs/ROADMAP.md`, `CHANGELOG.md`.
- Tag `v1.0.0` and push the tag.

---

## 8. Android app package (last step, must not block the web release)

The Android app is a Trusted Web Activity (TWA): a signed Android shell that opens the live PWA full screen. Updates to the website reach the app automatically. A new APK is only needed when the icon, name or package settings change.

### 8.1 Signing key (create once, keep forever)
- Folder OUTSIDE the repo: `C:\Rentora\keys\`
- File: `C:\Rentora\keys\rentora.keystore` (PKCS12)
- Key alias: `rentora`
- Password for both the keystore and the key: `rentora 123` (quote it in every command because of the space)
- Create it with:
```
keytool -genkeypair -v -storetype PKCS12 -keystore "C:\Rentora\keys\rentora.keystore" -alias rentora -keyalg RSA -keysize 2048 -validity 10000 -storepass "rentora 123" -keypass "rentora 123" -dname "CN=Rentora, OU=Development, O=Rentora, L=Minna, ST=Niger, C=NG"
```
- Read the fingerprint with:
```
keytool -list -v -keystore "C:\Rentora\keys\rentora.keystore" -alias rentora -storepass "rentora 123"
```
- Write `C:\Rentora\keys\SIGNING_DETAILS.txt` with the file path, alias, password, SHA-1 and SHA-256 fingerprints, package name and creation date. Never copy this file or the keystore into the repo. Add `*.keystore`, `*.jks` and `keys/` to `.gitignore` as a safety net.
- If `keytool` is not on PATH, look for it in an installed JDK or in Android Studio's bundled JBR (`C:\Program Files\Android\Android Studio\jbr\bin\keytool.exe`). If no JDK exists, install Temurin JDK 17.

### 8.2 Digital Asset Links (removes the browser address bar inside the app)
- Package name: `com.heisbayour.rentora` (permanent once published, never change it).
- Add `.env` keys `ANDROID_PACKAGE_NAME` and `ANDROID_SHA256_FINGERPRINTS` (comma separated), documented in `.env.example` with empty values.
- Add a route serving `/.well-known/assetlinks.json` built from those values, with content type `application/json`.

### 8.3 Build the package with Bubblewrap
- Install: `npm i -g @bubblewrap/cli`.
- Create `android/twa-manifest.json` in the repo (no secrets) with: packageId `com.heisbayour.rentora`, host from `APP_URL`, name `Rentora`, launcherName `Rentora`, themeColor and navigationColor `#1A73E8`, backgroundColor `#FFFFFF`, startUrl `/portal`, iconUrl pointing to the 512px icon, maskableIconUrl, appVersionName from `VERSION`, appVersionCode `1`, signingKey path `C:\Rentora\keys\rentora.keystore`, alias `rentora`.
- Let Bubblewrap download its own JDK and Android SDK if none exist, or point `~/.bubblewrap/config.json` at existing ones.
- Build with the passwords supplied as environment variables (`BUBBLEWRAP_KEYSTORE_PASSWORD` and `BUBBLEWRAP_KEY_PASSWORD`, both `rentora 123`) so nothing is typed into files.
- Output goes outside the repo to `C:\Rentora\android-build\`: `app-release-signed.apk` (direct install) and `app-release-bundle.aab` (Google Play).
- Write `android/README.md` (committed, no secrets): how to rebuild, how to bump versionCode and versionName, and where the key lives.

### 8.4 If the Android toolchain fails
Spend at most 30 minutes on it. If it still fails, finish everything else, commit the twa-manifest, the assetlinks route and `android/README.md`, and give the user exact manual steps for PWABuilder.com (Android package, "Use mine" signing key, upload `rentora.keystore`, alias and password as above).

### 8.5 Hosting note to report to the user
The app shows full screen only after `/.well-known/assetlinks.json` is reachable on the live HTTPS domain with the right fingerprint. InfinityFree's browser-check security blocks Google's verifier, so on InfinityFree the app opens with a browser bar. On paid hosting or Render it opens full screen. The app works either way.

---

## 9. Final report to the user

When done, print:
1. What was built, and the deferred list (also in `docs/ROADMAP.md`).
2. Test count and gate status, the final commit hash, and confirmation the `v1.0.0` tag is pushed.
3. Demo logins.
4. How to build the deploy package (`composer deploy:build`) and where the zip lands.
5. Android: keystore path, alias, SHA-256 fingerprint, package name, and the paths of the APK and AAB (or the PWABuilder fallback steps).
6. Any decisions the user should review.
