<?php

namespace App\Http\Controllers;

use App\Models\BallType;
use Illuminate\Contracts\View\View;

class LandingController extends Controller
{
    public function index(): View
    {
        $ballTypes = BallType::query()->active()->get();

        return view('landing', [
            // Derived from the catalogue so the headline figures cannot drift
            // out of date as colours are added or edited.
            'colourCount' => $ballTypes->count(),
            'minimumQuantity' => (int) ($ballTypes->min('min_quantity') ?: 0),
            'ballDiameter' => (int) ($ballTypes->mode('diameter_mm')[0] ?? 70),
        ]);
    }
}
