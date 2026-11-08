{{-- Landlord console (/app): collapsible sidebar, top bar with search, notifications and user menu. --}}
@props([
    'title' => null,
    'brandColor' => null,
])

@php
    $user = auth()->user();
    $role = $user?->role instanceof \App\Enums\Role ? $user->role : null;
    $groups = \App\Support\AdminNavigation::for($role);
    $appName = config('rentora.name');
@endphp

<x-layouts::base :title="$title" :brand-color="$brandColor" body-class="bg-canvas">
    <div class="min-h-full" x-data>
        {{-- Mobile backdrop --}}
        <div x-show="$store.sidebar.mobileOpen" x-cloak x-transition.opacity class="fixed inset-0 z-30 bg-ink/40 lg:hidden"
            @click="$store.sidebar.mobileOpen = false" aria-hidden="true"></div>

        {{-- Sidebar --}}
        <aside class="fixed inset-y-0 left-0 z-40 flex flex-col border-r border-border bg-surface transition-all duration-200"
            :class="[
                $store.sidebar.collapsed ? 'lg:w-rail' : 'lg:w-sidebar',
                $store.sidebar.mobileOpen ? 'w-sidebar translate-x-0' : 'w-sidebar -translate-x-full lg:translate-x-0'
            ]"
            aria-label="{{ __('nav.groups.overview') }}">
            <div class="flex h-16 items-center gap-3 border-b border-border px-4">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-control bg-primary-600 text-sm font-semibold text-white">
                    {{ mb_substr($appName, 0, 1) }}
                </span>
                <span class="truncate text-base font-semibold text-ink" x-show="!$store.sidebar.collapsed || $store.sidebar.mobileOpen">{{ $appName }}</span>
            </div>
            <nav class="flex-1 overflow-y-auto px-3 py-4">
                @foreach ($groups as $group)
                    <div class="mb-5">
                        <p class="mb-1 px-3 text-xs font-medium uppercase tracking-wide text-ink-muted"
                            x-show="!$store.sidebar.collapsed || $store.sidebar.mobileOpen">{{ $group['label'] }}</p>
                        <ul class="space-y-0.5">
                            @foreach ($group['items'] as $item)
                                <li>
                                    <a href="{{ $item['href'] ?? '#' }}" @if ($item['active']) aria-current="page" @endif
                                        title="{{ $item['label'] }}"
                                        @class([
                                            'flex h-10 items-center gap-3 rounded-control px-3 text-sm font-medium transition-colors',
                                            'bg-primary-50 text-primary-700' => $item['active'],
                                            'text-ink hover:bg-canvas' => ! $item['active'],
                                            'pointer-events-none opacity-50' => $item['href'] === null,
                                        ])>
                                        <x-dynamic-component :component="'heroicon-o-'.$item['icon']" class="h-6 w-6 shrink-0" aria-hidden="true" />
                                        <span class="truncate" x-show="!$store.sidebar.collapsed || $store.sidebar.mobileOpen">{{ $item['label'] }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </nav>
            <div class="hidden border-t border-border p-3 lg:block">
                <button type="button" @click="$store.sidebar.toggle()"
                    class="flex h-10 w-full items-center gap-3 rounded-control px-3 text-sm text-ink-muted hover:bg-canvas"
                    :aria-label="$store.sidebar.collapsed ? @js(__('ui.expand_sidebar')) : @js(__('ui.collapse_sidebar'))">
                    <x-heroicon-o-chevron-double-left class="h-5 w-5 shrink-0 transition-transform" x-bind:class="$store.sidebar.collapsed && 'rotate-180'" aria-hidden="true" />
                    <span x-show="!$store.sidebar.collapsed">{{ __('ui.collapse_sidebar') }}</span>
                </button>
            </div>
        </aside>

        {{-- Main column --}}
        <div class="transition-all duration-200" :class="$store.sidebar.collapsed ? 'lg:pl-rail' : 'lg:pl-sidebar'">
            <header class="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-border bg-surface/95 px-4 backdrop-blur sm:px-6">
                <button type="button" class="rounded-control p-2 text-ink-muted hover:bg-canvas lg:hidden" @click="$store.sidebar.mobileOpen = true">
                    <span class="sr-only">{{ __('ui.open_menu') }}</span>
                    <x-heroicon-o-bars-3 class="h-6 w-6" aria-hidden="true" />
                </button>

                <button type="button" @click="$dispatch('open-search')"
                    @keydown.window.ctrl.k.prevent="$dispatch('open-search')" @keydown.window.meta.k.prevent="$dispatch('open-search')"
                    class="flex h-10 flex-1 items-center gap-3 rounded-control border border-border bg-canvas px-3 text-left text-sm text-ink-muted hover:border-primary-600 sm:max-w-md">
                    <x-heroicon-m-magnifying-glass class="h-5 w-5 shrink-0" aria-hidden="true" />
                    <span class="flex-1 truncate">{{ __('ui.search_placeholder') }}</span>
                    <kbd class="hidden rounded border border-border bg-surface px-1.5 text-xs sm:inline">{{ __('ui.search_shortcut') }}</kbd>
                </button>

                <div class="ml-auto flex items-center gap-1">
                    @isset($notifications)
                        {{ $notifications }}
                    @else
                        <x-dropdown width="w-80" :label="__('ui.notifications')">
                            <x-slot:trigger class="relative p-2 text-ink-muted hover:bg-canvas">
                                <x-heroicon-o-bell class="h-6 w-6" aria-hidden="true" />
                            </x-slot:trigger>
                            <p class="px-4 py-6 text-center text-sm text-ink-muted">{{ __('ui.no_notifications') }}</p>
                        </x-dropdown>
                    @endisset

                    @if ($user)
                        <x-dropdown :label="__('ui.account_menu')">
                            <x-slot:trigger class="p-1 hover:bg-canvas">
                                <x-avatar :name="$user->name" size="sm" />
                            </x-slot:trigger>
                            <div class="border-b border-border px-4 py-3">
                                <p class="truncate text-sm font-medium text-ink">{{ $user->name }}</p>
                                <p class="truncate text-xs text-ink-muted">{{ $user->email }}</p>
                            </div>
                            @if (Route::has('app.profile.edit'))
                                <x-dropdown.item :href="route('app.profile.edit')" icon="user-circle">{{ __('ui.profile') }}</x-dropdown.item>
                            @endif
                            @if (Route::has('logout'))
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <x-dropdown.item type="submit" icon="arrow-right-start-on-rectangle">{{ __('ui.log_out') }}</x-dropdown.item>
                                </form>
                            @endif
                        </x-dropdown>
                    @endif
                </div>
            </header>

            <main id="main" class="px-4 py-6 sm:px-6 lg:px-8">
                {{ $slot }}
            </main>
        </div>
    </div>
    @isset($overlays)
        {{ $overlays }}
    @endisset
</x-layouts::base>
