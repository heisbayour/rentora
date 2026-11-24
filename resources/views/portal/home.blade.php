<x-layouts::portal :title="__('portal.home.title')">
    <h1 class="text-xl font-semibold text-ink">{{ __('portal.home.greeting', ['name' => auth()->user()->name]) }}</h1>

    <x-portal-install-prompt class="mt-4" />

    <section class="mt-4 rounded-card bg-primary-600 p-5 text-white shadow-card">
        <p class="text-sm opacity-90">{{ __('portal.home.balance') }}</p>
        <p class="mt-1 text-3xl font-semibold tabular-nums">{{ $outstanding->format() }}</p>
        @if ($nextInvoice)
            <p class="mt-1 text-sm opacity-90">{{ __('portal.home.next_due', ['number' => $nextInvoice->number, 'date' => $nextInvoice->due_date->format('j M Y')]) }}</p>
        @else
            <p class="mt-1 text-sm opacity-90">{{ __('portal.home.all_paid') }}</p>
        @endif
        @if ($credit->isPositive())
            <p class="mt-1 text-sm opacity-90">{{ __('portal.home.credit', ['amount' => $credit->format()]) }}</p>
        @endif
        <a href="{{ route('portal.pay') }}" class="mt-4 inline-flex h-10 items-center rounded-control bg-white px-4 text-sm font-semibold text-primary-700 hover:bg-primary-50">{{ __('portal.home.pay_now') }}</a>
    </section>

    <nav class="mt-4 grid grid-cols-3 gap-2 sm:grid-cols-5" aria-label="{{ __('portal.account.more') }}">
        @foreach (['invoices' => ['portal.invoices.index', 'document-text'], 'payments' => ['portal.payments.index', 'banknotes'], 'statement' => ['portal.statement', 'document-chart-bar'], 'documents' => ['portal.documents', 'folder'], 'notices' => ['portal.notices', 'megaphone']] as $key => [$route, $icon])
            <a href="{{ route($route) }}" class="flex flex-col items-center gap-1 rounded-card border border-border bg-surface p-3 text-xs font-medium text-ink hover:border-primary-600">
                <x-dynamic-component :component="'heroicon-o-'.$icon" class="h-6 w-6 text-primary-600" aria-hidden="true" />
                {{ __('portal.home.quick.'.$key) }}
            </a>
        @endforeach
    </nav>

    <div class="mt-4 space-y-4">
        <x-card :title="__('portal.home.lease')">
            @if ($lease)
                <p class="font-medium text-ink">{{ $lease->unit->displayName() }}</p>
                <p class="text-sm text-ink-muted">{{ __('portal.home.lease_dates', ['start' => $lease->start_date->format('j M Y'), 'end' => $lease->end_date->format('j M Y')]) }}</p>
                <p class="mt-2 text-sm"><span class="text-ink-muted">{{ __('portal.home.rent') }}:</span> <span class="tabular-nums">{{ $lease->rent()->format() }}</span> {{ $lease->rent_frequency->label() }}</p>
            @else
                <p class="text-sm text-ink-muted">{{ __('portal.home.no_lease') }}</p>
            @endif
        </x-card>

        <x-card :title="__('portal.home.repairs')">
            <x-slot:actions>
                <x-button size="sm" variant="secondary" :href="route('portal.repairs.create')" icon="plus">{{ __('portal.home.report_repair') }}</x-button>
            </x-slot:actions>
            @forelse ($repairs as $repair)
                <a href="{{ route('portal.repairs.show', $repair) }}" class="flex items-center justify-between gap-3 py-2 text-sm hover:text-primary-600">
                    <span class="truncate">{{ $repair->title }}</span>
                    <x-status-chip :status="$repair->status" />
                </a>
            @empty
                <p class="text-sm text-ink-muted">{{ __('portal.home.no_repairs') }}</p>
            @endforelse
        </x-card>

        <x-card :title="__('portal.home.notices')">
            <x-slot:actions>
                <a href="{{ route('portal.notices') }}" class="text-sm font-medium text-primary-600 hover:underline">{{ __('portal.home.view_all') }}</a>
            </x-slot:actions>
            @forelse ($notices as $notice)
                <div class="py-2">
                    <p class="text-sm font-medium text-ink">{{ $notice->title }}</p>
                    <p class="line-clamp-2 text-sm text-ink-muted">{{ $notice->body }}</p>
                </div>
            @empty
                <p class="text-sm text-ink-muted">{{ __('portal.home.no_notices') }}</p>
            @endforelse
        </x-card>
    </div>
</x-layouts::portal>
