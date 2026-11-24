# Roadmap

Deferred from v1.0.0 (see `docs/FINISH_BUILD.md`). Each item can ship in a later minor release.

## Messaging

- Message template editor with placeholders, live preview and reset to default (v1 keeps message text in code and `lang/en/messaging.php`).
- Twilio SMS driver for international numbers.
- SMS segment counter on the announcement form (160 characters per segment, 153 when multipart).
- Notification preferences screen for tenants and a Settings > Notifications tab (stored defaults are already respected).
- Direct one-off messages from a tenant profile through the outbox (v1 has the WhatsApp button).
- `payment.failed` and `maintenance.comment` in-app notifications.

## Reports

- Occupancy report (vacancy duration per property).
- Income vs expenses per property per month.
- Maintenance report (counts by status and category, response and resolution times, ratings).
- PDF export of reports.

## Console

- Global search command palette (Ctrl+K) across tenants, units, properties, invoices, payments and requests, with quick actions.
- A staff variant of the PWA manifest (`start_url /app`).

## Public website

- `sitemap.xml` and Open Graph tags.

## Out of scope for this product line

Property registry, estate agent features, cross-landlord tenant identity and a platform admin (spec Section 18) are not planned for this codebase.
