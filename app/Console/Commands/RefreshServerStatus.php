<?php

namespace App\Console\Commands;

use App\Domain\Minecraft\StatusRefresher;
use App\Models\GameServer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class RefreshServerStatus extends Command
{
    protected $signature = 'vibecraft:status-refresh';

    protected $description = 'Ping configured Minecraft servers and cache their status';

    public function handle(StatusRefresher $refresher): int
    {
        $budget = max(1.0, min(45.0, (float) config('vibecraft.status.batch_budget_seconds', 20)));
        $deadline = hrtime(true) + (int) ($budget * 1_000_000_000);
        $servers = GameServer::query()->whereNotNull('query_host')->where('query_host', '!=', '')
            ->where(fn ($q) => $q->whereNull('status_checked_at')->orWhere('status_checked_at', '<=', now()->subMinutes(max(1, (int) config('vibecraft.status.refresh_minutes', 2)))))
            ->orderBy('status_checked_at')->orderBy('id')->limit(max(1, min(100, (int) config('vibecraft.status.batch_limit', 20))))->get();
        foreach ($servers as $server) {
            $remaining = ($deadline - hrtime(true)) / 1_000_000_000;
            if ($remaining <= 0.001) {
                break;
            }
            $result = $refresher->refresh($server, min($remaining, max(0.001, (float) config('vibecraft.status.timeout_seconds', 3))));
            $this->line(sprintf('%-20s %s', $server->name, $result->online ? "online {$result->players}/{$result->maxPlayers}" : 'offline: '.$result->error));
        }
        Cache::forget('vibecraft.public_servers.v2');

        return self::SUCCESS;
    }
}
