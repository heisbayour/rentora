<?php

declare(strict_types=1);

namespace App\Actions\Organization;

use App\Models\Organization;
use App\Services\ActivityLogger;
use App\Support\PublicUploads;
use Illuminate\Http\UploadedFile;

/** Saves organization details and logo. Used by the first-run wizard and Settings. */
class UpdateOrganization
{
    public const CURRENCIES = ['NGN' => 'Nigerian naira (₦)', 'GHS' => 'Ghanaian cedi (GH₵)', 'KES' => 'Kenyan shilling (KSh)', 'USD' => 'US dollar ($)'];

    public function __construct(
        private readonly PublicUploads $uploads,
        private readonly ActivityLogger $logger,
    ) {}

    /** @return array<string, mixed> */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:32'],
            'address' => ['nullable', 'string', 'max:500'],
            'primary_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'timezone' => ['required', 'timezone:all'],
            'currency' => ['required', 'in:'.implode(',', array_keys(self::CURRENCIES))],
            'logo' => ['nullable', 'file', 'mimes:'.config('rentora.uploads.image_mimes'), 'max:'.config('rentora.uploads.max_kb')],
        ];
    }

    /** @return array<string, string> */
    public static function timezoneOptions(): array
    {
        $zones = \DateTimeZone::listIdentifiers(\DateTimeZone::AFRICA);

        return array_combine($zones, $zones) + ['UTC' => 'UTC'];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Organization $organization, array $data, ?UploadedFile $logo = null): Organization
    {
        unset($data['logo']);
        $organization->fill($data);

        if ($logo !== null) {
            $previous = $organization->logo_path;
            $organization->logo_path = $this->uploads->storeImage($logo, 'logos');
            $this->uploads->delete($previous);
        }

        $changed = array_keys($organization->getDirty());
        $organization->save();

        if ($changed !== []) {
            $this->logger->log('settings.changed', $organization, ['fields' => $changed]);
        }

        return $organization;
    }
}
