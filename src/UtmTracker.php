<?php

declare(strict_types=1);

namespace Fsuuaas\UtmTracker;

use Fsuuaas\UtmTracker\Fields\FieldRegistry;
use Fsuuaas\UtmTracker\Support\ClientIp;
use Illuminate\Http\Request;

/**
 * Entry point for the package: owns the field registry and resolves the current
 * request's UTM data exactly once.
 *
 * The resolved UtmData is memoised on the Request object rather than in a static
 * property, so nothing survives between requests on a long-lived worker.
 */
final class UtmTracker
{
    /** Key used to stash the resolved UtmData on the request. */
    private const REQUEST_KEY = 'utm-tracker.resolved';

    private FieldRegistry $registry;

    public function __construct(?FieldRegistry $registry = null)
    {
        $this->registry = $registry ?? FieldRegistry::default();
    }

    private static function instance(): self
    {
        return app(self::class);
    }

    /**
     * Read the registry, or extend it by passing a callback.
     *
     * UtmTracker::fields(fn ($r) => $r->add(new Field('fbclid', limit: 255)));
     */
    public static function fields(?callable $callback = null): FieldRegistry
    {
        $registry = self::instance()->registry;

        if ($callback !== null) {
            $callback($registry);
        }

        return $registry;
    }

    public static function setRegistry(FieldRegistry $registry): void
    {
        self::instance()->registry = $registry;
    }

    /**
     * The current request's UTM data, resolved once and reused. A request that
     * creates several models therefore parses input a single time and gives all
     * of them identical attribution.
     */
    public static function resolve(?Request $request = null): UtmData
    {
        $request ??= request();

        if (! $request instanceof Request) {
            return UtmData::empty();
        }

        $cached = $request->attributes->get(self::REQUEST_KEY);

        if ($cached instanceof UtmData) {
            return $cached;
        }

        $data = UtmData::fromRequest(
            $request,
            self::fields(),
            (string) config('utm-tracker.ip', ClientIp::MODE_REQUEST),
        );

        $request->attributes->set(self::REQUEST_KEY, $data);

        return $data;
    }

    /** Override what resolve() returns for the rest of this request. */
    public static function use(UtmData $data, ?Request $request = null): void
    {
        $request ??= request();

        if ($request instanceof Request) {
            $request->attributes->set(self::REQUEST_KEY, $data);
        }
    }

    public static function forget(?Request $request = null): void
    {
        $request ??= request();

        if ($request instanceof Request) {
            $request->attributes->remove(self::REQUEST_KEY);
        }
    }

    /**
     * Validation rules for the client-supplied fields, so an app can reject bad
     * input at the edge instead of relying on silent truncation.
     *
     * @return array<string, string>
     */
    public static function rules(): array
    {
        $registry = self::fields();
        $limits = $registry->limits();
        $rules = [];

        foreach ($registry->clientAttributes() as $attribute) {
            $rules[$attribute] = $attribute === 'session_count'
                ? 'nullable|integer|min:0'
                : 'nullable|string|max:'.($limits[$attribute] ?? 255);
        }

        return $rules;
    }

    /**
     * Turn a raw utm_source into a display label, e.g. "googleads" => "GoogleAds".
     * Returns null for the placeholders that mean "no attribution".
     */
    public static function referralSource(?string $source): ?string
    {
        if ($source === null) {
            return null;
        }

        $source = trim($source);

        if ($source === '' || in_array(strtolower($source), ['(none)', '(direct)', 'direct', 'none'], true)) {
            return null;
        }

        /** @var array<string, string> $map */
        $map = (array) config('utm-tracker.referral_sources', []);

        $key = strtolower($source);

        if (isset($map[$key])) {
            return $map[$key];
        }

        return ucwords(str_replace(['-', '_'], ' ', $source));
    }
}
