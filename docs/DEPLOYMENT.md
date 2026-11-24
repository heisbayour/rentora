# Deploying Rentora

Rentora is uploaded as a ready-built folder: the server needs PHP and MySQL only. No SSH, Composer, Node, cron or queue workers are required.

## 1. Build the package (on your computer)

```bash
composer deploy:build
```

This creates:

- `dist/rentora/` - the folder to upload
- `dist/rentora-v1.0.0.zip` - the same folder zipped (faster to upload if your host can extract zips)

The build compiles the CSS and JavaScript, installs production Composer packages, adds `.htaccess` files (the root one sends every request to `public/`, the others block direct access to code folders), writes `build-info.json` (version, commit, build date) and copies `.env.production.example`. It never caches config, routes or views, because cached absolute paths would break on the server.

## 2. Free hosting (InfinityFree)

1. Create the hosting account and domain or subdomain. In the control panel set the PHP version to 8.3 (minimum 8.2).
2. Create a MySQL database. Note the host (for example `sql123.infinityfree.com`), database name, user and password.
3. On your computer generate an app key: `php artisan key:generate --show`. Keep it safe.
4. Run `composer deploy:build`.
5. In `dist/rentora/`, rename `.env.production.example` to `.env` and fill in:
   - `APP_NAME`, `APP_URL=https://yourdomain`, `APP_KEY` (from step 3), `APP_ENV=production`, `APP_DEBUG=false`
   - `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
   - `RENTORA_INSTALL_KEY` and `RENTORA_TICK_KEY`: long random strings
   - keep `RENTORA_SCHEDULER_MODE=lazy`, `RENTORA_PAYSTACK_WEBHOOKS=false`, `SESSION_DRIVER=file`, `QUEUE_CONNECTION=sync`, `CACHE_STORE=file`
6. Upload everything inside `dist/rentora/` (including `.htaccess` and `.env`) into `htdocs/` with FileZilla (FTP details are in the control panel), or upload the zip and extract it with the file manager.
7. Turn on the free SSL certificate in the control panel and wait until it is active.
8. Open `https://yourdomain/install?key=YOUR_INSTALL_KEY`. The installer checks the server, runs the database migrations, and creates your organization and owner account.
9. Log in, finish the first-run wizard, then open Settings and enter SMTP (Email), SMS (Termii) and Paystack keys. Use each "Send test" or "Test connection" button.
10. In the Paystack dashboard set the Callback URL to `https://yourdomain/pay/callback`. Do not set a webhook URL on InfinityFree (it blocks requests that do not come from a browser).

**Never change `APP_KEY` after installing.** Saved secrets (Paystack, SMTP and SMS) are encrypted with it.

### Background work without cron

With `RENTORA_SCHEDULER_MODE=lazy`, due tasks (invoice generation, late fees, reminders, lease alerts, ending expired leases, closing resolved repairs, Paystack reconciliation, sending queued email and SMS) run for a few seconds after a page has been sent to a visitor, at most every 5 minutes. A database lock stops two runs overlapping. Settings > System health shows the last run of each task and the outbox.

## 3. Paid hosting (cPanel)

Follow the same steps, plus:

- Point the domain's document root to the `public/` folder if the host allows it (the root `.htaccess` then is simply unused).
- Set `RENTORA_SCHEDULER_MODE=cron` and add a cron job every 5 minutes:
  `*/5 * * * * cd /home/USER/rentora && php artisan rentora:tick >> /dev/null 2>&1`
  (or call `https://yourdomain/system/tick?key=YOUR_TICK_KEY` from any cron service).
- Set `RENTORA_PAYSTACK_WEBHOOKS=true` and in Paystack set the webhook URL to `https://yourdomain/webhooks/paystack`.

## 4. Updating a live site

1. Get the latest code and run `composer deploy:build`.
2. Upload the new files over the old ones **except `.env` and the `storage/` folder** (never overwrite those: they hold your settings, uploads and logs).
3. Log in as the owner and open `https://yourdomain/system/upgrade?key=YOUR_INSTALL_KEY`. It shows the new version and runs any new database migrations.

## 5. Recommended services

- Email: Brevo SMTP (free daily limit), Zoho Mail, or Gmail with an app password for low volume.
- SMS (Nigeria): Termii with a registered sender ID; use the `dnd` channel to reach numbers on Do Not Disturb.
- Payments: Paystack (test keys while you try things, live keys after business verification).

## 6. Android app

The Android app is a Trusted Web Activity: a signed shell that opens the live tenant portal full screen, so website updates reach the app at once. Build steps, the signing key and version numbers are in `android/README.md`.

To remove the browser bar inside the app, the site must serve `https://yourdomain/.well-known/assetlinks.json` with the app's signing fingerprint. Set in `.env`:

```
ANDROID_PACKAGE_NAME=com.heisbayour.rentora
ANDROID_SHA256_FINGERPRINTS=AA:BB:...   (the SHA-256 fingerprint of the signing key)
```

InfinityFree's browser check blocks Google's verifier, so on InfinityFree the app opens with a small browser bar. On paid hosting (or Render) it opens full screen. The app works either way.
