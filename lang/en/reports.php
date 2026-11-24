<?php

declare(strict_types=1);

return [
    'title' => 'Reports',
    'description' => 'Filter on screen or export to CSV for your spreadsheet.',
    'export_csv' => 'Export CSV',
    'empty_title' => 'No data for these filters',
    'empty_text' => 'Try another property or date range.',
    'names' => [
        'rent_roll' => 'Rent roll',
        'arrears' => 'Arrears aging',
        'collections' => 'Collections',
    ],
    'descriptions' => [
        'rent_roll' => 'Every unit with its tenant, rent, lease end and balance.',
        'arrears' => 'What each tenant owes, by how long it has been overdue.',
        'collections' => 'Payments received in a date range, by method and property.',
    ],
    'filters' => [
        'property' => 'Property',
        'all_properties' => 'All properties',
        'from' => 'From',
        'to' => 'To',
        'apply' => 'Apply',
    ],
    'columns' => [
        'property' => 'Property',
        'unit' => 'Unit',
        'status' => 'Status',
        'tenant' => 'Tenant',
        'rent' => 'Rent',
        'frequency' => 'Frequency',
        'lease_end' => 'Lease end',
        'balance' => 'Balance',
        'b0_30' => '0 to 30 days',
        'b31_60' => '31 to 60 days',
        'b61_90' => '61 to 90 days',
        'b90_plus' => 'Over 90 days',
        'total' => 'Total',
        'date' => 'Date',
        'receipt' => 'Receipt',
        'method' => 'Method',
        'amount' => 'Amount',
    ],
    'aging' => [
        'buckets' => [
            '0_30' => '0 to 30 days',
            '31_60' => '31 to 60 days',
            '61_90' => '61 to 90 days',
            '90_plus' => 'Over 90 days',
        ],
    ],
];
