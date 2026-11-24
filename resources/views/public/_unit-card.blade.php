<a href="{{ route('public.unit', $unit) }}" class="group block overflow-hidden rounded-card border border-border bg-surface shadow-card hover:border-primary-600">
    <div class="aspect-[4/3] bg-canvas">
        @if ($unit->photos->isNotEmpty())
            <img src="{{ $unit->photos->first()->url() }}" alt="{{ $unit->displayName() }}" class="h-full w-full object-cover" loading="lazy">
        @elseif ($unit->property->coverUrl())
            <img src="{{ $unit->property->coverUrl() }}" alt="{{ $unit->property->name }}" class="h-full w-full object-cover" loading="lazy">
        @else
            <div class="flex h-full items-center justify-center text-ink-muted"><x-heroicon-o-home class="h-10 w-10" aria-hidden="true" /></div>
        @endif
    </div>
    <div class="p-4">
        <p class="text-lg font-semibold tabular-nums text-ink">{{ $unit->rent()->format() }} <span class="text-sm font-normal text-ink-muted">{{ $unit->rent_frequency->label() }}</span></p>
        <p class="mt-1 font-medium text-ink group-hover:text-primary-600">{{ $unit->type->label() }} · {{ trans_choice('public.bedrooms', $unit->bedrooms, ['count' => $unit->bedrooms]) }}</p>
        <p class="text-sm text-ink-muted">{{ $unit->property->name }}@if ($unit->property->city), {{ $unit->property->city }}@endif</p>
    </div>
</a>
