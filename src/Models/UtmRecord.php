<?php

namespace Fsuuaas\UtmTracker\Models;

use Fsuuaas\UtmTracker\Support\Chain;
use Fsuuaas\UtmTracker\UtmTracker;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property string|null $utm_source
 * @property string|null $utm_medium
 * @property string|null $utm_campaign
 * @property string|null $mcf_utm_source
 * @property string|null $mcf_timestamp
 * @property int|null $session_count
 */
class UtmRecord extends Model
{
    use HasFactory;

    /** Mass assignment is bounded by getFillable() below, not by a guard list. */
    protected $guarded = [];

    protected $casts = [
        'session_count' => 'integer',
        // mcf_timestamp is deliberately NOT cast: it holds a ">"-joined chain of
        // epoch milliseconds, not a single date.
    ];

    public function getTable(): string
    {
        return $this->table ?? (string) config('utm-tracker.table', 'utm_records');
    }

    /**
     * Derived from the field registry, so adding a field never means editing
     * this model.
     *
     * @return list<string>
     */
    public function getFillable(): array
    {
        return UtmTracker::fields()->attributes();
    }

    public function utmable(): MorphTo
    {
        return $this->morphTo();
    }

    /*
    |---------------------------------------------------------------------------
    | Chain helpers
    |---------------------------------------------------------------------------
    */

    /**
     * The multi-touch chain for a field, decoded into individual touches.
     *
     * @return list<string>
     */
    public function touches(string $field = 'utm_source'): array
    {
        return Chain::parse((string) $this->getAttribute('mcf_'.$field));
    }

    /**
     * Touch timestamps decoded from the epoch-millisecond chain.
     *
     * @return list<Carbon>
     */
    public function touchTimes(): array
    {
        return array_values(array_map(
            static fn (string $ms) => Carbon::createFromTimestampMs((int) $ms),
            array_filter(Chain::parse((string) $this->mcf_timestamp), 'is_numeric'),
        ));
    }

    /*
    |---------------------------------------------------------------------------
    | Scopes
    |---------------------------------------------------------------------------
    |
    | Named for what they filter, not "with*" — in Eloquent "with" means
    | eager-loading, and these do the opposite.
    */

    /** @param  string|list<string>  $value */
    public function scopeSource(Builder $query, string|array $value): Builder
    {
        return $this->applyFilter($query, 'utm_source', $value);
    }

    /** @param  string|list<string>  $value */
    public function scopeMedium(Builder $query, string|array $value): Builder
    {
        return $this->applyFilter($query, 'utm_medium', $value);
    }

    /** @param  string|list<string>  $value */
    public function scopeCampaign(Builder $query, string|array $value): Builder
    {
        return $this->applyFilter($query, 'utm_campaign', $value);
    }

    /** @param  string|list<string>  $value */
    public function scopeTerm(Builder $query, string|array $value): Builder
    {
        return $this->applyFilter($query, 'utm_term', $value);
    }

    /** @param  string|list<string>  $value */
    public function scopeContent(Builder $query, string|array $value): Builder
    {
        return $this->applyFilter($query, 'utm_content', $value);
    }

    /** @param  string|list<string>  $value */
    public function scopeGclid(Builder $query, string|array $value): Builder
    {
        return $this->applyFilter($query, 'gclid', $value);
    }

    /** @param  string|list<string>  $value */
    public function scopeIp(Builder $query, string|array $value): Builder
    {
        return $this->applyFilter($query, 'ip_address', $value);
    }

    public function scopeLandingPage(Builder $query, string $value): Builder
    {
        return $query->where('landing_page', $value);
    }

    public function scopeUserAgentLike(Builder $query, string $value): Builder
    {
        return $query->where('user_agent', 'like', '%'.$value.'%');
    }

    public function scopeBetween(Builder $query, mixed $from, mixed $to, string $column = 'created_at'): Builder
    {
        return $query->whereBetween($column, [$from, $to]);
    }

    /** Records carrying a real marketing touch, excluding direct and unknown. */
    public function scopeAttributed(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->where(function (Builder $inner) {
                $inner->whereNotNull('utm_source')
                    ->whereNotIn('utm_source', ['', '(none)', '(direct)']);
            })->orWhereNotNull('gclid');
        });
    }

    /** @param  string|list<string>  $value */
    protected function applyFilter(Builder $query, string $column, string|array $value): Builder
    {
        return is_array($value)
            ? $query->whereIn($column, $value)
            : $query->where($column, $value);
    }
}
