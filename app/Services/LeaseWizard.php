<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Billing\IssueInitialInvoices;
use App\Actions\Leases\ActivateLease;
use App\Actions\Leases\CreateLease;
use App\Actions\Tenants\InviteTenant;
use App\Actions\Tenants\SaveTenant;
use App\Enums\DepositStatus;
use App\Enums\LateFeeType;
use App\Enums\RentFrequency;
use App\Enums\UnitStatus;
use App\Http\Requests\Admin\UnitRequest;
use App\Models\Lease;
use App\Models\Tenant;
use App\Models\Unit;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * "Add tenant and lease" wizard (spec 6.5.2): tenant, unit, terms, deposit,
 * review. The draft lives in the session so the wizard resumes after a reload.
 */
class LeaseWizard
{
    public const STEPS = ['tenant', 'unit', 'terms', 'deposit', 'review'];

    private const SESSION_KEY = 'lease_wizard';

    public function __construct(
        private readonly Session $session,
        private readonly SaveTenant $saveTenant,
        private readonly CreateLease $createLease,
        private readonly ActivateLease $activateLease,
        private readonly InviteTenant $inviteTenant,
        private readonly IssueInitialInvoices $initialInvoices,
    ) {}

    /** @return array<string, mixed> */
    public function draft(): array
    {
        $draft = $this->session->get(self::SESSION_KEY, []);

        return is_array($draft) ? $draft : [];
    }

    /** Starts afresh, optionally for an existing tenant (skips the tenant step). */
    public function start(?Tenant $tenant = null): string
    {
        $this->session->put(self::SESSION_KEY, $tenant !== null ? ['tenant_id' => $tenant->getKey()] : []);

        return $tenant !== null ? 'unit' : 'tenant';
    }

    public function forget(): void
    {
        $this->session->forget(self::SESSION_KEY);
    }

    /** @param  array<string, mixed>  $values */
    public function remember(string $step, array $values): void
    {
        $draft = $this->draft();
        if ($step === 'unit' && ($draft['unit_id'] ?? null) !== ($values['unit_id'] ?? null)) {
            // A different unit means different default terms.
            unset($draft['terms'], $draft['deposit']);
        }
        if ($step === 'terms') {
            $values = self::withLateFeeValue($values);
        }
        $draft[$step === 'unit' ? 'unit_id' : $step] = $step === 'unit' ? (int) $values['unit_id'] : $values;
        $this->session->put(self::SESSION_KEY, $draft);
    }

    /**
     * Keeps the late fee field matching the chosen type: kobo for fixed, basis points for percent.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public static function withLateFeeValue(array $values): array
    {
        $values['late_fee_value'] = match ($values['late_fee_type'] ?? 'none') {
            LateFeeType::Fixed->value => (int) ($values['late_fee_value_fixed'] ?? 0),
            LateFeeType::Percent->value => (int) ($values['late_fee_value_percent'] ?? 0),
            default => 0,
        };
        unset($values['late_fee_value_fixed'], $values['late_fee_value_percent']);

        return $values;
    }

    public function next(string $step): ?string
    {
        $index = array_search($step, self::STEPS, true);

        return $index === false ? null : (self::STEPS[$index + 1] ?? null);
    }

    public function previous(string $step): ?string
    {
        $draft = $this->draft();
        $index = array_search($step, self::STEPS, true);
        $previous = $index === false || $index === 0 ? null : self::STEPS[$index - 1];

        return $previous === 'tenant' && isset($draft['tenant_id']) ? null : $previous;
    }

    /** The first step still missing data, so a deep link cannot skip ahead. */
    public function firstIncompleteStep(): string
    {
        $draft = $this->draft();

        return match (true) {
            ! isset($draft['tenant_id']) && ! isset($draft['tenant']) => 'tenant',
            ! isset($draft['unit_id']) => 'unit',
            ! isset($draft['terms']) => 'terms',
            ! isset($draft['deposit']) => 'deposit',
            default => 'review',
        };
    }

    public function canOpen(string $step): bool
    {
        return array_search($step, self::STEPS, true) <= array_search($this->firstIncompleteStep(), self::STEPS, true);
    }

