{{-- Tenant portal (/portal): mobile first, bottom navigation under 768px, top navigation on desktop. --}}
@props([
    'title' => null,
    'brandColor' => null,
    'manifest' => null,
])

@php
    $user = auth()->user();
    $appName = app(\App\Support\CurrentOrganization::class)->get()?->name ?? config('rentora.name');
    $items = [
        ['key' => 'home', 'route' => 'portal.home', 'icon' => 'home'],
        ['key' => 'pay', 'route' => 'portal.pay', 'icon' => 'credit-card'],
        ['key' => 'repairs', 'route' => 'portal.repairs.index', 'icon' => 'wrench-screwdriver'],
        ['key' => 'inbox', 'route' => 'portal.inbox', 'icon' => 'inbox'],
        ['key' => 'account', 'route' => 'portal.account', 'icon' => 'user-circle'],
    ];
    $nav = array_map(function (array $item) {
        $exists = Route::has($item['route']);

        return $item + [
            'label' => __('nav.portal.'.$item['key']),
            'href' => $exists ? route($item['route']) : '#',
            'active' => $exists && request()->routeIs(\Illuminate\Support\Str::beforeLast($item['route'], '.').'*'),
        ];
    }, $items);
@endphp

<x-layouts::base :title="$title" :brand-color="$brandColor" :manifest="$manifest" body-class="bg-canvas">
    <header class="sticky top-0 z-20 border-b border-border bg-surface/95 backdrop-blur">
        <div class="mx-auto flex h-14 max-w-portal items-center gap-4 px-4">
            <span class="text-base font-semibold text-ink">{{ $appName }}</span>
            <nav class="ml-auto hidden items-center gap-1 md:flex">
                @foreach ($nav as $item)
                    <a href="{{ $item['href'] }}" @if ($item['active']) aria-current="page" @endif
                        @class([
                            'rounded-control px-3 py-2 text-sm font-medium',
                            'bg-primary-50 text-primary-700' => $item['active'],
                            'text-ink-muted hover:bg-canvas hover:text-ink' => ! $item['active'],
                        ])>{{ $item['label'] }}</a>
                @endforeach
            </nav>
            @isset($headerActions)
                <div class="ml-auto md:ml-2">{{ $headerActions }}</div>
            @endisset
        </div>
    </header>

    <main id="main" class="mx-auto max-w-portal px-4 pb-28 pt-6 md:pb-10">
        {{ $slot }}
    </main>

    <nav class="fixed inset-x-0 bottom-0 z-30 border-t border-border bg-surface pb-[env(safe-area-inset-bottom)] md:hidden" aria-label="{{ $appName }}">
        <ul class="grid grid-cols-5">
            @foreach ($nav as $item)
                <li>
                    <a href="{{ $item['href'] }}" @if ($item['active']) aria-current="page" @endif
                        @class([
                            'flex h-16 flex-col items-center justify-center gap-1 text-xs font-medium',
                            'text-primary-600' => $item['active'],
                            'text-ink-muted' => ! $item['active'],
                        ])>
                        <x-dynamic-component :component="'heroicon-o-'.$item['icon']" class="h-6 w-6" aria-hidden="true" />
                        {{ $item['label'] }}
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>
    @isset($overlays)
        {{ $overlays }}
    @endisset
</x-layouts::base>
