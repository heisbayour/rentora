<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Units\ManageUnitPhotos;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UnitPhotosRequest;
use App\Models\Unit;
use App\Models\UnitPhoto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class UnitPhotoController extends Controller
{
    public function __construct(private readonly ManageUnitPhotos $photos) {}

    public function store(UnitPhotosRequest $request, Unit $unit): RedirectResponse
    {
        /** @var list<UploadedFile> $files */
        $files = $request->file('photos');
        $count = $this->photos->add($unit, $files);

        return redirect()->route('app.units.show', $unit)->with('toast', trans_choice('units.photos.added', $count, ['count' => $count]));
    }

    public function move(Request $request, Unit $unit, UnitPhoto $photo): RedirectResponse
    {
        $this->authorize('update', $unit);
        abort_unless($photo->unit_id === $unit->id, 404);

        $this->photos->move($photo, $request->input('direction') === 'up' ? -1 : 1);

        return redirect()->route('app.units.show', $unit);
    }

    public function destroy(Unit $unit, UnitPhoto $photo): RedirectResponse
    {
        $this->authorize('update', $unit);
        abort_unless($photo->unit_id === $unit->id, 404);

        $this->photos->delete($photo);

        return redirect()->route('app.units.show', $unit)->with('toast', __('units.photos.deleted'));
    }
}
