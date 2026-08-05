<?php

namespace Fsuuaas\UtmTracker\Traits;

use Fsuuaas\UtmTracker\Concerns\HasUtm as Concern;

/**
 * @deprecated Use Fsuuaas\UtmTracker\Concerns\HasUtm instead. Kept so an app can
 *             adopt the package without editing every model in one change.
 */
trait HasUtm
{
    use Concern;
}
