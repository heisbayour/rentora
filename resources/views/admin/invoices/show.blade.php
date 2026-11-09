<x-layouts::admin :title="$invoice->number">
    <x-page-header :title="$invoice->number" :description="$invoice->description"
        :breadcrumbs="[__('invoices.title') => route('app.invoices.index'), $invoice->number => null]">
        <x-slot:actions>
            <x-button variant="secondary" :href="route('app.invoices.pdf', $invoice)" icon="arrow-down-tray">{{ __('invoices.download_pdf') }}</x-button>
            @if ($invoice->status !== \App\Enums\InvoiceStatus::Void && $invoice->amount_paid === 0)
                <x-button type="button" variant="danger" x-data x-on:click="$dispatch('open-modal', 'void-invoice')">{{ __('invoices.void') }}</x-button>
            @endif
        </x-slot:actions>
    </x-page-header>

    @error('invoice')
        <x-alert type="danger" class="mb-4">{{ $message }}</x-alert>
    @enderror
    @if ($invoice->voided_at)
        <x-alert type="warning" class="mb-4">{{ __('invoices.details.voided', ['date' => $invoice->voided_at->format('j M Y'), 'reason' => $invoice->void_reason]) }}</x-alert>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <x-card class="lg:col-span-2">
            <div class="mb-4 flex flex-wrap items-center gap-3">
                <x-status-chip :status="$invoice->status" />
                <x-status-chip :status="$invoice->type" />
                @if ($invoice->isOverdue())
                    <span class="text-sm font-medium text-danger">{{ __('invoices.details.overdue_days', ['count' => (int) $invoice->due_date->diffInDays(today())]) }}</span>
                @endif
            </div>
            <dl class="grid gap-4 text-sm sm:grid-cols-2">
                <div><dt class="text-ink-muted">{{ __('invoices.details.billed_to') }}</dt><dd><a href="{{ route('app.tenants.show', $invoice->tenant) }}" class="font-medium text-primary-600 hover:underline">{{ $invoice->tenant->full_name }}</a></dd></div>
                @if ($invoice->unit)
                    <div><dt class="text-ink-muted">{{ __('invoices.details.unit') }}</dt><dd>{{ $invoice->unit->label }}, {{ $invoice->unit->property?->name }}</dd></div>
                @endif
                @if ($invoice->period_start)
                    <div><dt class="text-ink-muted">{{ __('invoices.details.period') }}</dt><dd>{{ $invoice->period_start->format('j M Y') }} to {{ $invoice->period_end?->format('j M Y') }}</dd></div>
                @endif
                <div><dt class="text-ink-muted">{{ __('invoices.details.issued') }}</dt><dd>{{ $invoice->issued_at?->format('j M Y') }}</dd></div>
                <div><dt class="text-ink-muted">{{ __('invoices.details.due') }}</dt><dd>{{ $invoice->due_date->format('j M Y') }}</dd></div>
                @if ($invoice->parent)
                    <div><dt class="text-ink-muted">{{ __('invoices.details.late_fee_for') }}</dt><dd><a href="{{ route('app.invoices.show', $invoice->parent) }}" class="text-primary-600 hover:underline">{{ $invoice->parent->number }}</a></dd></div>
                @endif
                @if ($invoice->lateFee)
                    <div><dt class="text-ink-muted">{{ __('invoices.details.late_fee') }}</dt><dd><a href="{{ route('app.invoices.show', $invoice->lateFee) }}" class="text-primary-600 hover:underline">{{ $invoice->lateFee->number }}</a></dd></div>
                @endif
            </dl>
        </x-card>

        <x-card>
            <dl class="space-y-3 text-sm">
                <div class="flex justify-between"><dt class="text-ink-muted">{{ __('invoices.details.total') }}</dt><dd class="tabular-nums">{{ $invoice->total()->format() }}</dd></div>
                <div class="flex justify-between"><dt class="text-ink-muted">{{ __('invoices.details.paid') }}</dt><dd class="tabular-nums">{{ $invoice->paid()->format() }}</dd></div>
                <div class="flex justify-between border-t border-border pt-3 text-base font-semibold"><dt>{{ __('invoices.details.balance') }}</dt><dd class="tabular-nums">{{ $invoice->outstanding()->format() }}</dd></div>
            </dl>
        </x-card>

        <x-card :title="__('invoices.details.payments')" class="lg:col-span-3">
            @if ($invoice->allocations->isEmpty())
                <p class="text-sm text-ink-muted">{{ __('invoices.details.no_payments') }}</p>
            @else
                <ul class="divide-y divide-border text-sm">
                    @foreach ($invoice->allocations as $allocation)
                        <li class="flex justify-between gap-4 py-2">
                            <span>{{ $allocation->payment->receipt_number ?? $allocation->payment->reference }} · {{ $allocation->payment->method->label() }} · {{ $allocation->payment->paid_at?->format('j M Y') }}</span>
                            <span class="tabular-nums">{{ \App\Support\Money::forOrganization($allocation->amount)->format() }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    </div>

    <x-confirm name="void-invoice" method="POST" :action="route('app.invoices.void', $invoice)"
        :title="__('invoices.void_title', ['number' => $invoice->number])" :consequences="__('invoices.void_consequences')" :confirm-label="__('invoices.void')">
        <div class="mt-4"><x-textarea name="reason" :label="__('invoices.fields.reason')" rows="2" required /></div>
    </x-confirm>
</x-layouts::admin>
