<x-layouts::admin :title="__('onboarding.title')">
    <div class="mx-auto max-w-2xl">
        <x-stepper :steps="$stepTitles" :current="$position" class="mb-6" />
        <x-card :title="__('onboarding.organization.heading')" :description="__('onboarding.organization.intro')">
            <form method="POST" action="{{ route('app.onboarding.store', $stepKey) }}" enctype="multipart/form-data" class="grid gap-5 sm:grid-cols-2">
                @csrf
                <x-input name="name" :label="__('onboarding.organization.name')" :value="$organization->name" required class="sm:col-span-2" />
                <x-input name="email" type="email" :label="__('onboarding.organization.email')" :value="$organization->email" optional />
                <x-input name="phone" type="tel" :label="__('onboarding.organization.phone')" :value="$organization->phone" optional />
                <x-textarea name="address" :label="__('onboarding.organization.address')" :value="$organization->address" rows="2" optional class="sm:col-span-2" />
                <x-select name="timezone" :label="__('onboarding.organization.timezone')" :options="$timezones" :value="$organization->timezone" />
                <x-select name="currency" :label="__('onboarding.organization.currency')" :options="$currencies" :value="$organization->currency" />
                <x-input name="primary_color" type="color" :label="__('onboarding.organization.primary_color')" :value="$organization->primary_color" class="[&_input]:p-1" />
                <x-file-dropzone name="logo" :label="__('onboarding.organization.logo')" accept="image/jpeg,image/png,image/webp" class="sm:col-span-2" />
                <div class="flex justify-end sm:col-span-2">
                    <x-button>{{ __('onboarding.continue') }}</x-button>
                </div>
            </form>
        </x-card>
    </div>
</x-layouts::admin>
