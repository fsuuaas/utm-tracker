<?php

declare(strict_types=1);

namespace Fsuuaas\UtmTracker\Support;

use Fsuuaas\UtmTracker\Fields\ColumnType;
use Fsuuaas\UtmTracker\Fields\FieldRegistry;
use Fsuuaas\UtmTracker\UtmTracker;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Translates the field registry into database columns.
 *
 * Table creation is derived, so a registered field always has somewhere to land.
 * Evolution is not: a migration never re-runs, so adding a field to the registry
 * later needs its own ALTER migration. missingColumns() exists to make that gap
 * loud rather than silent.
 */
final class UtmSchema
{
    public static function table(): string
    {
        return (string) config('utm-tracker.table', 'utm_records');
    }

    /**
     * Add every registered column to a blueprint, in table order.
     */
    public static function columns(Blueprint $table, ?FieldRegistry $registry = null): void
    {
        $registry ??= UtmTracker::fields();

        $types = self::columnTypes($registry);

        foreach ($registry->columns() as $column) {
            ($types[$column] ?? ColumnType::String)->apply($table, $column);
        }
    }

    /**
     * Registered columns the live table does not have yet.
     *
     * @return list<string>
     */
    public static function missingColumns(?FieldRegistry $registry = null): array
    {
        $registry ??= UtmTracker::fields();
        $table = self::table();

        if (! Schema::hasTable($table)) {
            return [];
        }

        $existing = Schema::getColumnListing($table);

        return array_values(array_filter(
            $registry->columns(),
            static fn (string $column) => ! in_array($column, $existing, true),
        ));
    }

    /**
     * Column name => type, including the derived first_*, mcf_* and timestamp
     * columns. Chains are always TEXT regardless of the base field's type.
     *
     * @return array<string, ColumnType>
     */
    private static function columnTypes(FieldRegistry $registry): array
    {
        $types = [];

        foreach ($registry->all() as $field) {
            $types[$field->name] = $field->column;

            if ($field->first) {
                $types[$field->firstName()] = $field->column;
            }

            if ($field->mcf) {
                $types[$field->mcfName()] = ColumnType::Text;
            }
        }

        $types[FieldRegistry::TIMESTAMP_COLUMN] = ColumnType::Text;

        return $types;
    }
}
