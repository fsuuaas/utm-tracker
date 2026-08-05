<?php

declare(strict_types=1);

namespace Fsuuaas\UtmTracker;

use Fsuuaas\UtmTracker\Fields\FieldRegistry;
use Fsuuaas\UtmTracker\Support\ClientIp;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use JsonSerializable;

/**
 * An immutable, already-sanitised set of UTM attributes.
 *
 * This is the only place that reads untrusted request input, and it is why the
 * write can no longer fail: values are truncated to the registry limit rather
 * than being allowed to overflow the column and throw from inside a model event.
 *
 * @implements Arrayable<string, scalar>
 */
final class UtmData implements Arrayable, JsonSerializable
{
    /** @param array<string, scalar> $attributes */
    private function __construct(private readonly array $attributes) {}

    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * Build from arbitrary input, applying the same sanitisation as fromRequest()
     * but without touching the request. Server-derived keys are dropped.
     *
     * @param  array<string, mixed>  $input
     */
    public static function fromArray(array $input, ?FieldRegistry $registry = null): self
    {
        $registry ??= UtmTracker::fields();

        return new self(self::sanitise($input, $registry, $registry->clientAttributes()));
    }

    /**
     * Build from the current request.
     *
     * Client-supplied keys are read from input and sanitised; server-derived keys
     * (ip_address, user_agent) are resolved here and can never be spoofed by the
     * submitter, no matter what they post.
     */
    public static function fromRequest(
        Request $request,
        ?FieldRegistry $registry = null,
        string $ipMode = ClientIp::MODE_REQUEST,
    ): self {
        $registry ??= UtmTracker::fields();

        $limits = $registry->limits();

        $attributes = self::sanitise(
            $request->all(),
            $registry,
            $registry->clientAttributes(),
        );

        // Referrer is client-supplied but has a trustworthy server-side fallback.
        if (! isset($attributes['referrer']) && $registry->has('referrer')) {
            $referer = $request->headers->get('referer');

            if (is_string($referer) && $referer !== '') {
                $attributes['referrer'] = mb_substr($referer, 0, $limits['referrer'] ?? 512);
            }
        }

        if ($registry->has('ip_address')) {
            $ip = ClientIp::from($request, $ipMode);

            if ($ip !== null) {
                $attributes['ip_address'] = $ip;
            }
        }

        if ($registry->has('user_agent')) {
            $agent = $request->userAgent();

            if (is_string($agent) && $agent !== '') {
                $attributes['user_agent'] = mb_substr($agent, 0, $limits['user_agent'] ?? 512);
            }
        }

        return new self($attributes);
    }

    /**
     * Copy the attributes off an existing record — for propagating attribution
     * from one model to another (a trial converting into a user, say) without
     * restating every column by hand.
     */
    public static function fromRecord(Model $record, ?FieldRegistry $registry = null): self
    {
        $registry ??= UtmTracker::fields();

        $attributes = [];

        foreach ($registry->columns() as $column) {
            $value = $record->getAttribute($column);

            if ($value !== null && $value !== '') {
                $attributes[$column] = $value;
            }
        }

        return new self($attributes);
    }

    /**
     * Drop anything that is not a usable scalar, strip control characters, and
     * clamp to the per-column limit.
     *
     * @param  array<string, mixed>  $input
     * @param  list<string>  $allowed
     * @return array<string, scalar>
     */
    private static function sanitise(array $input, FieldRegistry $registry, array $allowed): array
    {
        $limits = $registry->limits();
        $out = [];

        foreach ($allowed as $key) {
            if (! array_key_exists($key, $input)) {
                continue;
            }

            $value = self::clean($input[$key], $limits[$key] ?? 255);

            if ($value === null) {
                continue;
            }

            $out[$key] = $value;
        }

        if (isset($out['session_count'])) {
            $out['session_count'] = max(0, (int) $out['session_count']);
        }

        return $out;
    }

    /**
     * @return string|null null when the value is unusable and should be skipped
     */
    private static function clean(mixed $value, int $limit): ?string
    {
        // Arrays, objects, closures and bools are never legitimate UTM values.
        // ?utm_source[]=a&utm_source[]=b must not reach the database layer.
        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            return null;
        }

        $value = (string) $value;

        // Reject rather than store mojibake; mb_substr on invalid UTF-8 is unsafe.
        if (! mb_check_encoding($value, 'UTF-8')) {
            return null;
        }

        // Strip control and format characters (nulls, escapes, bidi overrides).
        $value = preg_replace('/[\p{Cc}\p{Cf}]/u', '', $value) ?? '';

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, $limit);
    }

    public function isEmpty(): bool
    {
        return $this->attributes === [];
    }

    /**
     * Whether this represents a real marketing touch, as opposed to bare context
     * (an IP and a user agent, which every request has).
     */
    public function hasAttribution(): bool
    {
        foreach (['utm_source', 'utm_medium', 'utm_campaign', 'gclid'] as $key) {
            $value = $this->attributes[$key] ?? null;

            if ($value !== null && $value !== '' && $value !== '(none)' && $value !== '(direct)') {
                return true;
            }
        }

        return false;
    }

    /** @param list<string> $keys */
    public function only(array $keys): self
    {
        return new self(array_intersect_key($this->attributes, array_flip($keys)));
    }

    /** @param list<string> $keys */
    public function except(array $keys): self
    {
        return new self(array_diff_key($this->attributes, array_flip($keys)));
    }

    /** Values in $other win where both are set. */
    public function merge(self $other): self
    {
        return new self([...$this->attributes, ...$other->attributes]);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return isset($this->attributes[$key]);
    }

    public function __get(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }

    public function __isset(string $key): bool
    {
        return isset($this->attributes[$key]);
    }

    /** @return array<string, scalar> */
    public function toArray(): array
    {
        return $this->attributes;
    }

    /** @return array<string, scalar> */
    public function jsonSerialize(): array
    {
        return $this->attributes;
    }
}
