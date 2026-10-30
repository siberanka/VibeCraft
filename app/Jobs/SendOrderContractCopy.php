<?php

namespace App\Jobs;

use App\Domain\Commerce\ContractNotifications;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendOrderContractCopy implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    public int $uniqueFor = 600;

    public function __construct(public int $noticeId, public string $messageKey) {}

    public function uniqueId(): string
    {
        return $this->noticeId.':'.$this->messageKey;
    }

    public function handle(ContractNotifications $notifications): void
    {
        $notifications->deliver($this->noticeId, $this->messageKey);
    }
}
