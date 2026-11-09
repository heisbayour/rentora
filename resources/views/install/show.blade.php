@php
    $current = ['requirements' => 1, 'migrate' => 2, 'organization' => 3][$stage];
@endphp

<x-layouts::install :title="__('install.title')" :steps="__('install.steps')" :current="$current">
    @if ($debugWarning)
        <x-alert type="warning" class="mb-6">{{ __('install.debug_warning') }}</x-alert>
    @endif

    @if ($stage === 'requirements')
        <h1 class="text-lg font-semibold text-ink">{{ __('install.requirements') }}</h1>
        <p class="mt-1 text-sm text-ink-muted">{{ __('install.requirements_intro') }}</p>
        <ul class="mt-5 divide-y divide-border rounded-control border border-border">
            @foreach ($checks as $check)
                <li class="flex items-center justify-between gap-4 px-4 py-2.5 text-sm">
                    <span class="text-ink">{{ $check['label'] }}</span>
                    <x-status-chip :label="$check['detail']" :color="$check['ok'] ? 'success' : 'danger'" />
                </li>
            @endforeach
            <li class="flex items-center justify-between gap-4 px-4 py-2.5 text-sm">
                <span class="text-ink">{{ __('install.database') }}</span>
                <x-status-chip :label="$databaseError ? __('install.missing') : __('install.loaded')" :color="$databaseError ? 'danger' : 'success'" />
            </li>
        </ul>
        @if ($databaseError)
            <x-alert type="danger" :title="__('install.database_failed')" class="mt-4">
                <span class="break-all font-mono text-xs">{{ $databaseError }}</span>
            </x-alert>
        @endif
        <x-button :href="route('install.show', ['key' => $key])" variant="secondary" class="mt-6">{{ __('install.retry') }}</x-button>
    @elseif ($stage === 'migrate')
        <h1 class="text-lg font-semibold text-ink">{{ __('install.migrate_title') }}</h1>
        <p class="mt-1 text-sm text-ink-muted">{{ __('install.migrate_intro') }}</p>
        <x-alert type="success" class="mt-4">{{ __('install.database_ok') }}</x-alert>
        <form method="POST" action="{{ route('install.migrate') }}" class="mt-6">
            @csrf
            <input type="hidden" name="key" value="{{ $key }}">
            <x-button>{{ __('install.migrate') }}</x-button>
        </form>
    @else
        <h1 class="text-lg font-semibold text-ink">{{ __('install.organization_title') }}</h1>
        <p class="mt-1 text-sm text-ink-muted">{{ __('install.organization_intro') }}</p>
        <form method="POST" action="{{ route('install.organization') }}" class="mt-6 grid gap-5 sm:grid-cols-2">
            @csrf
            <input type="hidden" name="key" value="{{ $key }}">
            <x-input name="organization_name" :label="__('install.organization_name')" required class="sm:col-span-2" />
            <x-input name="name" :label="__('install.owner_name')" required autocomplete="name" />
            <x-input name="phone" type="tel" :label="__('install.owner_phone')" optional autocomplete="tel" />
            <x-input name="email" type="email" :label="__('install.owner_email')" required autocomplete="username" class="sm:col-span-2" />
            <x-input name="password" type="password" :label="__('install.owner_password')" :hint="__('install.password_hint')" required autocomplete="new-password" />
            <x-input name="password_confirmation" type="password" :label="__('install.owner_password_confirm')" required autocomplete="new-password" />
            <div class="sm:col-span-2">
                <x-button>{{ __('install.finish') }}</x-button>
            </div>
        </form>
    @endif
</x-layouts::install>
