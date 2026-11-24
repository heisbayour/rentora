# Changelog

All notable changes are listed here. Versions follow semantic versioning (see `docs/RELEASING.md`).

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
