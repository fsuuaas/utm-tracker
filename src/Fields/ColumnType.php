<?php

declare(strict_types=1);

namespace Fsuuaas\UtmTracker\Fields;

use Illuminate\Database\Schema\Blueprint;

/**
 * The database column shape for a field.
 *
 * String deliberately uses Laravel's default varchar(255) rather than narrowing
 * to the field's truncation limit: a fresh install must stay schema-identical to
 * the utm_records table that already exists in consuming apps. The limit governs
 * truncation only, and is always well under 255.
 */
enum ColumnType
{
    case String;
    case Text;
    case Ip;
    case UnsignedInt;

    public function apply(Blueprint $table, string $name): void
    {
        match ($this) {
            self::String => $table->string($name)->nullable(),
            self::Text => $table->text($name)->nullable(),
            self::Ip => $table->ipAddress($name)->nullable(),
            self::UnsignedInt => $table->unsignedInteger($name)->nullable(),
        };
    }
}
