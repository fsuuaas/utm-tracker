<?php

declare(strict_types=1);

namespace Fsuuaas\UtmTracker\Support;

/**
 * Encodes and decodes the ">"-joined multi-touch chains stored in mcf_* columns.
 *
 * The delimiter is escaped rather than assumed absent: a campaign name really
 * can contain ">", and the previous implementation joined values raw, so one
 * such value silently split into two touches on every later read.
 *
 * Escaping must stay byte-identical to the JS side, which writes the cookie.
 */
final class Chain
{
    public const DELIMITER = '>';

    /** Escape a single value for inclusion in a chain. */
    public static function escape(string $value): string
    {
        // "%" first, otherwise escaping ">" would then double-escape its own "%".
        return str_replace(['%', self::DELIMITER], ['%25', '%3E'], $value);
    }

    public static function unescape(string $value): string
    {
        // "%25" last, mirroring escape() in reverse.
        return str_replace(['%3E', '%25'], [self::DELIMITER, '%'], $value);
    }

    /**
     * Split a stored chain back into its touches.
     *
     * @return list<string>
     */
    public static function parse(?string $chain): array
    {
        if ($chain === null || $chain === '') {
            return [];
        }

        return array_values(array_map(
            static fn (string $part) => self::unescape($part),
            array_filter(explode(self::DELIMITER, $chain), static fn (string $p) => $p !== ''),
        ));
    }

    /**
     * Build a chain from raw touches.
     *
     * @param  list<string>  $touches
     */
    public static function build(array $touches): string
    {
        return implode(self::DELIMITER, array_map(
            static fn (string $t) => self::escape($t),
            array_values(array_filter($touches, static fn (string $t) => $t !== '')),
        ));
    }

    /**
     * Append a touch, skipping empties and consecutive duplicates, and keeping
     * at most $maxTouches entries. When trimming, the first touch is preserved
     * and the middle is dropped — first-touch attribution is the part you cannot
     * reconstruct later.
     */
    public static function push(?string $chain, string $value, int $maxTouches = 10): string
    {
        if ($value === '') {
            return (string) $chain;
        }

        $touches = self::parse($chain);

        if ($touches !== [] && end($touches) === $value) {
            return (string) $chain;
        }

        $touches[] = $value;

        if ($maxTouches > 0 && count($touches) > $maxTouches) {
            $head = array_slice($touches, 0, 1);
            $tail = array_slice($touches, -($maxTouches - 1));
            $touches = array_merge($head, $tail);
        }

        return self::build($touches);
    }

    public static function first(?string $chain): ?string
    {
        return self::parse($chain)[0] ?? null;
    }

    public static function last(?string $chain): ?string
    {
        $touches = self::parse($chain);

        return $touches === [] ? null : $touches[count($touches) - 1];
    }

    public static function count(?string $chain): int
    {
        return count(self::parse($chain));
    }
}
