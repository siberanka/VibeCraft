<?php

namespace App\Console\Commands;

use App\Domain\Commerce\ContractNotifications;
use Illuminate\Console\Command;

final class RecoverContractNotices extends Command
{
    protected $signature = 'vibecraft:contract-notices {--limit=50 : Maximum pending notices to queue (1-100)}';

    protected $description = 'Queue pending original contract copies and flag interrupted deliveries for review';

    public function handle(ContractNotifications $notifications): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]]);
        if ($limit === false) {
            $this->error('The limit must be an integer between 1 and 100.');

            return self::INVALID;
        }
        $this->info('Pending contract copies checked: '.$notifications->recover($limit));

        return self::SUCCESS;
    }
}
