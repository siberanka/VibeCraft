<?php

namespace App\Http\Controllers;

use App\Domain\Commerce\Chest;
use App\Domain\Commerce\CommerceException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChestController extends Controller
{
    public function __construct(private Chest $chest) {}

    public function index(Request $request): View
    {
        return view('theme::account.chest', ['items' => $this->chest->contents($request->user())]);
    }

    public function claim(Request $request, int $item): RedirectResponse
    {
        try {
            $claimed = $this->chest->claim($request->user(), $item);
        } catch (CommerceException $e) {
            return redirect()->route('chest.index')->withErrors(['chest' => $e->getMessage()]);
        }

        return redirect()->route('chest.index')->with('success', __(':item is on its way to the game.', ['item' => $claimed->product_name]));
    }
}
