<?php

declare(strict_types=1);

namespace Fsuuaas\UtmTracker\Actions;

use Fsuuaas\UtmTracker\Contracts\RecordsUtm;
use Fsuuaas\UtmTracker\Exceptions\MissingHasUtmException;
use Fsuuaas\UtmTracker\UtmData;
use Fsuuaas\UtmTracker\UtmTracker;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Writes UTM data onto a model's polymorphic relation.
 *
 * This is the explicit API. The SavesUtm trait is a convenience wrapper around
 * it, so anything the trait does can also be done deliberately from a controller.
 */
final class RecordUtm implements RecordsUtm
{
    public function handle(Model $model, ?UtmData $data = null): ?Model
    {
        $data ??= UtmTracker::resolve();

        if ($data->isEmpty()) {
            return null;
        }

        // Every web request carries an IP and a user agent, so without this guard
        // an admin creating a record by hand would still produce a UTM row.
        if (! config('utm-tracker.record_without_attribution', true) && ! $data->hasAttribution()) {
            return null;
        }

        $this->assertTrackable($model);

        $attributes = $data->toArray();

        /** @var MorphMany $relation */
        $relation = $model->utm();

        // One record per model by default: a resubmission updates the existing
        // row rather than accumulating duplicates that every read path then has
        // to ->first() past.
        if (config('utm-tracker.single_record', true)) {
            return $relation->updateOrCreate([], $attributes);
        }

        return $relation->create($attributes);
    }

    private function assertTrackable(Model $model): void
    {
        if (! method_exists($model, 'utm')) {
            throw MissingHasUtmException::for($model::class);
        }
    }
}
