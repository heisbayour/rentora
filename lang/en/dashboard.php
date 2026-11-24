<?php

declare(strict_types=1);

return [
    'title' => 'Dashboard',
    'greeting' => 'Welcome back, :name',
    'intro' => 'Here is what is happening across your properties.',
    'empty_title' => 'Nothing to show yet',
    'empty_text' => 'Collections, arrears, occupancy and repairs appear here once you add properties and tenants.',
    'kpi' => [
        'expected' => 'Expected this month',
        'collected' => 'Collected this month',
        'collection_rate' => 'Collection rate',
        'arrears' => 'Total arrears',
        'occupancy' => 'Occupancy',
        'occupied_of' => ':occupied of :total units occupied',
        'vacant' => 'Vacant units',
        'open_repairs' => 'Open repairs',
        'emergencies' => ':count emergency',
        'emergency_repairs' => 'Emergency repairs',
        'expiring' => 'Leases ending in 60 days',
    ],
    'charts' => [
        'collections' => 'Collections, last 12 months',
        'expected' => 'Expected',
        'collected' => 'Collected',
        'aging' => 'Arrears aging',
    ],
    'lists' => [
        'pending_proofs' => 'Transfer proofs awaiting approval',
        'no_proofs' => 'No proofs waiting.',
        'recent_payments' => 'Recent payments',
        'no_payments' => 'No payments yet.',
        'upcoming' => 'Due in the next 7 days',
        'no_upcoming' => 'Nothing due this week.',
        'new_repairs' => 'Newest repair requests',
        'no_repairs' => 'No repair requests.',
    ],
];
