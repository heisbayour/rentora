<?php

declare(strict_types=1);

return [
    'title' => 'Vendors',
    'description' => 'Plumbers, electricians and other artisans you send on repair jobs.',
    'new' => 'Add vendor',
    'edit' => 'Edit :name',
    'search' => 'Search by name, trade or phone',
    'empty_title' => 'No vendors yet',
    'empty_text' => 'Add the artisans you work with so you can assign them to repairs.',
    'fields' => [
        'name' => 'Name',
        'trade' => 'Trade',
        'phone' => 'Phone',
        'email' => 'Email',
        'notes' => 'Notes',
        'is_active' => 'Active',
        'is_active_hint' => 'Inactive vendors cannot be assigned new jobs.',
    ],
    'columns' => [
        'vendor' => 'Vendor',
        'contact' => 'Contact',
        'open_jobs' => 'Open jobs',
        'status' => 'Status',
    ],
    'active' => 'Active',
    'inactive' => 'Inactive',
    'save' => 'Save vendor',
    'created' => ':name was added.',
    'updated' => ':name was updated.',
];
