<?php

declare(strict_types=1);

namespace App\Onboarding;

use Illuminate\Http\Request;

/** One page of the first-run wizard (spec 6.5.1). */
interface OnboardingStep
{
    public function key(): string;

    public function title(): string;

    public function skippable(): bool;

    public function view(): string;

    /** @return array<string, mixed> */
    public function data(): array;

    /** @return array<string, mixed> */
    public function rules(): array;

    public function handle(Request $request): void;
}
