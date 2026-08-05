<?php

declare(strict_types=1);

namespace Fsuuaas\UtmTracker\Fields;

/**
 * The single source of truth for which fields are tracked.
 *
 * Everything else derives from here: the migration columns, UtmRecord::$fillable,
 * the hidden inputs rendered by <x-utm-tracker::fields />, the JS capture list,
 * and the truncation limits applied on both sides. Adding a field is one call to
 * add() — no migration, model, Blade or JS edits.
 */
final class FieldRegistry
{
    /** Column holding the ">"-joined epoch-millisecond chain of touch times. */
    public const TIMESTAMP_COLUMN = 'mcf_timestamp';

    public const TIMESTAMP_LIMIT = 256;

    /** @var array<string, Field> keyed by field name, insertion-ordered */
    private array $fields = [];

    /**
     * The default field set. Limits carry over from the tuned values already in
     * production, and the resulting column set is identical to the utm_records
     * table consuming apps already have.
     */
    public static function default(): self
    {
        return (new self)
            // Query-string fields: last-touch, first-touch and multi-touch chain.
            ->add(new Field('utm_source', limit: 120, indexed: true))
            ->add(new Field('utm_medium', limit: 120, indexed: true))
            ->add(new Field('utm_campaign', limit: 160, indexed: true))
            ->add(new Field('utm_term', limit: 160))
            ->add(new Field('utm_content', limit: 160))
            // gclid is worth locking a first touch for, but chaining it is noise.
            ->add(new Field('gclid', limit: 120, mcf: false, indexed: true))

            // Context derived by the browser.
            ->add(new Field('referrer', Origin::Client, ColumnType::String, 512, first: false, mcf: false))
            ->add(new Field('landing_page', Origin::Client, ColumnType::Text, 512, first: false, mcf: false))
            ->add(new Field('session_count', Origin::Client, ColumnType::UnsignedInt, 12, first: false, mcf: false))

            // Context derived server-side. Never accepted from request input.
            ->add(new Field('ip_address', Origin::Server, ColumnType::Ip, 45, first: false, mcf: false, indexed: true))
            ->add(new Field('user_agent', Origin::Server, ColumnType::Text, 512, first: false, mcf: false));
    }

    public function add(Field $field): self
    {
        $this->fields[$field->name] = $field;

        return $this;
    }

    public function remove(string $name): self
    {
        unset($this->fields[$name]);

        return $this;
    }

    public function get(string $name): ?Field
    {
        return $this->fields[$name] ?? null;
    }

    public function has(string $name): bool
    {
        return isset($this->fields[$name]);
    }

    /** @return array<string, Field> */
    public function all(): array
    {
        return $this->fields;
    }

    /**
     * Fields carrying a first_* or mcf_* companion — the "touch" fields, as
     * opposed to flat context like referrer or ip_address.
     *
     * @return list<Field>
     */
    public function touchFields(): array
    {
        return array_values(array_filter(
            $this->fields,
            fn (Field $f) => $f->first || $f->mcf,
        ));
    }

    /** @return list<Field> */
    public function contextFields(): array
    {
        return array_values(array_filter(
            $this->fields,
            fn (Field $f) => ! $f->first && ! $f->mcf,
        ));
    }

    /** @return list<Field> */
    public function queryFields(): array
    {
        return array_values(array_filter(
            $this->fields,
            fn (Field $f) => $f->origin === Origin::Query,
        ));
    }

    /**
     * Query-string parameter names the browser captures.
     *
     * @return list<string>
     */
    public function queryParams(): array
    {
        return array_map(fn (Field $f) => $f->param(), $this->queryFields());
    }

    /**
     * Every persisted column, in table order: touch bases, then first_*, then
     * mcf_* plus the shared timestamp chain, then flat context columns. This
     * ordering reproduces the schema already deployed in consuming apps.
     *
     * @return list<string>
     */
    public function columns(): array
    {
        $touch = $this->touchFields();

        $bases = array_map(fn (Field $f) => $f->name, $touch);

        $firsts = array_map(
            fn (Field $f) => $f->firstName(),
            array_values(array_filter($touch, fn (Field $f) => $f->first)),
        );

        $mcfs = array_map(
            fn (Field $f) => $f->mcfName(),
            array_values(array_filter($touch, fn (Field $f) => $f->mcf)),
        );

        if ($mcfs !== []) {
            $mcfs[] = self::TIMESTAMP_COLUMN;
        }

        $context = array_map(fn (Field $f) => $f->name, $this->contextFields());

        return array_merge($bases, $firsts, $mcfs, $context);
    }

    /**
     * Columns for UtmRecord::$fillable.
     *
     * @return list<string>
     */
    public function attributes(): array
    {
        return $this->columns();
    }

    /**
     * Columns the browser supplies — everything except server-derived fields.
     * These become the hidden inputs and the keys stored in the cookie.
     *
     * @return list<string>
     */
    public function clientAttributes(): array
    {
        $server = $this->serverColumns();

        return array_values(array_filter(
            $this->columns(),
            fn (string $column) => ! in_array($column, $server, true),
        ));
    }

    /**
     * Columns resolved server-side. Never rendered as inputs, never read from
     * request input.
     *
     * @return list<string>
     */
    public function serverColumns(): array
    {
        $columns = [];

        foreach ($this->fields as $field) {
            if ($field->isServerDerived()) {
                $columns = array_merge($columns, $field->columns());
            }
        }

        return $columns;
    }

    /**
     * Truncation limit per column, applied identically in JS and PHP.
     *
     * @return array<string, int>
     */
    public function limits(): array
    {
        $limits = [];

        foreach ($this->fields as $field) {
            $limits += $field->limits();
        }

        if (in_array(self::TIMESTAMP_COLUMN, $this->columns(), true)) {
            $limits[self::TIMESTAMP_COLUMN] = self::TIMESTAMP_LIMIT;
        }

        return $limits;
    }

    /**
     * Base columns flagged for indexing.
     *
     * @return list<string>
     */
    public function indexedColumns(): array
    {
        return array_values(array_map(
            fn (Field $f) => $f->name,
            array_filter($this->fields, fn (Field $f) => $f->indexed),
        ));
    }

    /**
     * The field-derived half of window.UtmTrackerConfig. Cookie and transport
     * settings are merged in by the Script component from config.
     *
     * @return array<string, mixed>
     */
    public function toJsConfig(): array
    {
        $first = [];
        $mcf = [];

        foreach ($this->queryFields() as $field) {
            if ($field->first) {
                $first[] = $field->name;
            }
            if ($field->mcf) {
                $mcf[] = $field->name;
            }
        }

        return [
            'params' => $this->queryParams(),
            'fields' => $this->clientAttributes(),
            'first' => $first,
            'mcf' => $mcf,
            'limits' => $this->limits(),
            'timestamp_key' => self::TIMESTAMP_COLUMN,
        ];
    }
}
