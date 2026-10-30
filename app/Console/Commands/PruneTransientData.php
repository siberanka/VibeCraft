<?php

namespace App\Console\Commands;

use App\Models\BridgeNonce;
use App\Models\LinkCode;
use Illuminate\Console\Command;

class PruneTransientData extends Command
{
    protected $signature = 'vibecraft:prune';

    protected $description = 'Remove expired bridge nonces and link codes';

    public function handle(): int
    {
        $window = (int) config('vibecraft.bridge.max_skew_seconds') * 2;
        $nonces = BridgeNonce::query()->where('created_at', '<', now()->subSeconds(max(600, $window)))->delete();
        $codes = LinkCode::query()->where('expires_at', '<', now()->subDay())->delete();
        $this->info("nonces=$nonces link_codes=$codes");

        return self::SUCCESS;
    }
}
