<?php

declare(strict_types=1);

namespace App\Actions\Properties;

use App\Models\Property;
use App\Support\PublicUploads;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class SaveProperty
{
    public function __construct(private readonly PublicUploads $uploads) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(?Property $property, array $data, ?UploadedFile $cover = null, bool $removeCover = false): Property
    {
        $property ??= new Property;
        unset($data['cover_image'], $data['remove_cover']);
        $data['code'] = Str::upper((string) $data['code']);
        $property->fill($data);

        $previous = $property->cover_image_path;
        if ($cover !== null) {
            $property->cover_image_path = $this->uploads->storeJpeg($cover, 'properties');
        } elseif ($removeCover) {
            $property->cover_image_path = null;
        }

        $property->save();

        if ($previous !== null && $previous !== $property->cover_image_path) {
            $this->uploads->delete($previous);
        }

        return $property;
    }
}
