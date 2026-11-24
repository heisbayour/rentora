<?php

declare(strict_types=1);

return [
    'mail' => [
        'greeting' => 'Hello :name,',
        'open' => 'Open',
        'footer' => 'Sent by :org.',
    ],
    'sms' => [
        'invite' => 'Activate your tenant account to pay rent and report repairs:',
        'vendor_job' => 'New job :number: :title. Details:',
    ],
    'errors' => [
        'invalid_phone' => 'The phone number ":phone" is not valid.',
        'termii_not_configured' => 'Termii API key or sender ID is missing in Settings.',
    ],
    'invoice_issued' => [
        'title' => 'New invoice :number',
        'body' => 'Invoice :number for :amount is due on :due_date.',
        'action' => 'View invoice',
    ],
    'reminder' => [
        'action' => 'Pay now',
        'pre_due' => [
            'title' => 'Rent due in :days days',
            'body' => 'Invoice :number (:amount) is due on :due_date.',
        ],
        'due_today' => [
            'title' => 'Rent due today',
            'body' => 'Invoice :number (:amount) is due today.',
        ],
        'overdue' => [
            'title' => 'Rent overdue by :days days',
            'body' => 'Invoice :number (:amount) was due on :due_date. Please pay as soon as possible.',
        ],
    ],
    'payment_received' => [
        'tenant_title' => 'Payment received: :amount',
        'tenant_body' => 'Thank you. We received :amount. Your receipt number is :receipt.',
        'tenant_action' => 'View receipt',
        'staff_title' => 'Payment received from :tenant',
        'staff_body' => ':tenant paid :amount (receipt :receipt).',
        'staff_action' => 'View payment',
    ],
    'proof_submitted' => [
        'title' => 'Transfer proof from :tenant',
        'body' => ':tenant uploaded proof of a :amount bank transfer. Please review and approve or reject it.',
        'action' => 'Review proof',
    ],
    'proof_rejected' => [
        'title' => 'Transfer proof not accepted',
        'body' => 'Your transfer proof for :amount was not accepted. Reason: :reason',
        'action' => 'Pay again',
    ],
    'lease_expiring' => [
        'title' => 'Lease ends in :days days',
        'tenant_body' => 'Your lease ends on :date. Please contact us about renewal.',
        'staff_body' => 'The lease for :tenant at :unit ends on :date.',
    ],
    'announcement' => [
        'action' => 'Open notices',
    ],
    'inbox' => [
        'title' => 'Inbox',
        'empty_title' => 'No notifications yet',
        'empty_text' => 'Updates about payments, invoices and repairs appear here.',
        'mark_all_read' => 'Mark all as read',
        'mark_read' => 'Mark as read',
        'view_all' => 'View all',
        'marked' => 'All notifications marked as read.',
        'unread' => ':count unread',
        'filter_unread' => 'Unread',
        'filter_all' => 'All',
    ],
    'whatsapp' => [
        'reminder' => 'Hello :name, this is a reminder from :org that :amount is outstanding on invoice :number (due :due_date). Pay here: :link',
        'tenant' => 'Hello :name, this is :org.',
        'receipt' => 'Hello :name, your receipt :receipt for :amount: :link',
        'button' => 'WhatsApp',
    ],
];
