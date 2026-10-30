<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\IpUtils;

class UpdateCloudflareIps extends Command
{
    protected $signature = 'vibecraft:cloudflare-ips';

    protected $description = 'Refresh the list of Cloudflare edge ranges used to trust CF-Connecting-IP';

    public function handle(): int
    {
        $ranges = [];
        foreach (['https://www.cloudflare.com/ips-v4', 'https://www.cloudflare.com/ips-v6'] as $url) {
            $response = Http::timeout(10)->get($url);
            if (! $response->successful()) {
                $this->error("Failed to fetch $url");

                return self::FAILURE;
            }
            foreach (preg_split('/\s+/', trim($response->body())) as $cidr) {
                [$ip, $bits] = array_pad(explode('/', $cidr, 2), 2, null);
                if (filter_var($ip, FILTER_VALIDATE_IP) && ctype_digit((string) $bits)) {
                    IpUtils::checkIp($ip, $cidr); // throws on malformed input
                    $ranges[] = $cidr;
                }
            }
        }

        if (count($ranges) < 10) {
            $this->error('Unexpectedly small range list; keeping the previous one.');

            return self::FAILURE;
        }

        file_put_contents(storage_path('app/cloudflare-ips.json'), json_encode(array_values(array_unique($ranges)), JSON_PRETTY_PRINT));
        $this->info(count($ranges).' ranges stored.');

        return self::SUCCESS;
    }
}
