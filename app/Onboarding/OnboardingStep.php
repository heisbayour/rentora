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

    /**
     * Tidies the submitted input before validation (for example dropping blank rows).
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function prepare(array $input): array;

    /** @return array<string, mixed> */
    public function rules(): array;

    public function handle(Request $request): void;
}
