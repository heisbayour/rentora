<x-layouts::portal :title="__('portal.invoices.title')">
    <h1 class="mb-4 text-xl font-semibold text-ink">{{ __('portal.invoices.title') }}</h1>

    @if ($invoices->isEmpty())
        <x-empty-state icon="document-text" :title="__('portal.invoices.empty_title')" :text="__('portal.invoices.empty_text')" />
    @else
        <ul class="space-y-3">
            @foreach ($invoices as $invoice)
                <li>
                    <a href="{{ route('portal.invoices.show', $invoice) }}" class="block rounded-card border border-border bg-surface p-4 shadow-card hover:border-primary-600">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-medium text-ink">{{ $invoice->number }}</p>
                                <p class="text-xs text-ink-muted">{{ $invoice->description ?? $invoice->type->label() }} · {{ __('portal.pay.due', ['date' => $invoice->due_date->format('j M Y')]) }}</p>
                            </div>
                            <div class="text-right">
                                <p class="font-semibold tabular-nums">{{ $invoice->total()->format() }}</p>
                                <x-status-chip :status="$invoice->status" />
                            </div>
                        </div>
                    </a>
                </li>
            @endforeach
        </ul>
        <x-pagination :paginator="$invoices" class="mt-4" />
    @endif
</x-layouts::portal>
