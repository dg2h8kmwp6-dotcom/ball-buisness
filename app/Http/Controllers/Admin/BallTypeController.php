<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBallTypeRequest;
use App\Http\Requests\Admin\UpdateBallTypeRequest;
use App\Models\BallType;
use App\Support\BallPhoto;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

class BallTypeController extends Controller
{
    public function __construct(protected BallPhoto $photos) {}

    public function index(): View
    {
        return view('admin.ball-types.index', [
            'ballTypes' => BallType::query()->ordered()->withCount('orderItems')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.ball-types.edit', [
            'ballType' => new BallType([
                'color_hex' => '#2b7fd4',
                'diameter_mm' => 70,
                'price_per_ball_cents' => 12,
                'min_quantity' => 100,
                'quantity_step' => 100,
                'is_active' => true,
            ]),
        ]);
    }

    public function store(StoreBallTypeRequest $request): RedirectResponse
    {
        $ballType = new BallType($request->ballTypeAttributes());
        $ballType->slug = $this->uniqueSlug($request->validated('name'));
        $ballType->save();

        $this->storePhotoIfPresent($request, $ballType);

        return redirect()
            ->route('admin.ball-types.index')
            ->with('status', "Added {$ballType->name}.");
    }

    public function edit(BallType $ballType): View
    {
        return view('admin.ball-types.edit', [
            'ballType' => $ballType,
        ]);
    }

    public function update(UpdateBallTypeRequest $request, BallType $ballType): RedirectResponse
    {
        // The slug is deliberately left alone: it keys the default catalogue
        // and appears in stored photo filenames.
        $ballType->update($request->ballTypeAttributes());

        $this->storePhotoIfPresent($request, $ballType);

        return redirect()
            ->route('admin.ball-types.index')
            ->with('status', "Saved {$ballType->name}.");
    }

    /**
     * Remove just the photo, keeping the ball type and its flat colour.
     */
    public function destroyPhoto(BallType $ballType): RedirectResponse
    {
        $this->photos->delete($ballType);

        $ballType->update(['image_path' => null]);

        return back()->with('status', "Removed the photo for {$ballType->name}.");
    }

    public function destroy(BallType $ballType): RedirectResponse
    {
        // Past orders reference this row, so deleting it would break their
        // history. Deactivating hides it from the storefront instead.
        if ($ballType->orderItems()->exists()) {
            return back()->with(
                'error',
                "{$ballType->name} appears in past orders, so it can't be deleted. Switch it to inactive to hide it from the shop."
            );
        }

        $this->photos->delete($ballType);
        $ballType->delete();

        return redirect()
            ->route('admin.ball-types.index')
            ->with('status', "Deleted {$ballType->name}.");
    }

    protected function storePhotoIfPresent(StoreBallTypeRequest $request, BallType $ballType): void
    {
        $crop = $request->crop();

        if ($request->hasFile('photo') && $crop !== null) {
            $this->photos->store($ballType, $request->file('photo'), $crop);
        }
    }

    protected function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'ball-type';
        $slug = $base;
        $suffix = 2;

        while (BallType::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix += 1;
        }

        return $slug;
    }
}
