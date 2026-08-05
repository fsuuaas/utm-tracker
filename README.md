# fsuuaas/utm-tracker

Structured UTM capture and persistence for Laravel apps: a first-party
capture script with proper multi-touch attribution, a Blade component that
populates any form automatically, and a polymorphic `UtmRecord` model that
attaches itself to whatever your app creates.

## What it does

- **Frontend** (`resources/js/utm-capture.js`) reads UTM params from the URL
  on every page load, classifies the traffic, and writes one first-party
  cookie tracking:
  - **last-touch** — the most recent campaign, replaced as a whole snapshot
    (a bare `?gclid=...` never blanks a previously captured `utm_source`);
  - **first-touch** — locked once, for the whole touch, the first time any
    campaign is seen;
  - a **multi-touch funnel chain** (`mcf_*`, e.g. `newsletter>google`), with
    the delimiter escaped so a campaign value containing `>` can't corrupt it;
  - **referrer classification** — organic search, social, referral or direct
    are all recorded, not just query-string UTMs. A direct visit never
    overwrites an existing campaign (configurable, see `direct_handling`).
  - It also reads and upgrades two older cookie formats in place (see
    *Migrating from an older cookie*, below) and keeps the cookie under the
    browser's ~4KB limit by shrinking the least-important fields first.
- **Blade components**: `<x-utm-tracker::script />` (include once per
  layout) and `<x-utm-tracker::fields />` (drop inside any
  `<form data-utm-track>`). Forms are populated on load, when they're
  injected into the DOM later (modals, AJAX), and again immediately before
  submit — so existing `new FormData(form)` code needs no changes.
- **Backend**: `HasUtm` + `SavesUtm` concerns and a polymorphic `UtmRecord`
  model (`utmable_type` / `utmable_id`) persist whatever UTM data arrives
  with a model's creation request. Recording is deferred until the
  surrounding transaction commits and never throws — a malformed value is
  truncated, not rejected, so attribution can never break the write it rides
  along with.

## Requirements

PHP 8.1+, Laravel 10 / 11 / 12.

## Install

```bash
composer require fsuuaas/utm-tracker
php artisan vendor:publish --tag=utm-tracker-assets
```

If you're consuming it via a VCS repository entry instead of Packagist:

```json
"repositories": [
    { "type": "vcs", "url": "https://github.com/fsuuaas/utm-tracker" }
],
"require": {
    "fsuuaas/utm-tracker": "^1.0"
}
```

## Frontend-only usage

For an app that only captures and submits attribution (forms POST to an
external API, e.g. cc-website → breeze10):

```blade
{{-- once per layout, as early as possible, before analytics tags --}}
<x-utm-tracker::script />
```

```blade
<form data-utm-track>
    <x-utm-tracker::fields />
    {{-- a second form on the same page needs a distinct id prefix: --}}
    {{-- <x-utm-tracker::fields :prefix="'modal_'" /> --}}
    ...
</form>
```

`<x-utm-tracker::fields />` renders one hidden input per field the browser is
responsible for (`utm_source`, `first_utm_source`, `mcf_utm_source`,
`referrer`, `landing_page`, `session_count`, …). `name` always stays plain
and unprefixed — only `id` takes the prefix — so the payload your backend
receives is unaffected by which form on the page it came from.

`ip_address` and `user_agent` are deliberately **never** rendered as hidden
inputs: they're resolved server-side from the request, so a submitter can't
forge them.

## Backend usage

```bash
php artisan vendor:publish --tag=utm-tracker-migrations   # fresh install only
php artisan migrate
```

```php
use Fsuuaas\UtmTracker\Concerns\HasUtm;
use Fsuuaas\UtmTracker\Concerns\SavesUtm;

class Trial extends Model
{
    use HasUtm, SavesUtm;
}
```

A model using both concerns records a `UtmRecord` the moment it's created —
no controller changes needed. Two accessors are available:

```php
$trial->utmRecord;      // MorphOne, latest touch — the one you almost always want
$trial->utm;            // MorphMany, every row ever recorded for this model
```

To capture explicitly instead of (or in addition to) the automatic hook:

```php
$trial->recordUtm();                          // resolve from the current request
$trial->recordUtm(UtmData::fromRecord($lead)); // copy attribution from another model
```

