{{--
    Include once per layout, as early as possible (before analytics tags),
    e.g. right before @include('layouts._tag-manager').
--}}
@php
    // Field metadata comes from the registry, so adding a field never means
    // editing this view. Cookie and transport settings come from config.
    $utmTrackerConfig = \Fsuuaas\UtmTracker\UtmTracker::fields()->toJsConfig() + [
        'cookie_name' => config('utm-tracker.cookie_name'),
        'cookie_days' => config('utm-tracker.cookie_days'),
        'cookie_domain' => config('utm-tracker.cookie_domain'),
        'same_site' => config('utm-tracker.same_site', 'Lax'),
        'mcf_max_touches' => config('utm-tracker.mcf_max_touches'),
        'max_bytes' => config('utm-tracker.max_bytes', 3500),
        'legacy_cookies' => config('utm-tracker.legacy_cookies', []),
    ];
@endphp
<script>
    window.UtmTrackerConfig = @json($utmTrackerConfig);
</script>
<script src="{{ asset('vendor/utm-tracker/utm-capture.js') }}"></script>
