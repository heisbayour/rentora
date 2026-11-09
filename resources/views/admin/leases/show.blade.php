@php
    $title = __('leases.lease_for', ['unit' => $lease->unit->label]);
    $days = $lease->daysRemaining();
@endphp

<x-layouts::admin :title="$title">
    <x-page-header :title="$title" :description="$lease->tenant->full_name.' · '.$lease->unit->property->name"
        :breadcrumbs="[__('leases.title') => route('app.leases.index'), $title => null]">
        <x-slot:actions>
            @if ($lease->status === \App\Enums\LeaseStatus::Draft)
                <form method="POST" action="{{ route('app.leases.activate', $lease) }}">
                    @csrf
                    <x-button icon="check">{{ __('leases.actions.activate') }}</x-button>
                </form>
            @elseif ($lease->isActive())
                @unless ($lease->renewal)
                    <x-button type="button" variant="secondary" icon="arrow-path" x-data x-on:click="$dispatch('open-modal', 'renew-lease')">{{ __('leases.actions.renew') }}</x-button>
                @endunless
                @if ($days <= 0)
                    <x-button type="button" variant="secondary" x-data x-on:click="$dispatch('open-modal', 'end-lease')">{{ __('leases.actions.end') }}</x-button>
                @endif
                <x-button type="button" variant="danger" x-data x-on:click="$dispatch('open-modal', 'terminate-lease')">{{ __('leases.actions.terminate') }}</x-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    @foreach (['lease', 'end_date', 'reason'] as $field)
        @error($field)
            <x-alert type="danger" class="mb-4">{{ $message }}</x-alert>
        @enderror
    @endforeach

    <div class="grid gap-6 lg:grid-cols-3">
        <x-card class="lg:col-span-2">
            <div class="mb-4 flex flex-wrap items-center gap-3">
                <x-status-chip :status="$lease->status" />
                @if ($lease->isActive())
                    <span class="text-sm text-ink-muted">{{ $days >= 0 ? trans_choice('leases.summary.days_left', $days, ['count' => $days]) : __('leases.summary.ended_days', ['count' => abs($days)]) }}</span>
                @endif
            </div>
            <dl class="grid gap-4 text-sm sm:grid-cols-2">
                <div><dt class="text-ink-muted">{{ __('leases.summary.tenant') }}</dt><dd><a href="{{ route('app.tenants.show', $lease->tenant) }}" class="font-medium text-primary-600 hover:underline">{{ $lease->tenant->full_name }}</a></dd></div>
                <div><dt class="text-ink-muted">{{ __('leases.summary.unit') }}</dt><dd><a href="{{ route('app.units.show', $lease->unit) }}" class="font-medium text-primary-600 hover:underline">{{ $lease->unit->label }}</a>, {{ $lease->unit->property->name }}</dd></div>
                <div><dt class="text-ink-muted">{{ __('leases.summary.period') }}</dt><dd>{{ $lease->start_date->format('j M Y') }} to {{ $lease->end_date->format('j M Y') }}</dd></div>
                <div><dt class="text-ink-muted">{{ __('leases.fields.rent_amount') }}</dt><dd class="tabular-nums">{{ $lease->rent()->format() }} <span class="text-ink-muted">{{ $lease->rent_frequency->label() }}</span></dd></div>
                <div><dt class="text-ink-muted">{{ __('leases.fields.service_charge_amount') }}</dt><dd class="tabular-nums">{{ $lease->serviceCharge()->format() }}</dd></div>
                <div><dt class="text-ink-muted">{{ __('leases.fields.deposit_amount') }}</dt><dd class="tabular-nums">{{ $lease->deposit()->format() }} <x-status-chip :status="$lease->deposit_status" class="ml-1" /></dd></div>
                <div><dt class="text-ink-muted">{{ __('leases.summary.billing') }}</dt><dd>{{ __('leases.fields.billing_anchor_day') }}: {{ $lease->billing_anchor_day }} · {{ __('leases.fields.grace_days') }}: {{ $lease->grace_days }}</dd></div>
                <div><dt class="text-ink-muted">{{ __('leases.fields.late_fee_type') }}</dt><dd>{{ $lease->late_fee_type->label() }}</dd></div>
                @if ($lease->renewal)
                    <div><dt class="text-ink-muted">{{ __('leases.summary.renewal') }}</dt><dd><a href="{{ route('app.leases.show', $lease->renewal) }}" class="text-primary-600 hover:underline">{{ $lease->renewal->start_date->format('j M Y') }} to {{ $lease->renewal->end_date->format('j M Y') }}</a></dd></div>
                @endif
                @if ($lease->renewalOf)
                    <div><dt class="text-ink-muted">{{ __('leases.summary.renewal_of') }}</dt><dd><a href="{{ route('app.leases.show', $lease->renewalOf) }}" class="text-primary-600 hover:underline">{{ $lease->renewalOf->start_date->format('j M Y') }} to {{ $lease->renewalOf->end_date->format('j M Y') }}</a></dd></div>
                @endif
            </dl>
            @if ($lease->terminated_at)
                <x-alert type="warning" class="mt-4">{{ __('leases.summary.terminated', ['date' => $lease->terminated_at->format('j M Y'), 'reason' => $lease->termination_reason]) }}</x-alert>
            @endif
        </x-card>

        <x-card :title="__('leases.agreement')" class="lg:col-span-1">
            <x-documents :documentable="$lease" :documents="$documents" :visibilities="$visibilities" :can-manage="true" />
        </x-card>
    </div>

    @if ($lease->isActive())
        <x-modal name="renew-lease" :title="__('leases.renew.title')" :show="$errors->has('end_date')">
            <p class="mb-4 text-ink-muted">{{ __('leases.renew.intro', ['date' => $lease->end_date->copy()->addDay()->format('j M Y')]) }}</p>
            <form method="POST" action="{{ route('app.leases.renew', $lease) }}" class="space-y-4">
                @csrf
                <x-date-input name="end_date" :label="__('leases.fields.end_date')" :value="$renewalEnd" required />
                <x-money-input name="rent_amount" :label="__('leases.fields.rent_amount')" :value="$lease->rent_amount" :symbol="$currencySymbol" />
                <x-money-input name="service_charge_amount" :label="__('leases.fields.service_charge_amount')" :value="$lease->service_charge_amount" :symbol="$currencySymbol" />
                <div class="flex justify-end gap-2 pt-2">
                    <x-button type="button" variant="secondary" x-on:click="$dispatch('close-modal', 'renew-lease')">{{ __('ui.cancel') }}</x-button>
                    <x-button>{{ __('leases.renew.submit') }}</x-button>
                </div>
            </form>
        </x-modal>

        <x-confirm name="end-lease" method="POST" :action="route('app.leases.end', $lease)" variant="primary"
            :title="__('leases.end_confirm.title')" :consequences="__('leases.end_confirm.consequences')" :confirm-label="__('leases.actions.end')" />

        <x-confirm name="terminate-lease" method="POST" :action="route('app.leases.terminate', $lease)" word="TERMINATE"
            :title="__('leases.terminate_confirm.title')" :consequences="__('leases.terminate_confirm.consequences')" :confirm-label="__('leases.actions.terminate')">
            <div class="mt-4 space-y-4">
                <x-textarea name="reason" :label="__('leases.fields.termination_reason')" rows="2" required />
                <x-checkbox name="final_invoice" :label="__('leases.terminate_confirm.final_invoice')" />
                <x-money-input name="final_invoice_amount" :label="__('leases.terminate_confirm.final_amount')" :value="0" :symbol="$currencySymbol" />
            </div>
        </x-confirm>
    @endif
</x-layouts::admin>
