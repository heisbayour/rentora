<?php

declare(strict_types=1);

namespace App\Onboarding;

use App\Actions\Tenants\InviteTenant;
use App\Actions\Tenants\SaveTenant;
use Illuminate\Http\Request;

/** Wizard step 4 (skippable): add and invite the first tenant. Their lease is set up later. */
class TenantStep implements OnboardingStep
{
    public function __construct(
        private readonly SaveTenant $save,
        private readonly InviteTenant $invite,
    ) {}

    public function key(): string
    {
        return 'tenant';
    }

    public function title(): string
    {
        return __('onboarding.steps.tenant');
    }

    public function skippable(): bool
    {
        return true;
    }

    public function view(): string
    {
        return 'admin.onboarding.tenant';
    }

    public function data(): array
    {
        return [];
    }

    public function prepare(array $input): array
    {
        return $input;
    }

    public function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['required', 'string', 'max:32'],
        ];
    }

    public function handle(Request $request): void
    {
        $tenant = $this->save->handle(null, $request->only(['full_name', 'email', 'phone']));
        $this->invite->handle($tenant);
    }
}
