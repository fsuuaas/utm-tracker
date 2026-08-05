<?php

namespace Fsuuaas\UtmTracker\Traits;

use Fsuuaas\UtmTracker\Concerns\SavesUtm as Concern;

/**
 * @deprecated Use Fsuuaas\UtmTracker\Concerns\SavesUtm instead. Kept so an app
 *             can adopt the package without editing every model in one change.
 */
trait SavesUtm
{
    use Concern;
}
