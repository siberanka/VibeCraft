<?php

namespace App\Http\Controllers;

use App\Domain\Minecraft\PlayerStatistics;
use App\Models\GameServer;
use App\Models\PlayerStat;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LeaderboardController extends Controller
{
    public function __invoke(Request $request, PlayerStatistics $statistics): View
    {
        $servers = GameServer::query()->where('show_on_site', true)
            ->whereIn('id', PlayerStat::query()->select('game_server_id')->distinct())->orderBy('sort')->orderBy('id')->get(['id', 'name', 'slug']);
        $server = $servers->firstWhere('slug', (string) $request->query('server')) ?? $servers->first();

        return view('theme::community.leaderboards', [
            'servers' => $servers,
            'server' => $server,
            'boards' => $server ? $statistics->leaderboards($server) : [],
        ]);
    }
}
