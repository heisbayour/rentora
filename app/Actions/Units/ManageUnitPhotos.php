<?php

declare(strict_types=1);

namespace App\Actions\Units;

use App\Models\Unit;
use App\Models\UnitPhoto;
use App\Support\PublicUploads;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/** Unit photo gallery: add, reorder and delete. Photos are public (public/uploads/units). */
class ManageUnitPhotos
{
    public const MAX_PHOTOS = 20;

    public function __construct(private readonly PublicUploads $uploads) {}

    /**
     * @param  list<UploadedFile>  $files
     */
    public function add(Unit $unit, array $files): int
    {
        $next = (int) $unit->photos()->max('sort_order') + 1;
        foreach ($files as $file) {
            $unit->photos()->create([
                'path' => $this->uploads->storeJpeg($file, 'units'),
                'sort_order' => $next++,
            ]);
        }

        return count($files);
    }

    /** Moves a photo one place earlier (-1) or later (+1) in the gallery. */
    public function move(UnitPhoto $photo, int $direction): void
    {
        DB::transaction(function () use ($photo, $direction): void {
            $photos = UnitPhoto::query()->where('unit_id', $photo->unit_id)
                ->orderBy('sort_order')->orderBy('id')->lockForUpdate()->get()->values();
            $index = $photos->search(fn (UnitPhoto $p) => $p->is($photo));
            $target = $index === false ? false : $index + ($direction < 0 ? -1 : 1);
            if ($index === false || $target < 0 || $target >= $photos->count()) {
                return;
            }

            $ordered = $photos->all();
            [$ordered[$index], $ordered[$target]] = [$ordered[$target], $ordered[$index]];
            foreach ($ordered as $position => $item) {
                if ($item->sort_order !== $position) {
                    $item->forceFill(['sort_order' => $position])->save();
                }
            }
        });
    }

    public function delete(UnitPhoto $photo): void
    {
        $path = $photo->path;
        $photo->delete();
        $this->uploads->delete($path);
    }
}
