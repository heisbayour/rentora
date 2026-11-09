<x-layouts::admin :title="__('onboarding.title')">
    <div class="mx-auto max-w-2xl">
        <x-stepper :steps="$stepTitles" :current="$position" class="mb-6" />
        <x-card :title="__('onboarding.property.heading')" :description="__('onboarding.property.intro')">
            <form method="POST" action="{{ route('app.onboarding.store', $stepKey) }}" class="grid gap-5 sm:grid-cols-2">
                @csrf
                <x-input name="name" :label="__('properties.fields.name')" :value="$property->name" required />
                <x-input name="code" :label="__('properties.fields.code')" :value="$property->code" :hint="__('properties.fields.code_hint')" required class="[&_input]:uppercase" />
                <x-select name="type" :label="__('properties.fields.type')" :options="$types" :value="$property->type" />
                <x-input name="country" :label="__('properties.fields.country')" :value="$property->country" required />
                <x-input name="address" :label="__('properties.fields.address')" :value="$property->address" required class="sm:col-span-2" />
                <x-input name="city" :label="__('properties.fields.city')" :value="$property->city" optional />
                <x-input name="state" :label="__('properties.fields.state')" :value="$property->state" optional />
                <div class="flex justify-end sm:col-span-2">
                    <x-button>{{ __('onboarding.continue') }}</x-button>
                </div>
            </form>
        </x-card>
    </div>
</x-layouts::admin>
