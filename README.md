# Rentora

Rental property management for Nigerian landlords: properties and units, tenants and leases, automatic rent invoices, Paystack and bank transfer payments with receipts, repair requests, reminders by email, SMS and in-app inbox, reports, a tenant portal that installs as an app, and a public listings website.

Version: see [`VERSION`](VERSION) (currently 1.0.0). Release notes: [`CHANGELOG.md`](CHANGELOG.md).

## Features

- **Landlord console** (`/app`) for owners, managers and caretakers, each with their own access.
- **Portfolio:** properties, units with photos, quick-add of many units, listing on the public site, documents.
- **Tenants and leases:** tenant records, portal invitations by email or SMS, a step-by-step lease wizard, renewals, ending and termination with unit status kept in sync.
- **Billing:** invoices generated per lease period, manual invoices, late fees, credit balances, invoice PDFs.
- **Payments:** Paystack checkout (callback plus server-side verify), bank transfer with receipt upload and owner approval, cash and other recorded payments, receipts with QR verification, statements.
- **Maintenance:** repair requests with photos, assignment to staff or vendors (signed job link), statuses, SLA flags, ratings.
- **Messaging:** email (SMTP) and SMS (Termii) through an outbox with retries, in-app inbox, rent reminders before and after the due date, lease expiry alerts, announcements, WhatsApp click-to-send.
- **Dashboard and reports:** KPIs, 12-month collections chart, arrears aging, rent roll, collections CSV, expenses, audit log.
- **Tenant portal** (`/portal`): balance and Pay now, invoices, payments and receipts, repairs, notices, inbox, documents, account. Installs as an app (PWA), and ships as an Android app (a Capacitor WebView that opens the portal full screen, see `android-app/README.md`).
- **Public website** (`/`): headline, about, vacant listings with filters, unit pages with inquiry form.
- **Settings and system health:** organization, website, billing, Paystack, SMTP and SMS settings with test buttons; scheduler and outbox status.
- Runs on free shared hosting (InfinityFree): no SSH, no cron, no queue workers needed.

## Requirements

PHP 8.2 or newer (with pdo_mysql, mbstring, openssl, tokenizer, xml, ctype, json, fileinfo, gd, curl, bcmath), MySQL 8 or MariaDB 10.4+, Composer 2, Node 18+ (only to build assets).

## Local setup

```bash
git clone https://github.com/heisbayour/rentora.git
cd rentora
composer install
npm ci && npm run build
cp .env.example .env
php artisan key:generate
# Use MySQL (default in .env.example) or set DB_CONNECTION=sqlite and create database/database.sqlite
php artisan migrate:fresh --seed
php artisan serve
```

Open http://localhost:8000.

### Demo logins

The seeder creates "Demo Properties Ltd" with 2 properties, 12 units, 9 tenants, 6 months of invoices and payments (with some arrears), 2 transfer proofs awaiting approval, 6 repair requests, 2 announcements and 2 inquiries.

| Role | Email | Password |
|---|---|---|
| Owner | owner@demo.test | password |
| Manager | manager@demo.test | password |
| Caretaker | caretaker@demo.test | password |
| Tenant | tenant@demo.test | password |

### Scheduler locally

Background work (invoices, reminders, the email/SMS outbox) runs lazily after page loads. To run it on demand: `php artisan rentora:tick`.

### Quality gates

```bash
composer test     # Pest
composer lint     # Pint
composer analyse  # Larastan level 5
```

## Deploying

1. `composer deploy:build` builds `dist/rentora/` and `dist/rentora-v{version}.zip` (assets compiled, production Composer packages, `.htaccess` files, `build-info.json`).
2. Upload the folder contents to the web root, rename `.env.production.example` to `.env` and fill it in.
3. Visit `https://yourdomain/install?key=YOUR_INSTALL_KEY` and follow the installer.

Full steps for InfinityFree and paid cPanel hosting, updates and the Android app: [`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md). Using the app day to day: [`docs/USER_GUIDE.md`](docs/USER_GUIDE.md). Releasing a new version: [`docs/RELEASING.md`](docs/RELEASING.md). Planned work: [`docs/ROADMAP.md`](docs/ROADMAP.md).

## License

MIT
