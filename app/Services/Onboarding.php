<?php

declare(strict_types=1);

namespace App\Services;

use App\Onboarding\OnboardingStep;
use App\Onboarding\OrganizationStep;

/**
 * First-run wizard progress, stored in settings so it resumes where the owner
 * left off. Steps are added here as the modules they create are built.
 */
class Onboarding
{
    public const DONE_KEY = 'onboarding.done';

    public const COMPLETED_KEY = 'onboarding.completed_at';

    /** @var list<class-string<OnboardingStep>> */
    private const STEPS = [
        OrganizationStep::class,
    ];

    public function __construct(private readonly SettingsService $settings) {}

    /** @return list<OnboardingStep> */
    public function steps(): array
    {
        return array_map(fn (string $class) => app($class), self::STEPS);
    }

    public function step(string $key): ?OnboardingStep
    {
        foreach ($this->steps() as $step) {
            if ($step->key() === $key) {
                return $step;
            }
        }

        return null;
    }

    /** 1-based position of a step, for the stepper. */
    public function position(string $key): int
    {
        $keys = array_map(fn (OnboardingStep $step) => $step->key(), $this->steps());
        $index = array_search($key, $keys, true);

        return $index === false ? 1 : $index + 1;
    }

    public function isComplete(): bool
    {
        return $this->settings->has(self::COMPLETED_KEY);
    }

    public function currentStep(): string
    {
        $done = $this->done();
        foreach ($this->steps() as $step) {
            if (! in_array($step->key(), $done, true)) {
                return $step->key();
            }
        }

        return $this->steps()[0]->key();
    }

    /** Marks a step done (completed or skipped) and returns the next step key, or null when finished. */
    public function advance(string $key): ?string
    {
        $done = array_values(array_unique([...$this->done(), $key]));
        $this->settings->set(self::DONE_KEY, (string) json_encode($done));

        foreach ($this->steps() as $step) {
            if (! in_array($step->key(), $done, true)) {
                return $step->key();
            }
        }
        $this->settings->set(self::COMPLETED_KEY, now()->toIso8601String());

        return null;
    }

    /** @return list<string> */
    private function done(): array
    {
        $decoded = json_decode((string) $this->settings->get(self::DONE_KEY, '[]'), true);

        return is_array($decoded) ? array_values(array_filter($decoded, 'is_string')) : [];
    }
}
