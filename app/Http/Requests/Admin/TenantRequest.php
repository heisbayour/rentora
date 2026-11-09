<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Tenant;
use App\Services\LeaseWizard;
use Illuminate\Foundation\Http\FormRequest;

class TenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tenant = $this->route('tenant');

        return $tenant instanceof Tenant
            ? (bool) $this->user()?->can('update', $tenant)
            : (bool) $this->user()?->can('create', Tenant::class);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return app(LeaseWizard::class)->rules('tenant', $this->user()?->organization_id) + [
            'notes' => ['nullable', 'string', 'max:5000'],
            'notification_prefs.email' => ['nullable', 'boolean'],
            'notification_prefs.sms' => ['nullable', 'boolean'],
        ];
    }

    /** @return array<string, mixed> */
    public function tenantData(): array
    {
        $data = $this->safe()->except('notification_prefs');
        $data['notification_prefs'] = [
            'email' => $this->boolean('notification_prefs.email'),
            'sms' => $this->boolean('notification_prefs.sms'),
        ];

        return $data;
    }
}
