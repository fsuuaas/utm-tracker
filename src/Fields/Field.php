<?php

declare(strict_types=1);

namespace Fsuuaas\UtmTracker\Fields;

/**
 * One tracked field, and everything derived from it: the database columns, the
 * hidden inputs, the JS capture list, and the truncation limits.
 *
 * Declaring a field here is the only edit needed to add one — the migration,
 * $fillable, the Blade component and the JS config all read from the registry.
 */
final class Field
{
    /**
     * @param  string  $name  Base column name, e.g. "utm_source".
     * @param  Origin  $origin  Where the value comes from; Server-origin fields are never read from request input.
     * @param  ColumnType  $column  Database column shape.
     * @param  int  $limit  Max characters kept for this value, in both JS and PHP.
     * @param  bool  $first  Emit a first_<name> companion column (locked first touch).
     * @param  bool  $mcf  Emit an mcf_<name> companion column (multi-touch chain).
     * @param  int  $chainLimit  Max characters for the mcf_<name> chain as a whole.
     * @param  bool  $indexed  Add a database index; set for columns you actually filter on.
     * @param  string|null  $param  Query-string parameter, when it differs from $name.
     */
    public function __construct(
        public readonly string $name,
        public readonly Origin $origin = Origin::Query,
        public readonly ColumnType $column = ColumnType::String,
        public readonly int $limit = 120,
        public readonly bool $first = true,
        public readonly bool $mcf = true,
        public readonly int $chainLimit = 512,
        public readonly bool $indexed = false,
        public readonly ?string $param = null,
    ) {}

    /** The query-string parameter this field is captured from. */
    public function param(): string
    {
        return $this->param ?? $this->name;
    }

    public function firstName(): string
    {
        return 'first_'.$this->name;
    }

    public function mcfName(): string
    {
        return 'mcf_'.$this->name;
    }

    /**
     * Every column this field contributes, in table order.
     *
     * @return list<string>
     */
    public function columns(): array
    {
        $columns = [$this->name];

        if ($this->first) {
            $columns[] = $this->firstName();
        }

        if ($this->mcf) {
            $columns[] = $this->mcfName();
        }

        return $columns;
    }

    /**
     * Truncation limit per column this field owns.
     *
     * @return array<string, int>
     */
    public function limits(): array
    {
        $limits = [$this->name => $this->limit];

        if ($this->first) {
            $limits[$this->firstName()] = $this->limit;
        }

        if ($this->mcf) {
            $limits[$this->mcfName()] = $this->chainLimit;
        }

        return $limits;
    }

    public function isServerDerived(): bool
    {
        return $this->origin === Origin::Server;
    }
}
