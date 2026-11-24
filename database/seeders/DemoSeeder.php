<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Leases\ActivateLease;
use App\Actions\Messaging\PublishAnnouncement;
use App\Actions\Payments\RecordManualPayment;
use App\Actions\Payments\TransferProofs;
use App\Enums\AnnouncementAudience;
use App\Enums\DepositStatus;
use App\Enums\LateFeeType;
use App\Enums\LeaseStatus;
use App\Enums\MaintenancePriority;
use App\Enums\MaintenanceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PropertyType;
use App\Enums\RentFrequency;
use App\Enums\TenantStatus;
use App\Enums\UnitStatus;
use App\Enums\UnitType;
use App\Models\Inquiry;
use App\Models\Invoice;
use App\Models\Lease;
use App\Models\MaintenanceRequest;
use App\Models\Organization;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Billing\InvoiceGenerator;
use App\Services\Maintenance\MaintenanceWorkflow;
use App\Services\SettingsService;
use App\Support\CurrentOrganization;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;

/**
 * Demo portfolio (FINISH_BUILD Section 7): 2 properties, 12 units, 9 tenants
 * with active leases, about 6 months of invoices and payments with some
 * arrears, 2 pending transfer proofs, 6 repairs across statuses, 2
 * announcements and 2 inquiries. Deterministic: fixed names and amounts.
 * Built through the real actions so balances, receipts and logs agree.
 */
class DemoSeeder extends Seeder
{
    private const TENANTS = [
        // name, phone, units index, rent (naira, monthly unless set), payment pattern
        ['Adaeze Okafor', '08031110001', 0, 250_000, 'paid'],
        ['Babatunde Adeyemi', '08031110002', 1, 250_000, 'paid'],
        ['Chiamaka Eze', '08031110003', 2, 180_000, 'paid'],
        ['Danladi Musa', '08031110004', 3, 180_000, 'paid'],
        ['Efosa Igbinedion', '08031110005', 6, 3_000_000, 'annual'],
        ['Fatima Bello', '08031110006', 7, 2_400_000, 'annual'],
        ['Gbenga Olatunji', '08031110007', 8, 150_000, 'late_one'],
        ['Halima Abubakar', '08031110008', 9, 150_000, 'late_many'],
        ['Demo Tenant', '08031110009', 4, 200_000, 'late_one'],
    ];

    public function run(): void
    {
        fake()->seed(2026);
        $organization = Organization::query()->where('slug', 'demo-properties')->firstOrFail();
        if (Property::query()->withoutGlobalScopes()->where('organization_id', $organization->id)->exists()) {
            return;
        }

        app(CurrentOrganization::class)->runAs($organization, fn () => $this->seed($organization));
    }

