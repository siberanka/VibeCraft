<?php

namespace App\Http\Controllers;

use App\Models\Punishment;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PunishmentController extends Controller
{
    public function __invoke(Request $request): View
    {
        $search = mb_substr(trim((string) $request->query('player', '')), 0, 16);
        $query = Punishment::query()->where('active', true)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->whereHas('server', fn ($q) => $q->where('show_on_site', true));
        if ($search !== '' && preg_match('/^[A-Za-z0-9_.*]+$/', $search)) {
            $query->where('player_name', 'like', str_replace(['%', '_'], ['\%', '\_'], $search).'%');
        }

        return view('theme::community.punishments', [
            'search' => $search,
            'punishments' => $query->with('server:id,name')->orderByDesc('issued_at')->orderByDesc('id')->paginate(30)->withQueryString(),
        ]);
    }
}
