<?php

namespace App\Console\Commands;

use App\Domain\Commerce\Fulfillment;
use App\Domain\Commerce\OrderLifecycle;
use App\Domain\Commerce\Renewals;
use App\Models\Order;
use Illuminate\Console\Command;

class ExpireOrders extends Command
{
    protected $signature = 'vibecraft:expire';

    protected $description = 'Cancel unpaid orders past their reservation and expire timed entitlements';

    public function handle(OrderLifecycle $lifecycle, Fulfillment $fulfillment): int
    {
        $cancelled = 0;
        Order::query()->where('status', Order::PENDING)->where('expires_at', '<', now())->limit(500)->get()
            ->each(function (Order $order) use ($lifecycle, &$cancelled) {
                if ($lifecycle->cancel($order, 'Payment window expired')) {
                    $cancelled++;
                }
            });

        $renewed = app(Renewals::class)->renewDue();
        $expired = $fulfillment->expireDue();
        $this->info("cancelled=$cancelled entitlements_renewed=$renewed entitlements_expired=$expired");

        return self::SUCCESS;
    }
}
