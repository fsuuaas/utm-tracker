# fsuuaas/utm-tracker

Structured, drop-in UTM capture and persistence for Laravel apps.

## What it does

- **Frontend**: `resources/js/utm-capture.js` reads UTM params (`utm_source`,
  `utm_medium`, `utm_campaign`, `utm_term`, `utm_content`, `gclid`) from the
  URL on every page load and writes them into a first-party `utm_data`
  cookie — tracking **last-touch**, **first-touch** (locked once set), and a
  **multi-touch funnel chain** (`mcf_*`, e.g. `google>newsletter>facebook`).
- **Blade components**: `<x-utm-tracker::script />` (include once per
  layout) and `<x-utm-tracker::fields />` (drop inside any
  `<form data-utm-track>`) — no per-form JS needed, population is automatic
  on `DOMContentLoaded`.
- **Backend**: `HasUtm` + `SavesUtm` traits and a polymorphic `UtmRecord`
  model (`utmable_type`/`utmable_id`) auto-persist whatever UTM fields
  arrive with a model's creation request.

## Install (as a git dependency)

In the consuming app's `composer.json`:

```json
"repositories": [
    { "type": "vcs", "url": "https://github.com/<org>/utm-tracker" }
],
"require": {
    "fsuuaas/utm-tracker": "dev-main"
}
```

```bash
composer require fsuuaas/utm-tracker:dev-main
php artisan vendor:publish --tag=utm-tracker-assets
```

### Frontend-only usage (e.g. cc-website, submits to an external API)

```blade
{{-- once per layout, before analytics tags --}}
<x-utm-tracker::script />
```

```blade
<form data-utm-track>
    <x-utm-tracker::fields />
    {{-- for a second form on the same page, avoid id collisions: --}}
    {{-- <x-utm-tracker::fields :prefix="'modal_'" /> --}}
    ...
</form>
```

### Backend usage (receiving app, e.g. breeze10)

```bash
php artisan vendor:publish --tag=utm-tracker-migrations
php artisan migrate
```

```php
use Fsuuaas\UtmTracker\Traits\HasUtm;
use Fsuuaas\UtmTracker\Traits\SavesUtm;

class Trial extends Model
{
    use HasUtm, SavesUtm;
}
```

Any model using both traits auto-saves a `UtmRecord` from the request's UTM
fields the moment it's created — no controller changes needed.

## Config

Publish and adjust if needed:

```bash
php artisan vendor:publish --tag=utm-tracker-config
```

```php
// config/utm-tracker.php
'cookie_name' => 'utm_data',
'cookie_days' => 365,
'mcf_max_touches' => 10,
'fields' => ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid'],
'model' => \Fsuuaas\UtmTracker\Models\UtmRecord::class, // override to reuse an existing model
```
