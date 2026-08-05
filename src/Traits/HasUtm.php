<?php

namespace Fsuuaas\UtmTracker\Traits;

/**
 * Adds a polymorphic one-to-many relationship to UtmRecord.
 */
trait HasUtm
{
    public function utm()
    {
        return $this->morphMany(config('utm-tracker.model'), 'utmable');
    }

    public function hasUtm(): bool
    {
        return $this->utm()->exists();
    }
}
