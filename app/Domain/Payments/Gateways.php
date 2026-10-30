<?php

namespace App\Domain\Payments;

use App\Domain\Payments\Gateways\PaytrGateway;
use App\Domain\Payments\Gateways\SandboxGateway;
use App\Domain\Payments\Gateways\StripeGateway;
use Illuminate\Contracts\Container\Container;

class Gateways
{
    private const MAP = [
        'stripe' => StripeGateway::class,
        'paytr' => PaytrGateway::class,
        'sandbox' => SandboxGateway::class,
    ];

    public function __construct(private Container $container) {}

    public function get(string $id): ?PaymentGateway
    {
        $class = self::MAP[$id] ?? null;

        return $class !== null ? $this->container->make($class) : null;
    }

    /** @return list<PaymentGateway> */
    public function available(): array
    {
        $out = [];
        foreach (array_keys(self::MAP) as $id) {
            $gateway = $this->get($id);
            if ($gateway !== null && $gateway->isAvailable()) {
                $out[] = $gateway;
            }
        }

        return $out;
    }

    public static function ids(): array
    {
        return array_keys(self::MAP);
    }
}
