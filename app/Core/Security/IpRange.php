<?php

namespace App\Core\Security;

use Symfony\Component\HttpFoundation\IpUtils;

final class IpRange
{
    /** Cloudflare edge ranges (https://www.cloudflare.com/ips/). Refresh with `php artisan vibecraft:cloudflare-ips`. */
    public static function cloudflare(): array
    {
        $file = storage_path('app/cloudflare-ips.json');
        if (is_file($file)) {
            $data = json_decode((string) file_get_contents($file), true);
            if (is_array($data) && $data !== []) {
                return $data;
            }
        }

        return [
            '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22', '141.101.64.0/18',
            '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20', '197.234.240.0/22', '198.41.128.0/17',
            '162.158.0.0/15', '104.16.0.0/13', '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
            '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32', '2405:8100::/32',
            '2a06:98c0::/29', '2c0f:f248::/32',
        ];
    }

    public static function contains(string $ip, array $ranges): bool
    {
        return $ranges !== [] && IpUtils::checkIp($ip, $ranges);
    }

    /** True for loopback, private, link-local, CGNAT and reserved addresses (SSRF guard). */
    public static function isInternal(string $ip): bool
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            return true;
        }

        $packed = inet_pton($ip);
        if (strlen($packed) === 16 && substr($packed, 0, 12) === str_repeat("\0", 10)."\xff\xff") {
            return self::isInternal(inet_ntop(substr($packed, 12)));
        }

        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false
            || IpUtils::checkIp($ip, ['100.64.0.0/10', '169.254.0.0/16', 'fe80::/10', 'fc00::/7', '::1/128', '0.0.0.0/8', '224.0.0.0/4', 'ff00::/8', '64:ff9b::/96', '2002::/16', '2001::/32']);
    }
}