    /** @return array<string, mixed> */
    public function rules(string $step, ?int $organizationId): array
    {
        $money = ['required', 'integer', 'min:0', 'max:'.UnitRequest::MAX_MINOR];

        return match ($step) {
            'tenant' => [
                'full_name' => ['required', 'string', 'max:150'],
                'email' => ['nullable', 'email', 'max:150'],
                'phone' => ['required', 'string', 'max:32'],
                'alt_phone' => ['nullable', 'string', 'max:32'],
                'occupation' => ['nullable', 'string', 'max:100'],
                'employer' => ['nullable', 'string', 'max:150'],
                'id_type' => ['nullable', 'string', 'max:50'],
                'id_number' => ['nullable', 'string', 'max:100'],
                'emergency_contact_name' => ['nullable', 'string', 'max:150'],
                'emergency_contact_phone' => ['nullable', 'string', 'max:32'],
            ],
            'unit' => [
                'unit_id' => ['required', 'integer', Rule::exists('units', 'id')
                    ->where('organization_id', $organizationId)
                    ->whereNull('deleted_at')
                    ->whereIn('status', [UnitStatus::Vacant->value, UnitStatus::Reserved->value])],
            ],
            'terms' => [
                'start_date' => ['required', 'date'],
                'end_date' => ['required', 'date', 'after:start_date'],
                'rent_amount' => $money,
                'rent_frequency' => ['required', Rule::enum(RentFrequency::class)],
                'service_charge_amount' => $money,
                'billing_anchor_day' => ['required', 'integer', 'min:1', 'max:28'],
                'invoice_lead_days' => ['required', 'integer', 'min:0', 'max:120'],
                'grace_days' => ['required', 'integer', 'min:0', 'max:90'],
                'late_fee_type' => ['required', Rule::enum(LateFeeType::class)],
                'late_fee_value_fixed' => ['nullable', 'integer', 'min:0', 'max:'.UnitRequest::MAX_MINOR],
                'late_fee_value_percent' => ['nullable', 'integer', 'min:0', 'max:10000'],
            ],
            'deposit' => [
                'deposit_amount' => $money,
                'deposit_status' => ['required', Rule::in([DepositStatus::Unpaid->value, DepositStatus::Held->value])],
                'issue_first_invoices' => ['required', 'boolean'],
            ],
            'review' => [
                'activate_now' => ['required', 'boolean'],
                'send_invite' => ['required', 'boolean'],
            ],
            default => [],
        };
    }

    /** Default terms from the chosen unit (spec 6.5.2: "prefilled from unit rent, editable"). @return array<string, mixed> */
    public function defaultTerms(Unit $unit): array
    {
        $start = today();

        return [
            'start_date' => $start->toDateString(),
            'end_date' => $start->copy()->addYear()->subDay()->toDateString(),
            'rent_amount' => $unit->rent_amount,
            'rent_frequency' => $unit->rent_frequency->value,
            'service_charge_amount' => $unit->service_charge_amount,
            'billing_anchor_day' => CreateLease::anchorDayFor($start),
            'invoice_lead_days' => $unit->rent_frequency->defaultLeadDays(),
            'grace_days' => CreateLease::DEFAULT_GRACE_DAYS,
            'late_fee_type' => LateFeeType::None->value,
            'late_fee_value' => 0,
        ];
    }

    /** @return array<string, mixed> */
    public function defaultDeposit(Unit $unit): array
    {
        return [
            'deposit_amount' => $unit->caution_deposit_amount,
            'deposit_status' => DepositStatus::Unpaid->value,
            'issue_first_invoices' => true,
        ];
    }

    public function tenant(): ?Tenant
    {
        $id = $this->draft()['tenant_id'] ?? null;

        return $id !== null ? Tenant::query()->find($id) : null;
    }

    public function unit(): ?Unit
    {
        $id = $this->draft()['unit_id'] ?? null;

        return $id !== null ? Unit::query()->with('property')->find($id) : null;
    }

    /**
     * Creates the tenant (if new) and the lease, then activates and invites as chosen.
     *
     * @param  array{activate_now: bool, send_invite: bool}  $options
     */
    public function complete(array $options): Lease
    {
        $draft = $this->draft();
        $unit = $this->unit() ?? throw new \LogicException('The wizard has no unit.');

        $lease = DB::transaction(function () use ($draft, $unit, $options): Lease {
            $tenant = $this->tenant() ?? $this->saveTenant->handle(null, $draft['tenant']);
            $lease = $this->createLease->handle($tenant, $unit, array_merge($draft['terms'], [
                'start_date' => Carbon::parse($draft['terms']['start_date']),
                'end_date' => Carbon::parse($draft['terms']['end_date']),
                'deposit_amount' => $draft['deposit']['deposit_amount'],
                'deposit_status' => DepositStatus::from($draft['deposit']['deposit_status']),
                'late_fee_type' => LateFeeType::from($draft['terms']['late_fee_type']),
            ]));

            return $options['activate_now'] ? $this->activateLease->handle($lease) : $lease;
        });

        if ($lease->isActive() && (bool) ($draft['deposit']['issue_first_invoices'] ?? false)) {
            $this->initialInvoices->handle($lease);
        }

        if ($options['send_invite'] && ! $lease->tenant->hasPortalAccount()) {
            $this->inviteTenant->handle($lease->tenant);
        }

        $this->forget();

        return $lease;
    }
}
