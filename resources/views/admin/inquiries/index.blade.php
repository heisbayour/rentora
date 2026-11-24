<x-layouts::admin :title="__('inquiries.title')">
    <x-page-header :title="__('inquiries.title')" :description="__('inquiries.description')" />

    <div class="mb-4 flex flex-wrap gap-2">
        <x-filter-chip :href="route('app.inquiries.index')" :active="$status === ''">{{ __('inquiries.all') }}</x-filter-chip>
        @foreach ($statuses as $value => $label)
            <x-filter-chip :href="route('app.inquiries.index', ['status' => $value])" :active="$status === $value">{{ $label }}</x-filter-chip>
        @endforeach
    </div>

    @if ($inquiries->isEmpty())
        <x-empty-state icon="inbox" :title="__('inquiries.empty_title')" :text="__('inquiries.empty_text')" />
    @else
        <ul class="space-y-3">
            @foreach ($inquiries as $inquiry)
                <li class="rounded-card border border-border bg-surface p-4 shadow-card">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="font-medium text-ink">{{ $inquiry->name }}</p>
                            <p class="text-sm text-ink-muted">
                                @if ($inquiry->phone)<a href="tel:{{ $inquiry->phone }}" class="hover:text-primary-600">{{ $inquiry->phone }}</a>@endif
                                @if ($inquiry->email) · <a href="mailto:{{ $inquiry->email }}" class="hover:text-primary-600">{{ $inquiry->email }}</a>@endif
                            </p>
                            <p class="text-xs text-ink-muted">{{ $inquiry->unit?->displayName() ?? __('inquiries.general') }} · {{ $inquiry->created_at->timezone(config('rentora.defaults.timezone'))->format('j M Y H:i') }}</p>
                        </div>
                        <form method="POST" action="{{ route('app.inquiries.update', $inquiry) }}" class="flex items-center gap-2" x-data>
                            @csrf
                            @method('PATCH')
                            <label for="status-{{ $inquiry->id }}" class="sr-only">{{ __('inquiries.status') }}</label>
                            <select id="status-{{ $inquiry->id }}" name="status" x-on:change="$el.form.requestSubmit()"
                                class="h-8 rounded-control border-border text-sm focus:border-primary-600 focus:ring-primary-600">
                                @foreach ($statuses as $value => $label)
                                    <option value="{{ $value }}" @selected($inquiry->status->value === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <noscript><x-button size="sm">{{ __('ui.save') }}</x-button></noscript>
                        </form>
                    </div>
                    <p class="mt-3 whitespace-pre-line text-sm text-ink">{{ $inquiry->message }}</p>
                </li>
            @endforeach
        </ul>
        <x-pagination :paginator="$inquiries" class="mt-4" />
    @endif
</x-layouts::admin>