```php
// Suppress capture for a block — imports, back-fills, admin-created records.
Trial::withoutUtmCapture(function () {
    Trial::factory()->count(50)->create();
});
```

`Fsuuaas\UtmTracker\Traits\HasUtm` and `Traits\SavesUtm` still exist as thin
aliases over the `Concerns\` versions above, so an app already using the old
namespace keeps working without touching its models.

### An app that already has a `utm_records` table

Point the package at your existing model instead of publishing the
create-table migration, and add indexing separately:

```php
// config/utm-tracker.php
'model' => \App\Models\UtmRecord::class,
```

```bash
php artisan vendor:publish --tag=utm-tracker-indexes
php artisan migrate
```

### Querying

```php
UtmRecord::source('google')->campaign(['spring-sale', 'jan-promo'])->get();
UtmRecord::attributed()->between($from, $to)->count();
$record->touchChain('utm_source'); // ['newsletter', 'google'] — decoded mcf_utm_source
$record->touchTimes();            // [Carbon, Carbon, ...] — decoded mcf_timestamp
```

Scopes are named for what they filter (`source`, `medium`, `campaign`,
`term`, `content`, `gclid`, `ip`, `landingPage`, `userAgentLike`, `between`,
`attributed`) — not `withSource()` etc., since in Eloquent `with*` means
eager-loading.

## Config

```bash
php artisan vendor:publish --tag=utm-tracker-config
```

```php
// config/utm-tracker.php

'model' => \Fsuuaas\UtmTracker\Models\UtmRecord::class,
'table' => 'utm_records',

'auto_capture' => true,               // record automatically on model creation
'defer_until_commit' => true,         // wait for the enclosing transaction to commit
'single_record' => true,              // one row per model; false keeps every touch
'record_without_attribution' => true, // false = skip rows with no real campaign
'ip' => 'request',                    // 'request' | 'anonymize' | 'none'

'cookie_name' => 'utm_data',
'cookie_days' => 365,
'cookie_domain' => null,              // e.g. '.example.com' for cross-subdomain attribution
'same_site' => 'Lax',
'mcf_max_touches' => 10,
'direct_handling' => 'first_only',    // 'first_only' | 'always' | 'ignore'
'max_bytes' => 3500,                  // cookie size ceiling before browsers silently drop it
'legacy_cookies' => ['traffic_source'],

'referral_sources' => [
    'google' => 'Google', 'fb' => 'Facebook', /* ... */
],
```

`defer_until_commit` matters for tests: `RefreshDatabase` wraps everything in
a transaction that never commits, so set this to `false` in `phpunit.xml` if
your tests assert on `utm_records` rows.

### Adding a field

The field list is defined once, in the registry — not duplicated across the
migration, the model, the Blade view and the JS config. Extend it from a
service provider:

```php
use Fsuuaas\UtmTracker\Fields\Field;
use Fsuuaas\UtmTracker\UtmTracker;

UtmTracker::fields(fn ($registry) => $registry->add(
    new Field('fbclid', limit: 255, indexed: true)
));
```

A registered field with no matching column stays uncaptured on a database
that hasn't migrated for it yet. `UtmSchema::missingColumns()` reports what's
out of sync so you know when a migration is needed.

## Migrating from an older cookie

The capture script upgrades two prior formats in place, on first read, and
never deletes the old cookie — so anything else still reading it keeps
working during the transition:

- a plain-JSON `utm_data` cookie with 5 fields (the original hand-rolled
  cc-website script);
- a base64-encoded `traffic_source` cookie with 23 keys (the breeze10
  `tracking.js` format) — including converting its ISO-8601 timestamps to
  the epoch-millisecond format this package uses.

List any cookie names you want upgraded-from in `legacy_cookies`.

## JS API

```js
window.UtmTracker.data();                 // current cookie, decoded
window.UtmTracker.populate(formOrRoot);   // fill [data-utm-field] inputs under a node
window.UtmTracker.capture();              // re-run capture (rarely needed manually)
window.UtmTracker.reset();                // clear the cookie
document.addEventListener('utm-tracker:ready', () => { /* ... */ });
```

## Testing

```bash
composer install
composer test      # phpunit
composer lint       # pint --test
composer analyse     # phpstan
```

## License

MIT.
