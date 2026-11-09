@php
    $title = $payment->receipt_number ?? $payment->reference;
    $pendingProof = $payment->status === \App\Enums\PaymentStatus::Pending && $payment->method === \App\Enums\PaymentMethod::BankTransfer;
    $allocated = $payment->allocations->sum('amount');
@endphp

<x-layouts::admin :title="$title">
    <x-page-header :title="$title" :description="$payment->tenant->full_name"
        :breadcrumbs="[__('payments.title') => route('app.payments.index'), $title => null]">
        <x-slot:actions>
            @if ($payment->proof_path)
                <x-button variant="secondary" :href="route('app.payments.proof', $payment)" icon="paper-clip">{{ __('payments.actions.proof') }}</x-button>
            @endif
            @if ($payment->receipt_number)
                <x-button variant="secondary" :href="route('app.payments.receipt', $payment)" icon="arrow-down-tray">{{ __('payments.actions.receipt') }}</x-button>
            @endif
            @if ($payment->method === \App\Enums\PaymentMethod::Paystack && $payment->status === \App\Enums\PaymentStatus::Pending)
                <form method="POST" action="{{ route('app.payments.check', $payment) }}">@csrf<x-button variant="secondary" icon="arrow-path">{{ __('payments.actions.check') }}</x-button></form>
            @endif
            @if ($pendingProof)
                <form method="POST" action="{{ route('app.payments.approve', $payment) }}">@csrf<x-button icon="check">{{ __('payments.actions.approve') }}</x-button></form>
                <x-button type="button" variant="danger" x-data x-on:click="$dispatch('open-modal', 'reject-payment')">{{ __('payments.actions.reject') }}</x-button>
            @endif
            @can('reverse', $payment)
                @if ($payment->status === \App\Enums\PaymentStatus::Successful && $payment->method !== \App\Enums\PaymentMethod::Paystack)
                    <x-button type="button" variant="danger" x-data x-on:click="$dispatch('open-modal', 'reverse-payment')">{{ __('payments.actions.reverse') }}</x-button>
                @endif
            @endcan
        </x-slot:actions>
    </x-page-header>

    @error('payment')
        <x-alert type="danger" class="mb-4">{{ $message }}</x-alert>
    @enderror

    <div class="grid gap-6 lg:grid-cols-3">
        <x-card class="lg:col-span-2">
            <div class="mb-4 flex flex-wrap items-center gap-3">
                <x-status-chip :status="$payment->status" />
                <span class="text-xl font-semibold tabular-nums">{{ $payment->money()->format() }}</span>
            </div>
            <dl class="grid gap-4 text-sm sm:grid-cols-2">
                <div><dt class="text-ink-muted">{{ __('payments.fields.tenant') }}</dt><dd><a href="{{ route('app.tenants.show', $payment->tenant) }}" class="font-medium text-primary-600 hover:underline">{{ $payment->tenant->full_name }}</a></dd></div>
                <div><dt class="text-ink-muted">{{ __('payments.fields.method') }}</dt><dd>{{ $payment->method->label() }}</dd></div>
                <div><dt class="text-ink-muted">{{ __('payments.details.reference') }}</dt><dd class="font-mono text-xs">{{ $payment->reference }}</dd></div>
                @if ($payment->receipt_number)
                    <div><dt class="text-ink-muted">{{ __('payments.details.receipt') }}</dt><dd class="font-mono text-xs">{{ $payment->receipt_number }}</dd></div>
                @endif
                <div><dt class="text-ink-muted">{{ __('payments.details.paid_at') }}</dt><dd>{{ ($payment->paid_at ?? $payment->created_at)?->format('j M Y') }}</dd></div>
                @if ($payment->note)
                    <div><dt class="text-ink-muted">{{ __('payments.details.sender') }}</dt><dd>{{ $payment->note }}</dd></div>
                @endif
                @if ($payment->gateway_reference)
                    <div><dt class="text-ink-muted">{{ __('payments.details.gateway_reference') }}</dt><dd class="font-mono text-xs">{{ $payment->gateway_reference }}</dd></div>
                @endif
                @if ($payment->last_verified_at)
                    <div><dt class="text-ink-muted">{{ __('payments.details.last_checked') }}</dt><dd>{{ $payment->last_verified_at->diffForHumans() }}</dd></div>
                @endif
            </dl>
            @if ($payment->rejection_reason)
                <x-alert type="warning" class="mt-4">{{ __('payments.details.rejection', ['reason' => $payment->rejection_reason]) }}</x-alert>
            @endif
        </x-card>

        <x-card :title="__('payments.details.allocations')">
            @if ($payment->allocations->isEmpty())
                <p class="text-sm text-ink-muted">{{ __('payments.details.no_allocations') }}</p>
            @else
                <ul class="divide-y divide-border text-sm">
                    @foreach ($payment->allocations as $allocation)
                        <li class="flex justify-between gap-3 py-2">
                            <a href="{{ route('app.invoices.show', $allocation->invoice) }}" class="font-mono text-xs text-primary-600 hover:underline">{{ $allocation->invoice->number }}</a>
                            <span class="tabular-nums">{{ \App\Support\Money::forOrganization($allocation->amount)->format() }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
            @if ($payment->status === \App\Enums\PaymentStatus::Successful && $payment->amount > $allocated)
                <p class="mt-3 text-sm text-ink-muted">{{ __('payments.details.credit', ['amount' => \App\Support\Money::forOrganization($payment->amount - $allocated)->format()]) }}</p>
            @endif
        </x-card>
    </div>

    @if ($pendingProof)
        <x-confirm name="reject-payment" method="POST" :action="route('app.payments.reject', $payment)"
            :title="__('payments.reject_title')" :consequences="__('payments.reject_consequences')" :confirm-label="__('payments.actions.reject')">
            <div class="mt-4"><x-textarea name="reason" :label="__('payments.fields.reason')" rows="2" required /></div>
        </x-confirm>
    @endif
    <x-confirm name="reverse-payment" method="POST" :action="route('app.payments.reverse', $payment)" word="REVERSE"
        :title="__('payments.reverse_title')" :consequences="__('payments.reverse_consequences')" :confirm-label="__('payments.actions.reverse')">
        <div class="mt-4"><x-textarea name="reason" id="reverse-reason" :label="__('payments.fields.reason')" rows="2" required /></div>
    </x-confirm>
</x-layouts::admin>