    private function seed(Organization $organization): void
    {
        $owner = User::query()->where('email', 'owner@demo.test')->firstOrFail();
        $manager = User::query()->where('email', 'manager@demo.test')->firstOrFail();
        $tenantUser = User::query()->where('email', 'tenant@demo.test')->firstOrFail();
        $today = CarbonImmutable::today();
        $start = $today->startOfMonth()->subMonthsNoOverflow(5);

        app(SettingsService::class)->setMany([
            'billing.bank_name' => 'Zenith Bank',
            'billing.bank_account_name' => 'Demo Properties Ltd',
            'billing.bank_account_number' => '1012345678',
            'website.enabled' => '1',
        ]);

        $units = $this->portfolio($organization);

        $tenants = [];
        foreach (self::TENANTS as $i => [$name, $phone, $unitIndex, $rent, $pattern]) {
            $unit = $units[$unitIndex];
            $tenant = new Tenant([
                'full_name' => $name,
                'email' => $i === 8 ? $tenantUser->email : strtolower(str_replace(' ', '.', $name)).'@example.com',
                'phone' => $phone,
                'occupation' => ['Engineer', 'Banker', 'Teacher', 'Trader', 'Doctor', 'Lawyer', 'Civil servant', 'Nurse', 'Designer'][$i],
                'status' => TenantStatus::PendingInvite,
                'notification_prefs' => Tenant::DEFAULT_PREFS,
            ]);
            $tenant->organization_id = $organization->id;
            $tenant->save();
            if ($i === 8) {
                $tenant->forceFill(['user_id' => $tenantUser->id])->save();
                $tenantUser->forceFill(['name' => $name, 'phone' => $phone])->save();
            }

            $annual = $pattern === 'annual';
            $leaseStart = $annual ? $start->subMonthsNoOverflow(2) : $start;
            $lease = new Lease([
                'unit_id' => $unit->id,
                'tenant_id' => $tenant->id,
                'start_date' => $leaseStart,
                'end_date' => $i === 2 ? $today->addDays(45) : $leaseStart->addYear()->subDay(),
                'rent_amount' => $rent * 100,
                'rent_frequency' => $annual ? RentFrequency::Annual : RentFrequency::Monthly,
                'service_charge_amount' => $annual ? 0 : 1_500_000,
                'deposit_amount' => 0,
                'deposit_status' => DepositStatus::Held,
                'billing_anchor_day' => 1,
                'invoice_lead_days' => $annual ? 30 : 7,
                'grace_days' => 5,
                'late_fee_type' => LateFeeType::None,
                'late_fee_value' => 0,
                'status' => LeaseStatus::Draft,
            ]);
            $lease->organization_id = $organization->id;
            $lease->save();
            app(ActivateLease::class)->handle($lease);
            app(InvoiceGenerator::class)->forLease($lease->fresh(), $today);

            $tenants[] = $tenant->fresh();
            $this->pay($tenant->fresh(), $pattern, $manager, $today);
        }

        // Two transfer proofs waiting for approval.
        $proofs = app(TransferProofs::class);
        foreach ([6, 8] as $i) {
            $tenant = $tenants[$i];
            $open = Invoice::query()->where('tenant_id', $tenant->id)->open()->orderBy('due_date')->first();
            if ($open !== null) {
                $proofs->submit($tenant, $open->outstandingMinor(), $today->subDay(), $tenant->full_name, UploadedFile::fake()->image('transfer-receipt.jpg', 600, 800), [$open->id]);
            }
        }

        $this->repairs($units, $tenants, $owner, $manager);

        $publish = app(PublishAnnouncement::class);
        $publish->handle($owner, 'Water tank cleaning on Saturday', 'Water supply will be off on Saturday from 9am to 1pm while the overhead tanks are cleaned. Please store some water on Friday night.', AnnouncementAudience::AllTenants, [], ['in_app', 'email']);
        $publish->handle($manager, 'Estate meeting', 'All Bosso Court residents are invited to a short meeting at the car park on the last Sunday of the month at 4pm.', AnnouncementAudience::Property, [$units[6]->property_id], ['in_app']);

        foreach ([[$units[5], 'Kemi Ajayi', '08039990001', 'Good afternoon. Is this flat still available? I would like to inspect it this weekend.'], [$units[11], 'Ibrahim Sani', '08039990002', 'Please what is the total package for this shop, including service charge?']] as [$unit, $name, $phone, $message]) {
            $inquiry = new Inquiry(['unit_id' => $unit->id, 'name' => $name, 'phone' => $phone, 'message' => $message]);
            $inquiry->organization_id = $organization->id;
            $inquiry->ip = '127.0.0.1';
            $inquiry->save();
        }
    }

    /** @return list<Unit> */
    private function portfolio(Organization $organization): array
    {
        $properties = [
            ['name' => 'Lekki Palms', 'code' => 'LKP', 'address' => '14 Admiralty Way, Lekki Phase 1', 'city' => 'Lagos', 'state' => 'Lagos', 'type' => PropertyType::Residential],
            ['name' => 'Bosso Court', 'code' => 'BSC', 'address' => '7 Bosso Road', 'city' => 'Minna', 'state' => 'Niger', 'type' => PropertyType::Mixed],
        ];
        $layout = [
            // property index, label, type, bedrooms, rent naira, frequency
            [0, 'A1', UnitType::Flat, 3, 250_000, RentFrequency::Monthly],
            [0, 'A2', UnitType::Flat, 3, 250_000, RentFrequency::Monthly],
            [0, 'B1', UnitType::Flat, 2, 180_000, RentFrequency::Monthly],
            [0, 'B2', UnitType::Flat, 2, 180_000, RentFrequency::Monthly],
            [0, 'C1', UnitType::Flat, 2, 200_000, RentFrequency::Monthly],
            [0, 'C2', UnitType::Flat, 2, 200_000, RentFrequency::Monthly],
            [1, 'D1', UnitType::Duplex, 4, 3_000_000, RentFrequency::Annual],
            [1, 'D2', UnitType::Bungalow, 3, 2_400_000, RentFrequency::Annual],
            [1, 'S1', UnitType::SelfContain, 1, 150_000, RentFrequency::Monthly],
            [1, 'S2', UnitType::SelfContain, 1, 150_000, RentFrequency::Monthly],
            [1, 'S3', UnitType::SelfContain, 1, 150_000, RentFrequency::Monthly],
            [1, 'Shop 1', UnitType::Shop, 0, 900_000, RentFrequency::Annual],
        ];

        $created = [];
        foreach ($properties as $data) {
            $property = new Property([...$data, 'country' => 'Nigeria', 'is_listed' => true, 'description' => 'Secure, well kept estate with steady power and water.']);
            $property->organization_id = $organization->id;
            $property->save();
            $created[] = $property;
        }

        $units = [];
        foreach ($layout as [$p, $label, $type, $bedrooms, $rent, $frequency]) {
            $unit = new Unit([
                'property_id' => $created[$p]->id,
                'label' => $label,
                'type' => $type,
                'bedrooms' => $bedrooms,
                'bathrooms' => max(1, $bedrooms),
                'rent_amount' => $rent * 100,
                'rent_frequency' => $frequency,
                'service_charge_amount' => $frequency === RentFrequency::Monthly ? 1_500_000 : 0,
                'caution_deposit_amount' => 0,
                'status' => UnitStatus::Vacant,
                'is_listed' => true,
                'description' => str_starts_with((string) $label, 'Shop') ? 'Ground floor shop facing the main road.' : 'Tiled floors, fitted kitchen, prepaid meter and parking space.',
            ]);
            $unit->organization_id = $organization->id;
            $unit->save();
            $units[] = $unit;
        }

        return $units;
    }

