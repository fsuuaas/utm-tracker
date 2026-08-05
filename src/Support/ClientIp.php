<?php

declare(strict_types=1);

namespace Fsuuaas\UtmTracker\Support;

use Illuminate\Http\Request;

/**
 * Resolves the client IP for a UTM record.
 *
 * Deliberately goes through $request->ip() rather than reading forwarding headers
 * directly. Laravel resolves CF-Connecting-IP / X-Forwarded-For through the app's
 * TrustProxies configuration, which is what decides whether those headers can be
 * believed. Reading $_SERVER['HTTP_CF_CONNECTING_IP'] unconditionally — as this
 * package previously did — lets any client forge the value simply by sending the
 * header, unless the origin is unreachable except through Cloudflare.
 */
final class ClientIp
{
    public const MODE_REQUEST = 'request';

    public const MODE_ANONYMIZE = 'anonymize';

    public const MODE_NONE = 'none';

    public static function from(Request $request, string $mode = self::MODE_REQUEST): ?string
    {
        if ($mode === self::MODE_NONE) {
            return null;
        }

        $ip = $request->ip();

        if ($ip === null || $ip === '') {
            return null;
        }

        return $mode === self::MODE_ANONYMIZE ? self::anonymize($ip) : $ip;
    }

    /**
     * Zero the host portion: last octet for IPv4, last 80 bits for IPv6.
     * Keeps the record useful for coarse geo and dedup without storing an identifier.
     */
    public static function anonymize(string $ip): ?string
    {
        $packed = @inet_pton($ip);

        if ($packed === false) {
            return null;
        }

        $length = strlen($packed);

        // IPv4: keep the first 3 octets. IPv6: keep the first 48 bits.
        $keep = $length === 4 ? 3 : 6;

        $masked = substr($packed, 0, $keep).str_repeat("\0", $length - $keep);

        $result = @inet_ntop($masked);

        return $result === false ? null : $result;
    }
}
