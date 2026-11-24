# Rentora user guide

A short guide for landlords and their staff. Tenants use the portal and need no guide: it opens on their balance with a Pay now button.

## Roles

- **Owner:** everything, including settings, staff, the audit log and system health.
- **Manager:** properties, tenants, leases, invoices, payments, reports, announcements and repairs.
- **Caretaker:** repairs and vendors; can see units and tenant contact details, never money.
- **Tenant:** the portal only, and only their own records.

## First steps

1. After installing, the first-run wizard asks for your organization details, your first property, its units and (optionally) your first tenant.
2. **Settings:** enter your bank account (Billing), Paystack keys (Payments), SMTP (Email) and Termii (SMS). Use the test buttons.
3. **Staff:** invite managers and caretakers by email from Settings > Staff.

## Daily work

- **New tenant:** Leases > New lease walks you through the unit, the tenant, the terms and activation. Choose "invite to the portal" to send the tenant an activation link by email and SMS.
- **Invoices** are created automatically before each rent period (Settings > Billing sets how many days before). Late fees are added after the grace period if the lease has one. You can also raise a manual invoice.
- **Payments:**
  - Tenants pay online with Paystack, or upload a bank transfer receipt. Transfer proofs appear on the dashboard: Approve allocates the money and sends a receipt; Reject asks for a reason and tells the tenant.
  - Record cash or other payments yourself from Payments > Record payment.
  - Every receipt has a QR code anyone can scan to confirm it is genuine.
- **Reminders** go out automatically 7 and 3 days before rent is due, on the day, and 3 and 7 days after. Tenants choose email and SMS; receipts and invitations always go by email.
- **Repairs:** tenants report repairs with photos in the portal. Move each request through Acknowledged, Scheduled, In progress and Resolved; the tenant is told at every step. Assign a vendor to send them a job link by SMS.
- **Announcements:** send a notice to all tenants, tenants of one property, or chosen tenants, in-app, by email or SMS. The form shows how many tenants will receive it.
- **WhatsApp:** the WhatsApp button on a tenant or invoice page opens a prefilled message on your phone or WhatsApp Web.

## Reports

Reports has the rent roll, arrears aging and collections, each filterable by property (and dates for collections) and exportable to CSV. Expenses records what you spend per property, with receipts.

## Website

Your public site lists units marked "Listed" that are vacant or reserved. Visitors' messages arrive in Inquiries. Turn the site off in Settings > Website.

## When something looks wrong

Settings > System health shows the server checks, when each background task last ran (with any error), and the email/SMS outbox. "Retry failed" requeues failed messages and "Drain now" sends what is waiting.
