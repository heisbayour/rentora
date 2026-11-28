# Deploying Rentora

Rentora is uploaded as a ready-built folder: the server needs PHP and MySQL only. No SSH, Composer, Node, cron or queue workers are required.

## 1. Build the package (on your computer)

```bash
composer deploy:build
```

This creates:

- `dist/htdocs/` - the folder whose **contents** go into the host's document root (`htdocs/`)
- `dist/rentora-vX.Y.Z.zip` - the same contents zipped (faster to upload if your host can extract zips)

Layout of `dist/htdocs/`:

```
htdocs/
  index.php, .htaccess          front controller and the standard Laravel public .htaccess
  build/  icons/  uploads/      compiled CSS and JS, app icons, public images (logos, unit photos)
  favicon.ico, offline.html, robots.txt, sw.js
  _rentora/                     the application, blocked from the web ("Require all denied")
    .env                        your settings (you create it)
    app/ bootstrap/ config/ database/ lang/ resources/ routes/ storage/ vendor/
    VERSION, build-info.json, .env.production.example
```

Why: the document root on InfinityFree is `htdocs/` and cannot be changed. Apache applies a real folder's access rules before any rewrite runs, so a code folder at the top level with the same name as a route (`app/` and `/app`, `vendor/` and `/vendor/job/...`) answers 403. Everything except the contents of `public/` therefore lives in `_rentora/`, and the build fails if a top-level folder ever matches a route prefix. `index.php` loads `_rentora/` and sets Laravel's public path to `htdocs/`, so uploads and built assets resolve there.

The build compiles the CSS and JavaScript, installs production Composer packages, writes `build-info.json` (version, commit, build date) and copies `.env.production.example`. It never caches config, routes or views, because cached absolute paths would break on the server.

## 2. Free hosting (InfinityFree)

1. Create the hosting account and domain or subdomain. In the control panel set the PHP version to 8.3 (minimum 8.2).
2. Create a MySQL database. Note the host (for example `sql123.infinityfree.com`), database name, user and password.
3. On your computer generate an app key: `php artisan key:generate --show`. Keep it safe.
4. Run `composer deploy:build`.
5. In `dist/htdocs/_rentora/`, rename `.env.production.example` to `.env` and fill in:
   - `APP_NAME`, `APP_URL=https://yourdomain`, `APP_KEY` (from step 3), `APP_ENV=production`, `APP_DEBUG=false`
   - `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
   - `RENTORA_INSTALL_KEY` and `RENTORA_TICK_KEY`: long random strings
   - keep `RENTORA_SCHEDULER_MODE=lazy`, `RENTORA_PAYSTACK_WEBHOOKS=false`, `SESSION_DRIVER=file`, `QUEUE_CONNECTION=sync`, `CACHE_STORE=file`
6. Upload everything inside `dist/htdocs/` (including `.htaccess` and `_rentora/.env`) into `htdocs/` with FileZilla (FTP details are in the control panel; turn on Server > Force showing hidden files so `.htaccess` and `.env` are visible), or upload the zip and extract it with the file manager.
7. Turn on the free SSL certificate in the control panel and wait until it is active.
8. Open `https://yourdomain/install?key=YOUR_INSTALL_KEY`. The installer checks the server, runs the database migrations, and creates your organization and owner account.
9. Log in, finish the first-run wizard, then open Settings and enter SMTP (Email), SMS (Termii) and Paystack keys. Use each "Send test" or "Test connection" button.
10. In the Paystack dashboard set the Callback URL to `https://yourdomain/pay/callback`. Do not set a webhook URL on InfinityFree (it blocks requests that do not come from a browser).

**Never change `APP_KEY` after installing.** Saved secrets (Paystack, SMTP and SMS) are encrypted with it.

### Background work without cron

With `RENTORA_SCHEDULER_MODE=lazy`, due tasks (invoice generation, late fees, reminders, lease alerts, ending expired leases, closing resolved repairs, Paystack reconciliation, sending queued email and SMS) run for a few seconds after a page has been sent to a visitor, at most every 5 minutes. A database lock stops two runs overlapping. Settings > System health shows the last run of each task and the outbox.

## 3. Paid hosting (cPanel)

Follow the same steps, plus:

- Upload the same `dist/htdocs/` contents into the document root (`public_html/`). The layout works unchanged on any host.
- Set `RENTORA_SCHEDULER_MODE=cron` and add a cron job every 5 minutes:
  `*/5 * * * * cd /home/USER/public_html/_rentora && php artisan rentora:tick >> /dev/null 2>&1`
  (or call `https://yourdomain/system/tick?key=YOUR_TICK_KEY` from any cron service).
