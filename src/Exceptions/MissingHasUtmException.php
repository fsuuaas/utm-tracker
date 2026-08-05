<?php

declare(strict_types=1);

namespace Fsuuaas\UtmTracker\Exceptions;

use Fsuuaas\UtmTracker\Concerns\HasUtm;
use LogicException;

/**
 * Thrown when a model records UTM data without declaring the relationship.
 *
 * Without this the failure surfaces as "Call to undefined method utm()" from
 * inside a model event, which points at the package rather than at the model
 * that is actually misconfigured.
 */
final class MissingHasUtmException extends LogicException
{
    public static function for(string $model): self
    {
        return new self(sprintf(
            '%s records UTM data but does not use the %s trait, so it has no utm() relation. Add `use %s;` to the model.',
            $model,
            HasUtm::class,
            HasUtm::class,
        ));
    }
}
