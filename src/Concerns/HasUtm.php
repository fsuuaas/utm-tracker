<?php

declare(strict_types=1);

namespace Fsuuaas\UtmTracker\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * Gives a model its UTM records.
 *
 * Prefer $model->utmRecord for reading. The plural relation exists because the
 * storage genuinely permits several rows per model, but in practice every read
 * wants the latest one, and `->utm->first()` scattered through call sites is a
 * sign the singular accessor was missing rather than that the data is a list.
 */
trait HasUtm
{
    /** Every UTM record attached to this model. */
    public function utm(): MorphMany
    {
        return $this->morphMany(config('utm-tracker.model'), 'utmable');
    }

    /** The current attribution for this model. */
    public function utmRecord(): MorphOne
    {
        return $this->morphOne(config('utm-tracker.model'), 'utmable')->latestOfMany();
    }

    public function hasUtm(): bool
    {
        return $this->utm()->exists();
    }
}
