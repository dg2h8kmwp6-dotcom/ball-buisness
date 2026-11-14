<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBallSelectionRequest;
use App\Models\BallType;
use App\Support\Cart;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class OrderController extends Controller
{
    /**
     * The order builder: pick ball types and quantities.
     */
    public function create(Cart $cart): View
    {
        return view('orders.create', [
            'ballTypes' => BallType::query()->active()->ordered()->get(),
            'quantities' => $cart->quantities(),
        ]);
    }

    /**
     * Persist the selection to the cart and move on to checkout.
     */
    public function store(StoreBallSelectionRequest $request, Cart $cart): RedirectResponse
    {
        $cart->replace($request->selection());

        return redirect()->route('checkout.show');
    }
}
