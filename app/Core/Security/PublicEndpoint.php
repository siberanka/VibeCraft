<?php

namespace App\Core\Security;

use InvalidArgumentException;

/** Resolve once, reject non-public addresses, then connect to the pinned IP. */
final class PublicEndpoint
{
    public static function address(string $host, int $port): string
    {
        if ($port < 1 || $port > 65535 || strlen($host) > 253 || ! preg_match('/^[a-zA-Z0-9.:-]+$/D', $host)) {
            throw new InvalidArgumentException('Invalid status endpoint.');
        }
        $addresses = [];
        if (filter_var($host, FILTER_VALIDATE_IP)) { $addresses[] = $host; }
        else {
            foreach (@dns_get_record($host, DNS_A | DNS_AAAA) ?: [] as $record) {
                $ip = $record['ip'] ?? $record['ipv6'] ?? null;
                if (is_string($ip)) { $addresses[] = $ip; }
            }
        }
        if (! $addresses) { throw new InvalidArgumentException('Status host could not be resolved.'); }
        foreach ($addresses as $ip) {
            if (IpRange::isInternal($ip)) { throw new InvalidArgumentException('Status host must resolve to public addresses.'); }
        }
        $ip = $addresses[0];
        return (str_contains($ip, ':') ? '['.$ip.']' : $ip).':'.$port;
    }
}
