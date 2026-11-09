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
    'tenant_status' => [
        'pending_invite' => 'Invite pending',
        'active' => 'Active',
        'former' => 'Former',
    ],
    'lease_status' => [
        'draft' => 'Draft',
        'active' => 'Active',
        'ended' => 'Ended',
        'terminated' => 'Terminated',
    ],
    'deposit_status' => [
        'unpaid' => 'Unpaid',
        'held' => 'Held',
        'refunded' => 'Refunded',
        'forfeited' => 'Forfeited',
    ],
    'late_fee_type' => [
        'none' => 'No late fee',
        'fixed' => 'Fixed amount',
        'percent' => 'Percentage of the invoice',
    ],
    'document_visibility' => [
        'internal' => 'Staff only',
        'tenant' => 'Visible to tenant',
    ],
];
