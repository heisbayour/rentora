<x-layouts::portal :title="__('portal.account.title')">
    <h1 class="mb-4 text-xl font-semibold text-ink">{{ __('portal.account.title') }}</h1>

    <div class="space-y-4">
        <x-card :title="__('portal.account.profile')">
            <form method="POST" action="{{ route('portal.account.update') }}" class="space-y-4">
                @csrf
                @method('PUT')
                <x-input name="name" :label="__('portal.account.name')" :value="$user->name" required />
                <x-input name="phone" type="tel" :label="__('portal.account.phone')" :value="$tenant?->phone ?? $user->phone" required />
                <x-input name="email_display" type="email" :label="__('portal.account.email')" :value="$user->email" :hint="__('portal.account.email_hint')" disabled />
                <x-button>{{ __('portal.account.save') }}</x-button>
            </form>
        </x-card>

        <x-card :title="__('profile.password')">
            <form method="POST" action="{{ route('user-password.update') }}" class="space-y-4">
                @csrf
                @method('PUT')
                <x-input name="current_password" type="password" bag="updatePassword" :label="__('profile.current_password')" autocomplete="current-password" required />
                <x-input name="password" type="password" bag="updatePassword" :label="__('profile.new_password')" autocomplete="new-password" required />
                <x-input name="password_confirmation" type="password" bag="updatePassword" :label="__('profile.confirm_password')" autocomplete="new-password" required />
                <x-button>{{ __('profile.update_password') }}</x-button>
            </form>
        </x-card>

        <x-card :title="__('portal.account.more')">
            <nav class="grid gap-1 text-sm">
                <a href="{{ route('portal.invoices.index') }}" class="rounded-control px-2 py-2 hover:bg-canvas">{{ __('portal.home.quick.invoices') }}</a>
                <a href="{{ route('portal.payments.index') }}" class="rounded-control px-2 py-2 hover:bg-canvas">{{ __('portal.home.quick.payments') }}</a>
                <a href="{{ route('portal.statement') }}" class="rounded-control px-2 py-2 hover:bg-canvas">{{ __('portal.home.quick.statement') }}</a>
                <a href="{{ route('portal.documents') }}" class="rounded-control px-2 py-2 hover:bg-canvas">{{ __('portal.home.quick.documents') }}</a>
                <a href="{{ route('portal.notices') }}" class="rounded-control px-2 py-2 hover:bg-canvas">{{ __('portal.home.quick.notices') }}</a>
            </nav>
            <form method="POST" action="{{ route('logout') }}" class="mt-4">
                @csrf
                <x-button variant="secondary" icon="arrow-right-start-on-rectangle">{{ __('ui.log_out') }}</x-button>
            </form>
        </x-card>
    </div>
</x-layouts::portal>
