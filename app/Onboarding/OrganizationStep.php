<?php

declare(strict_types=1);

namespace App\Onboarding;

use App\Actions\Organization\UpdateOrganization;
use App\Support\CurrentOrganization;
use Illuminate\Http\Request;

class OrganizationStep implements OnboardingStep
{
    public function __construct(
        private readonly CurrentOrganization $organization,
        private readonly UpdateOrganization $update,
    ) {}

    public function key(): string
    {
        return 'organization';
    }

    public function title(): string
    {
        return __('onboarding.steps.organization');
    }

    public function skippable(): bool
    {
        return false;
    }

    public function view(): string
    {
        return 'admin.onboarding.organization';
    }

    public function data(): array
    {
        return [
            'organization' => $this->organization->get(),
            'timezones' => UpdateOrganization::timezoneOptions(),
            'currencies' => UpdateOrganization::CURRENCIES,
        ];
    }

    public function rules(): array
    {
        return UpdateOrganization::rules();
    }

    public function handle(Request $request): void
    {
        $organization = $this->organization->get() ?? throw new \LogicException('No current organization.');
        $this->update->handle($organization, $request->only(array_keys($this->rules())), $request->file('logo'));
    }
}
