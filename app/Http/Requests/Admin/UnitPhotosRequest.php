<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Actions\Units\ManageUnitPhotos;
use App\Models\Unit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UnitPhotosRequest extends FormRequest
{
    public function authorize(): bool
    {
        $unit = $this->route('unit');

        return $unit instanceof Unit && (bool) $this->user()?->can('update', $unit);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'photos' => ['required', 'array', 'min:1', 'max:'.ManageUnitPhotos::MAX_PHOTOS],
            'photos.*' => ['required', 'file', 'mimes:'.config('rentora.uploads.image_mimes'), 'max:'.config('rentora.uploads.max_kb')],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $unit = $this->route('unit');
                $incoming = count((array) $this->file('photos', []));
                if ($unit instanceof Unit && $unit->photos()->count() + $incoming > ManageUnitPhotos::MAX_PHOTOS) {
                    $validator->errors()->add('photos', __('units.photos.too_many', ['max' => ManageUnitPhotos::MAX_PHOTOS]));
                }
            },
        ];
    }
}