    /** Pays the tenant's invoices according to their pattern, on each invoice's due date. */
    private function pay(Tenant $tenant, string $pattern, User $staff, CarbonImmutable $today): void
    {
        $invoices = Invoice::query()->where('tenant_id', $tenant->id)->whereDate('due_date', '<=', $today->toDateString())->orderBy('due_date')->get();
        $count = $invoices->count();
        $record = app(RecordManualPayment::class);

        foreach ($invoices->values() as $n => $invoice) {
            $skip = match ($pattern) {
                'late_one' => $n === $count - 1,
                'late_many' => $n >= 2,
                default => false,
            };
            if ($skip) {
                continue;
            }
            $method = $n % 3 === 0 ? PaymentMethod::BankTransfer : PaymentMethod::Cash;
            $paidAt = CarbonImmutable::parse($invoice->due_date->toDateString())->addDays($n % 4)->setTime(10, 30);
            $record->handle($tenant, $staff, $method, $invoice->amount, $paidAt->min($today->setTime(9, 0)), null, null, [$invoice->id]);
        }
    }

    /**
     * @param  list<Unit>  $units
     * @param  list<Tenant>  $tenants
     */
    private function repairs(array $units, array $tenants, User $owner, User $manager): void
    {
        $vendor = new Vendor(['name' => 'Sunday Plumbing Works', 'trade' => 'Plumber', 'phone' => '08035550101', 'is_active' => true]);
        $vendor->organization_id = $owner->organization_id;
        $vendor->save();
        $electrician = new Vendor(['name' => 'Bright Spark Electricals', 'trade' => 'Electrician', 'phone' => '08035550102', 'is_active' => true]);
        $electrician->organization_id = $owner->organization_id;
        $electrician->save();

        $workflow = app(MaintenanceWorkflow::class);
        $jobs = [
            [0, 'plumbing', 'Kitchen sink is leaking', MaintenancePriority::Normal, MaintenanceStatus::Submitted, null],
            [1, 'electrical', 'Bedroom sockets not working', MaintenancePriority::High, MaintenanceStatus::Acknowledged, $electrician],
            [2, 'carpentry', 'Wardrobe door off its hinge', MaintenancePriority::Low, MaintenanceStatus::Scheduled, null],
            [8, 'plumbing', 'No water pressure in bathroom', MaintenancePriority::Emergency, MaintenanceStatus::InProgress, $vendor],
            [3, 'painting', 'Damp patch on living room wall', MaintenancePriority::Normal, MaintenanceStatus::Resolved, null],
            [6, 'security', 'Gate lock is faulty', MaintenancePriority::High, MaintenanceStatus::Closed, null],
        ];
        $paths = [
            MaintenanceStatus::Submitted->value => [],
            MaintenanceStatus::Acknowledged->value => [MaintenanceStatus::Acknowledged],
            MaintenanceStatus::Scheduled->value => [MaintenanceStatus::Acknowledged, MaintenanceStatus::Scheduled],
            MaintenanceStatus::InProgress->value => [MaintenanceStatus::Acknowledged, MaintenanceStatus::InProgress],
            MaintenanceStatus::Resolved->value => [MaintenanceStatus::Acknowledged, MaintenanceStatus::InProgress, MaintenanceStatus::Resolved],
            MaintenanceStatus::Closed->value => [MaintenanceStatus::Acknowledged, MaintenanceStatus::InProgress, MaintenanceStatus::Resolved, MaintenanceStatus::Closed],
        ];

        foreach ($jobs as [$tenantIndex, $category, $title, $priority, $status, $assignee]) {
            $tenant = $tenants[$tenantIndex];
            $lease = Lease::query()->where('tenant_id', $tenant->id)->where('status', LeaseStatus::Active->value)->firstOrFail();
            $unit = Unit::query()->findOrFail($lease->unit_id);
            $request = $workflow->create($unit, $tenant, $tenant->user, [
                'category' => $category,
                'title' => $title,
                'description' => $title.'. Please send someone to check it.',
                'priority' => $priority->value,
            ]);
            if ($assignee instanceof Vendor) {
                $request = $workflow->assign($request, $manager, null, $assignee->id);
            }
            foreach ($paths[$status->value] as $step) {
                $extra = $step === MaintenanceStatus::Scheduled ? ['scheduled_for' => now()->addDays(2)->setTime(10, 0)] : [];
                $request = $workflow->changeStatus(MaintenanceRequest::query()->findOrFail($request->id), $step, $manager, null, $extra);
            }
        }
    }
}
