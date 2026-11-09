<x-layouts::auth :title="__('payments.verify.title')" :heading="__('payments.verify.title')">
    @if ($payment === null)
        <x-alert type="danger" :title="__('payments.verify.not_found')">{{ __('payments.verify.not_found_text') }}</x-alert>
    @else
        @if ($valid)
            <x-alert type="success" :title="__('payments.verify.valid')" />
        @else
            <x-alert type="danger" :title="__('payments.verify.invalid')">{{ __('payments.verify.invalid_text') }}</x-alert>
        @endif
        <dl class="mt-5 space-y-3 text-sm">
            <div class="flex justify-between gap-4"><dt class="text-ink-muted">{{ __('payments.verify.receipt_number') }}</dt><dd class="font-mono">{{ $payment->receipt_number }}</dd></div>
            <div class="flex justify-between gap-4"><dt class="text-ink-muted">{{ __('payments.verify.amount') }}</dt><dd class="tabular-nums font-medium">{{ \App\Support\Money::of($payment->amount, $payment->organization->currency)->format() }}</dd></div>
            <div class="flex justify-between gap-4"><dt class="text-ink-muted">{{ __('payments.verify.date') }}</dt><dd>{{ $payment->paid_at?->format('j M Y') }}</dd></div>
            <div class="flex justify-between gap-4"><dt class="text-ink-muted">{{ __('payments.verify.tenant') }}</dt><dd>{{ $maskedName }}</dd></div>
            <div class="flex justify-between gap-4"><dt class="text-ink-muted">{{ __('payments.verify.issuer') }}</dt><dd>{{ $payment->organization->name }}</dd></div>
        </dl>
    @endif
</x-layouts::auth>
