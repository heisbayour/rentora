<x-layouts::admin :title="__('onboarding.title')">
    <div class="mx-auto max-w-4xl">
        <x-stepper :steps="$stepTitles" :current="$position" class="mb-6" />
        <h1 class="text-lg font-semibold text-ink">{{ __('onboarding.units.heading', ['property' => $property->name]) }}</h1>
        <p class="mb-5 mt-1 text-sm text-ink-muted">{{ __('onboarding.units.intro', ['max' => $max]) }}</p>
        @include('admin.units._quick-add-form', [
            'formAction' => route('app.onboarding.store', $stepKey),
            'submitLabel' => __('onboarding.continue'),
            'cancelUrl' => null,
        ])
    </div>
</x-layouts::admin>
