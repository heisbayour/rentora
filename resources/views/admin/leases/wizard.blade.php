<x-layouts::admin :title="__('leases.wizard.title')">
    <div class="mx-auto max-w-3xl">
        <x-page-header :title="__('leases.wizard.title')" :breadcrumbs="[__('leases.title') => route('app.leases.index'), __('leases.wizard.title') => null]" />
        <x-stepper :steps="$steps" :current="$position" class="mb-6" />

        <form method="POST" action="{{ route('app.leases.wizard.store', $step) }}">
            @csrf
            <x-card>
                @if ($step === 'tenant')
                    <p class="mb-5 text-sm text-ink-muted">{{ __('leases.wizard.tenant_intro') }}</p>
                    @include('admin.tenants._fields', ['tenant' => $draft['tenant'] ?? []])

                @elseif ($step === 'unit')
                    <p class="mb-5 text-sm text-ink-muted">{{ __('leases.wizard.unit_intro') }}</p>
                    @error('unit_id')
                        <x-alert type="danger" class="mb-4">{{ $message }}</x-alert>
                    @enderror
                    @if ($units->isEmpty())
                        <x-empty-state icon="squares-2x2" :title="__('leases.wizard.no_units')" :action-label="__('units.new')" :action-href="route('app.units.create')" />
                    @else
                        <fieldset class="space-y-6">
                            <legend class="sr-only">{{ __('leases.wizard.steps.unit') }}</legend>
                            @foreach ($units->groupBy('property_id') as $group)
                                <div>
                                    <p class="mb-2 text-xs font-medium uppercase tracking-wide text-ink-muted">{{ $group->first()->property->name }}</p>
                                    <div class="grid gap-3 sm:grid-cols-2">
                                        @foreach ($group as $option)
                                            <label class="flex cursor-pointer items-start gap-3 rounded-control border border-border p-3 has-[:checked]:border-primary-600 has-[:checked]:bg-primary-50">
                                                <input type="radio" name="unit_id" value="{{ $option->id }}" class="mt-1 border-border text-primary-600 focus:ring-primary-600"
                                                    @checked((int) old('unit_id', $draft['unit_id'] ?? 0) === $option->id)>
                                                <span class="text-sm">
                                                    <span class="block font-medium text-ink">{{ $option->label }}</span>
                                                    <span class="block text-ink-muted">{{ $option->type->label() }} · {{ $option->rent()->format() }} {{ $option->rent_frequency->label() }}</span>
                                                    <x-status-chip :status="$option->status" class="mt-1" />
                                                </span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </fieldset>
                    @endif

                @elseif ($step === 'terms')
                    <p class="mb-5 text-sm text-ink-muted">{{ __('leases.wizard.terms_intro') }}</p>
                    <div class="grid gap-5 sm:grid-cols-2" x-data="{ feeType: @js(old('late_fee_type', $terms['late_fee_type'])) }">
                        <x-date-input name="start_date" :label="__('leases.fields.start_date')" :value="$terms['start_date']" required />
                        <x-date-input name="end_date" :label="__('leases.fields.end_date')" :value="$terms['end_date']" required />
                        <x-money-input name="rent_amount" :label="__('leases.fields.rent_amount')" :value="$terms['rent_amount']" :symbol="$currencySymbol" />
                        <x-select name="rent_frequency" :label="__('leases.fields.rent_frequency')" :options="$frequencies" :value="$terms['rent_frequency']" />
                        <x-money-input name="service_charge_amount" :label="__('leases.fields.service_charge_amount')" :value="$terms['service_charge_amount']" :symbol="$currencySymbol" />
                        <x-input name="billing_anchor_day" type="number" min="1" max="28" :label="__('leases.fields.billing_anchor_day')" :hint="__('leases.fields.billing_anchor_day_hint')" :value="$terms['billing_anchor_day']" required />
                        <x-input name="invoice_lead_days" type="number" min="0" max="120" :label="__('leases.fields.invoice_lead_days')" :value="$terms['invoice_lead_days']" required />
                        <x-input name="grace_days" type="number" min="0" max="90" :label="__('leases.fields.grace_days')" :value="$terms['grace_days']" required />
                        <x-select name="late_fee_type" :label="__('leases.fields.late_fee_type')" :options="$lateFeeTypes" :value="$terms['late_fee_type']" x-model="feeType" />
                        {{-- Both fields submit; the wizard keeps the one matching the chosen fee type. --}}
                        <div x-show="feeType === 'fixed'">
                            <x-money-input name="late_fee_value_fixed" :label="__('leases.fields.late_fee_value')" :value="$terms['late_fee_type'] === 'fixed' ? $terms['late_fee_value'] : 0" :symbol="$currencySymbol" />
                        </div>
                        <div x-show="feeType === 'percent'">
                            <x-input name="late_fee_value_percent" type="number" min="0" max="10000" :label="__('leases.fields.late_fee_value')" :hint="__('leases.fields.late_fee_percent_hint')"
                                :value="$terms['late_fee_type'] === 'percent' ? $terms['late_fee_value'] : 0" />
                        </div>
                    </div>

                @elseif ($step === 'deposit')
                    <p class="mb-5 text-sm text-ink-muted">{{ __('leases.wizard.deposit_intro') }}</p>
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-money-input name="deposit_amount" :label="__('leases.fields.deposit_amount')" :value="$deposit['deposit_amount']" :symbol="$currencySymbol" />
                        <x-select name="deposit_status" :label="__('leases.fields.deposit_status')" :value="$deposit['deposit_status']"
                            :options="['unpaid' => \App\Enums\DepositStatus::Unpaid->label(), 'held' => \App\Enums\DepositStatus::Held->label()]" />
                        <x-toggle name="issue_first_invoices" :label="__('leases.wizard.issue_first_invoices')" :checked="(bool) $deposit['issue_first_invoices']" class="sm:col-span-2" />
                    </div>

                @else
                    <p class="mb-5 text-sm text-ink-muted">{{ __('leases.wizard.review_intro') }}</p>
                    @php
                        $reviewTenant = $tenant?->full_name ?? ($draft['tenant']['full_name'] ?? '');
                        $reviewTerms = $draft['terms'];
                        $money = fn (int $minor) => \App\Support\Money::forOrganization($minor)->format();
                    @endphp
                    <dl class="grid gap-4 text-sm sm:grid-cols-2">
                        <div><dt class="text-ink-muted">{{ __('leases.summary.tenant') }}</dt><dd class="font-medium">{{ $reviewTenant }}@if ($tenant) <span class="text-xs text-ink-muted">({{ __('leases.wizard.existing_tenant') }})</span>@endif</dd></div>
                        <div><dt class="text-ink-muted">{{ __('leases.summary.unit') }}</dt><dd class="font-medium">{{ $unit?->label }}, {{ $unit?->property->name }}</dd></div>
                        <div><dt class="text-ink-muted">{{ __('leases.summary.period') }}</dt><dd>{{ \Illuminate\Support\Carbon::parse($reviewTerms['start_date'])->format('j M Y') }} to {{ \Illuminate\Support\Carbon::parse($reviewTerms['end_date'])->format('j M Y') }}</dd></div>
                        <div><dt class="text-ink-muted">{{ __('leases.fields.rent_amount') }}</dt><dd class="tabular-nums">{{ $money((int) $reviewTerms['rent_amount']) }} {{ \App\Enums\RentFrequency::from($reviewTerms['rent_frequency'])->label() }}</dd></div>
                        <div><dt class="text-ink-muted">{{ __('leases.fields.service_charge_amount') }}</dt><dd class="tabular-nums">{{ $money((int) $reviewTerms['service_charge_amount']) }}</dd></div>
                        <div><dt class="text-ink-muted">{{ __('leases.fields.deposit_amount') }}</dt><dd class="tabular-nums">{{ $money((int) $draft['deposit']['deposit_amount']) }} · {{ \App\Enums\DepositStatus::from($draft['deposit']['deposit_status'])->label() }}</dd></div>
                    </dl>
                    <div class="mt-6 space-y-4 border-t border-border pt-5">
                        <x-toggle name="activate_now" :label="__('leases.wizard.activate_now')" :hint="__('leases.wizard.activate_now_hint')" :checked="true" />
                        <x-toggle name="send_invite" :label="__('leases.wizard.send_invite')" :hint="__('leases.wizard.send_invite_hint')" :checked="! $tenant?->hasPortalAccount()" />
                    </div>
                @endif

                <x-slot:footer>
                    <div class="flex items-center justify-between gap-2">
                        @if ($previous)
                            <x-button variant="ghost" :href="route('app.leases.wizard.show', $previous)" icon="arrow-left">{{ __('ui.back') }}</x-button>
                        @else
                            <x-button variant="ghost" :href="route('app.leases.index')">{{ __('ui.cancel') }}</x-button>
                        @endif
                        <x-button>{{ $step === 'review' ? __('leases.wizard.submit') : __('ui.next') }}</x-button>
                    </div>
                </x-slot:footer>
            </x-card>
        </form>
    </div>
</x-layouts::admin>
