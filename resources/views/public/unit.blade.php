<x-layouts::public :title="$unit->displayName()" :description="$unit->type->label().' - '.$unit->rent()->format().' '.$unit->rent_frequency->label()"
    :org-name="$organization->name" :logo="$organization->logoUrl()" :contact="$contact">
    <section class="mx-auto max-w-6xl px-4 py-10 sm:px-6">
        <a href="{{ route('public.listings') }}" class="mb-4 inline-flex items-center gap-1 text-sm text-ink-muted hover:text-ink">
            <x-heroicon-m-arrow-left class="h-4 w-4" aria-hidden="true" /> {{ __('public.listings_title') }}
        </a>

        <div class="grid grid-cols-1 gap-8 lg:grid-cols-5">
            <div class="lg:col-span-3">
                @if ($unit->photos->isNotEmpty())
                    <div x-data="{ active: 0 }">
                        <div class="aspect-[4/3] overflow-hidden rounded-card bg-canvas">
                            @foreach ($unit->photos as $i => $photo)
                                <img x-show="active === {{ $i }}" @if ($i > 0) x-cloak @endif src="{{ $photo->url() }}" alt="{{ __('public.photo', ['number' => $i + 1]) }}" class="h-full w-full object-cover">
                            @endforeach
                        </div>
                        @if ($unit->photos->count() > 1)
                            <div class="mt-3 grid grid-cols-5 gap-2">
                                @foreach ($unit->photos as $i => $photo)
                                    <button type="button" x-on:click="active = {{ $i }}" class="overflow-hidden rounded-control border-2" :class="active === {{ $i }} ? 'border-primary-600' : 'border-transparent'">
                                        <img src="{{ $photo->url() }}" alt="{{ __('public.photo', ['number' => $i + 1]) }}" class="aspect-square w-full object-cover" loading="lazy">
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @else
                    <div class="flex aspect-[4/3] items-center justify-center rounded-card bg-canvas text-ink-muted"><x-heroicon-o-home class="h-16 w-16" aria-hidden="true" /></div>
                @endif

                <h1 class="mt-6 text-2xl font-semibold text-ink">{{ $unit->type->label() }} · {{ $unit->label }}</h1>
                <p class="text-ink-muted">{{ $unit->property->name }}, {{ $unit->property->fullAddress() }}</p>
                <dl class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <div><dt class="text-sm text-ink-muted">{{ __('public.rent') }}</dt><dd class="font-semibold tabular-nums">{{ $unit->rent()->format() }}</dd><dd class="text-xs text-ink-muted">{{ $unit->rent_frequency->label() }}</dd></div>
                    <div><dt class="text-sm text-ink-muted">{{ __('public.filters.bedrooms') }}</dt><dd class="font-semibold">{{ $unit->bedrooms }}</dd></div>
                    <div><dt class="text-sm text-ink-muted">{{ __('public.bathrooms') }}</dt><dd class="font-semibold">{{ $unit->bathrooms }}</dd></div>
                    @if ($unit->service_charge_amount > 0)
                        <div><dt class="text-sm text-ink-muted">{{ __('public.service_charge') }}</dt><dd class="font-semibold tabular-nums">{{ $unit->serviceCharge()->format() }}</dd></div>
                    @endif
                </dl>
                @if ($unit->description)
                    <p class="mt-6 whitespace-pre-line text-ink">{{ $unit->description }}</p>
                @endif
            </div>

            <aside class="lg:col-span-2">
                <x-card :title="__('public.inquiry.title')" :description="__('public.inquiry.intro')">
                    <form method="POST" action="{{ route('public.inquire', $unit) }}" class="space-y-4">
                        @csrf
                        <div class="hidden" aria-hidden="true">
                            <label for="website">Website</label>
                            <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                        </div>
                        <x-input name="name" :label="__('public.inquiry.name')" required />
                        <x-input name="phone" type="tel" :label="__('public.inquiry.phone')" />
                        <x-input name="email" type="email" :label="__('public.inquiry.email')" />
                        <x-textarea name="message" :label="__('public.inquiry.message')" rows="4" :value="__('public.inquiry.default_message', ['unit' => $unit->displayName()])" required />
                        <x-button class="w-full" icon="paper-airplane">{{ __('public.inquiry.send') }}</x-button>
                    </form>
                </x-card>
            </aside>
        </div>
    </section>
</x-layouts::public>
