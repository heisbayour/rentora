<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OnboardingStepRequest;
use App\Onboarding\OnboardingStep;
use App\Services\Onboarding;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/** First-run wizard for the owner (spec 6.5.1). Progress is saved after every step. */
class OnboardingController extends Controller
{
    public function __construct(private readonly Onboarding $onboarding) {}

    public function show(string $step): View
    {
        $this->authorize('manage-settings');
        $current = $this->resolve($step);

        return view($current->view(), $current->data() + [
            'step' => $current,
            'stepKey' => $current->key(),
            'stepTitles' => array_map(fn (OnboardingStep $s) => $s->title(), $this->onboarding->steps()),
            'position' => $this->onboarding->position($current->key()),
        ]);
    }

    public function store(OnboardingStepRequest $request, string $step): RedirectResponse
    {
        $current = $this->resolve($step);

        if (! ($request->boolean('skip') && $current->skippable())) {
            $current->handle($request);
        }

        $next = $this->onboarding->advance($current->key());

        return $next === null
            ? redirect()->route('app.dashboard')->with('toast', __('onboarding.finished'))
            : redirect()->route('app.onboarding.show', $next);
    }

    private function resolve(string $step): OnboardingStep
    {
        return $this->onboarding->step($step) ?? abort(404);
    }
}
