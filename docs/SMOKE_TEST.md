# Smoke test (about 10 minutes)

Run after installing or updating a site. Use Paystack test keys.

1. **Install:** open `/install` without a key (expect "forbidden"), then `/install?key=...`. Requirements are green, migrations run, create the organization and owner. Opening `/install?key=...` again returns "not found".
2. **First-run wizard:** log in as the owner, complete organization, first property, a few units and (optionally) a tenant.
3. **Settings:** Billing: add the bank account. Email: save SMTP and "Send test email". SMS: save Termii and "Send test SMS". Payments: save Paystack test keys and "Test connection".
4. **Tenant invite:** Leases > New lease, choose a unit, add a tenant with your own email and phone, activate and invite. The invitation email/SMS arrives (System health > Drain now sends it at once).
5. **Invoice:** the lease shows its first invoice. Open it, download the PDF, try the WhatsApp button.
6. **Paystack payment:** open the invite link in a private window, set a password, Pay > card, pay with a Paystack test card. You return to Payments with a receipt; the invoice is paid; scan the receipt QR code.
7. **Transfer proof:** in the portal pay by bank transfer and upload any image. As the owner, approve it from the dashboard; the tenant gets a receipt. Upload another and reject it with a reason.
8. **Repair:** as the tenant, report a repair with a photo. As the caretaker, acknowledge, schedule and resolve it; the tenant sees each step and can rate it.
9. **Announcement:** send one to all tenants, in-app and email; it shows under the tenant's Notices and Inbox.
10. **Reports:** open the dashboard (charts load), the arrears aging report and export the collections CSV.
11. **Public site:** list a vacant unit, open `/`, send an inquiry from the unit page; it appears in Inquiries.
12. **System health:** every task has a last run time and the outbox has no failed messages.