- Set `RENTORA_PAYSTACK_WEBHOOKS=true` and in Paystack set the webhook URL to `https://yourdomain/webhooks/paystack`.

## 4. Updating a live site

1. Get the latest code and run `composer deploy:build`.
2. Upload the contents of `dist/htdocs/` over the old files **except `_rentora/.env`, `_rentora/storage/` and `uploads/`** (never overwrite or delete those: they hold your settings, private files, logs, the install lock and public images).
3. Log in as the owner and open `https://yourdomain/system/upgrade?key=YOUR_INSTALL_KEY`. It shows the new version and runs any new database migrations.

## 5. Moving an existing 1.0.0 install to the 1.0.1 layout

Version 1.0.0 put the whole project in `htdocs/` with `public/` inside it. That layout makes `/app` and vendor links answer 403 on InfinityFree. Move to the new layout like this. **No reinstall**: the database is not touched, and `.env` (with `APP_KEY`), `storage/` (install lock, private files, sessions) and the uploaded images are moved, not replaced.

Use FileZilla with Server > Force showing hidden files turned on. Dragging a file or folder onto another folder in the remote (right-hand) pane moves it on the server without downloading it.

1. **Back up.** Download `htdocs/.env`, `htdocs/storage/app/` and `htdocs/public/uploads/` to your computer. Optionally export the database in phpMyAdmin.
2. **Build** on your computer: `composer deploy:build` (creates `dist/htdocs/`). Do not put a `.env` in it.
3. **Create** the folder `htdocs/_rentora/` on the server.
4. **Move** (on the server, do not delete):
   - `htdocs/.env` to `htdocs/_rentora/.env`
   - `htdocs/storage/` to `htdocs/_rentora/storage/`
   - `htdocs/public/uploads/` to `htdocs/uploads/`
5. **Delete** everything else in `htdocs/` from the old layout, leaving only `_rentora/` and `uploads/`: the old root `.htaccess`, `app/`, `bootstrap/`, `config/`, `database/`, `deploy/`, `lang/`, `public/` (now without `uploads/`), `resources/`, `routes/`, `scripts/`, `vendor/`, and the loose files (`artisan`, `composer.json`, `composer.lock`, `package.json`, `package-lock.json`, `vite.config.js`, `tailwind.config.js`, `postcss.config.js`, `phpstan.neon`, `pint.json`, `VERSION`, `build-info.json`, `README.md`, `.env.example`, `.env.production.example`, `.gitattributes`, `.gitignore`, `.editorconfig`). The site is offline from here until step 6 finishes.
6. **Upload** the contents of `dist/htdocs/` into `htdocs/`. When FileZilla reports existing files, choose Skip for `_rentora/storage/` and `uploads/` (they only contain placeholder files in the build).
7. Open `https://yourdomain/system/upgrade?key=YOUR_INSTALL_KEY` as the owner. It shows 1.0.1 and runs any new migrations (1.0.1 has none).
8. **Check:** log in and open `/app`, open a vendor job link, the tenant portal `/portal`, a page with a logo or unit photo, and Settings > System health. Opening `https://yourdomain/_rentora/.env` must answer 403.

If something goes wrong, the old layout can be restored from the backup and the 1.0.0 package, because the database is unchanged.

## 6. Recommended services

- Email: Brevo SMTP (free daily limit), Zoho Mail, or Gmail with an app password for low volume.
- SMS (Nigeria): Termii with a registered sender ID; use the `dnd` channel to reach numbers on Do Not Disturb.
- Payments: Paystack (test keys while you try things, live keys after business verification).

## 7. Android app

The Android app is a Capacitor WebView: a signed app that opens the live tenant portal full screen with no browser bar, so website updates reach the app at once. It needs nothing on the server (no `assetlinks.json` check). Build steps, the signing key and version numbers are in `android-app/README.md`.

To remove the browser bar inside the app, the site must serve `https://yourdomain/.well-known/assetlinks.json` with the app's signing fingerprint. Set in `.env`:

```
ANDROID_PACKAGE_NAME=com.heisbayour.rentora
ANDROID_SHA256_FINGERPRINTS=AA:BB:...   (the SHA-256 fingerprint of the signing key)
```

InfinityFree's browser check blocks Google's verifier, so on InfinityFree the app opens with a small browser bar. On paid hosting (or Render) it opens full screen. The app works either way.
