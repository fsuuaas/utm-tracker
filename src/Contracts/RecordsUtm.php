<?php

declare(strict_types=1);

namespace Fsuuaas\UtmTracker\Contracts;

use Fsuuaas\UtmTracker\UtmData;
use Illuminate\Database\Eloquent\Model;

/**
 * The sink UTM data is written to.
 *
 * Bound to the Eloquent implementation by default; rebind it to push records to
 * a queue, a warehouse, or an analytics service instead.
 */
interface RecordsUtm
{
    /**
     * Attach UTM data to a model.
     *
     * @param  UtmData|null  $data  Resolved from the current request when omitted.
     * @return Model|null The written record, or null when there was nothing to record.
     */
    public function handle(Model $model, ?UtmData $data = null): ?Model;
}
