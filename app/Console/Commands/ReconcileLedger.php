<?php

namespace App\Console\Commands;

use App\Domain\Commerce\Ledger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReconcileLedger extends Command
{
    protected $signature = 'vibecraft:ledger-reconcile';

    protected $description = 'Compare cached wallet balances with the immutable ledger';

    public function handle(Ledger $ledger): int
    {
        $drift = $ledger->reconcile();
        if ($drift === []) {
            $this->info('Ledger and wallets agree.');

            return self::SUCCESS;
        }

        Log::critical('ledger.drift', $drift);
        foreach ($drift as $user => $d) {
            $this->error("user $user: cached {$d['cached']} ledger {$d['ledger']}");
        }

        return self::FAILURE;
    }
}
