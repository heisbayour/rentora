<x-layouts::admin :title="__('onboarding.title')">
    <div class="mx-auto max-w-2xl">
        <x-stepper :steps="$stepTitles" :current="$position" class="mb-6" />
        <x-card :title="__('onboarding.tenant.heading')" :description="__('onboarding.tenant.intro')">
            <form method="POST" action="{{ route('app.onboarding.store', $stepKey) }}" class="grid gap-5 sm:grid-cols-2">
                @csrf
                <x-input name="full_name" :label="__('tenants.fields.full_name')" class="sm:col-span-2" />
                <x-input name="phone" type="tel" :label="__('tenants.fields.phone')" />
                <x-input name="email" type="email" :label="__('tenants.fields.email')" optional />
                <div class="flex justify-end gap-2 sm:col-span-2">
                    <x-button type="submit" variant="ghost" name="skip" value="1" formnovalidate>{{ __('onboarding.skip') }}</x-button>
                    <x-button>{{ __('onboarding.tenant.submit') }}</x-button>
                </div>
            </form>
        </x-card>
    </div>
</x-layouts::admin>
