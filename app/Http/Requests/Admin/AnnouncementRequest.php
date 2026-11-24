<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\AnnouncementAudience;
use App\Models\Announcement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('manage-money');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $organizationId = $this->user()?->organization_id;

        return [
            'title' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:2000'],
            'audience' => ['required', Rule::in(AnnouncementAudience::values())],
            'property_ids' => ['exclude_unless:audience,property', 'required', 'array', 'min:1'],
            'property_ids.*' => ['integer', Rule::exists('properties', 'id')->where('organization_id', $organizationId)],
            'tenant_ids' => ['exclude_unless:audience,tenants', 'required', 'array', 'min:1'],
            'tenant_ids.*' => ['integer', Rule::exists('tenants', 'id')->where('organization_id', $organizationId)],
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => [Rule::in(Announcement::CHANNELS)],
        ];
    }

    public function audience(): AnnouncementAudience
    {
        return AnnouncementAudience::from((string) $this->validated('audience'));
    }

    /** @return list<int> */
    public function audienceIds(): array
    {
        $key = match ($this->audience()) {
            AnnouncementAudience::Property => 'property_ids',
            AnnouncementAudience::Tenants => 'tenant_ids',
            AnnouncementAudience::AllTenants => null,
        };

        return $key === null ? [] : array_values(array_map('intval', (array) $this->validated($key, [])));
    }
}
