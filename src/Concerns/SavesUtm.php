<?php

declare(strict_types=1);

namespace Fsuuaas\UtmTracker\Concerns;

use Fsuuaas\UtmTracker\Contracts\RecordsUtm;
use Fsuuaas\UtmTracker\UtmData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

/**
 * Captures UTM data automatically when the model is created.
 *
 * Requires HasUtm. Three properties matter here, and all three were absent from
 * the original implementation:
 *
 *  - Recording never breaks the business write. The parent row is already
 *    committed by the time `created` fires, so an exception here used to leave an
 *    orphaned record, return a 500, and skip the notifications that followed.
 *  - Recording is deferred until the surrounding transaction commits. On Postgres
 *    a failed statement poisons the whole transaction, so catching the error
 *    in place is not enough.
 *  - Console context is skipped. Seeders, tinker and queued jobs have a synthetic
 *    request whose "attribution" is meaningless.
 */
trait SavesUtm
{
    /** Per-class suspension flag, toggled by withoutUtmCapture(). */
    protected static bool $utmCaptureSuspended = false;

    public static function bootSavesUtm(): void
    {
        static::created(function (Model $model): void {
            if (static::$utmCaptureSuspended) {
                return;
            }

            if (! config('utm-tracker.auto_capture', true)) {
                return;
            }

            if (! static::utmContextIsCapturable()) {
                return;
            }

            $model->captureUtm();
        });
    }

    /**
     * Run a callback with automatic capture turned off for this model class.
     * Useful for imports and back-fills that would otherwise attribute every row
     * to whoever happened to trigger the run.
     */
    public static function withoutUtmCapture(callable $callback): mixed
    {
        $previous = static::$utmCaptureSuspended;
        static::$utmCaptureSuspended = true;

        try {
            return $callback();
        } finally {
            static::$utmCaptureSuspended = $previous;
        }
    }

    /**
     * Record UTM data explicitly. Unlike the automatic hook this reports failure
     * by throwing, because the caller asked for it and can decide what to do.
     */
    public function recordUtm(?UtmData $data = null): ?Model
    {
        return app(RecordsUtm::class)->handle($this, $data);
    }

    /**
     * The automatic path: deferred where possible, and never fatal.
     */
    protected function captureUtm(): void
    {
        $run = function (): void {
            try {
                app(RecordsUtm::class)->handle($this);
            } catch (Throwable $e) {
                // Attribution is not worth failing a request over. Surface it
                // through the app's error handler rather than swallowing it.
                report($e);
            }
        };

        $connection = $this->getConnection();

        // Outside a transaction there is nothing to wait for. Note that test
        // suites using RefreshDatabase run inside one that never commits, so
        // they should set utm-tracker.defer_until_commit to false.
        if (! config('utm-tracker.defer_until_commit', true) || $connection->transactionLevel() === 0) {
            $run();

            return;
        }

        try {
            $connection->afterCommit($run);
        } catch (RuntimeException) {
            // Connection has no transactions manager (bare or hand-built setups).
            $run();
        }
    }

    protected static function utmContextIsCapturable(): bool
    {
        $app = app();

        if ($app->runningInConsole() && ! $app->runningUnitTests()) {
            return false;
        }

        return $app->bound('request') && $app->make('request') instanceof Request;
    }
}
