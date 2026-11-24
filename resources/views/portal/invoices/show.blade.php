<x-layouts::portal :title="$invoice->number">
    <a href="{{ route('portal.invoices.index') }}" class="mb-4 inline-flex items-center gap-1 text-sm text-ink-muted hover:text-ink">
        <x-heroicon-m-arrow-left class="h-4 w-4" aria-hidden="true" /> {{ __('portal.invoices.title') }}
    </a>

    <x-card>
        <div class="flex items-start justify-between gap-3">
            <div>
                <h1 class="text-lg font-semibold text-ink">{{ $invoice->number }}</h1>
                <p class="text-sm text-ink-muted">{{ $invoice->description ?? $invoice->type->label() }}</p>
                @if ($invoice->unit)
                    <p class="text-xs text-ink-muted">{{ $invoice->unit->displayName() }}</p>
                @endif
            </div>
            <x-status-chip :status="$invoice->status" />
        </div>

        <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
            <div><dt class="text-ink-muted">{{ __('portal.invoices.due_date') }}</dt><dd class="font-medium">{{ $invoice->due_date->format('j M Y') }}</dd></div>
            @if ($invoice->period_start)
                <div><dt class="text-ink-muted">{{ __('portal.invoices.period') }}</dt><dd class="font-medium">{{ $invoice->period_start->format('j M Y') }} - {{ $invoice->period_end?->format('j M Y') }}</dd></div>
            @endif
            <div><dt class="text-ink-muted">{{ __('portal.invoices.amount') }}</dt><dd class="font-medium tabular-nums">{{ $invoice->total()->format() }}</dd></div>
            <div><dt class="text-ink-muted">{{ __('portal.invoices.paid') }}</dt><dd class="font-medium tabular-nums">{{ $invoice->paid()->format() }}</dd></div>
            <div class="col-span-2"><dt class="text-ink-muted">{{ __('portal.invoices.balance') }}</dt><dd class="text-xl font-semibold tabular-nums">{{ $invoice->outstanding()->format() }}</dd></div>
        </dl>

        <div class="mt-5 flex flex-wrap gap-2">
            @if ($invoice->outstandingMinor() > 0)
                <x-button :href="route('portal.pay')" icon="credit-card">{{ __('portal.invoices.pay') }}</x-button>
            @endif
            <x-button variant="secondary" :href="route('portal.invoices.pdf', $invoice)" icon="arrow-down-tray">{{ __('portal.invoices.download') }}</x-button>
        </div>
    </x-card>
</x-layouts::portal>
