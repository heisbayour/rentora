<?php

declare(strict_types=1);

return [
    'role' => [
        'owner' => 'Owner',
        'manager' => 'Manager',
        'caretaker' => 'Caretaker',
        'tenant' => 'Tenant',
    ],
    'property_type' => [
        'residential' => 'Residential',
        'commercial' => 'Commercial',
        'mixed' => 'Mixed use',
    ],
    'unit_type' => [
        'flat' => 'Flat',
        'self_contain' => 'Self contain',
        'room' => 'Room',
        'duplex' => 'Duplex',
        'bungalow' => 'Bungalow',
        'shop' => 'Shop',
        'office' => 'Office',
        'warehouse' => 'Warehouse',
        'other' => 'Other',
    ],
    'rent_frequency' => [
        'monthly' => 'Monthly',
        'quarterly' => 'Quarterly',
        'biannual' => 'Every 6 months',
        'annual' => 'Yearly',
    ],
    'unit_status' => [
        'vacant' => 'Vacant',
        'occupied' => 'Occupied',
        'reserved' => 'Reserved',
        'under_maintenance' => 'Under maintenance',
    ],
    'document_visibility' => [
        'internal' => 'Staff only',
        'tenant' => 'Visible to tenant',
    ],
];
