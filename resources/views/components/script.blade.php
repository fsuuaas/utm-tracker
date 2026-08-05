{{--
    Include once per layout, as early as possible (before analytics tags),
    e.g. right before @include('layouts._tag-manager').
--}}
<script>
    window.UtmTrackerConfig = @json([
        'cookie_name' => config('utm-tracker.cookie_name'),
        'cookie_days' => config('utm-tracker.cookie_days'),
        'mcf_max_touches' => config('utm-tracker.mcf_max_touches'),
        'fields' => config('utm-tracker.fields'),
    ]);
</script>
<script src="{{ asset('vendor/utm-tracker/utm-capture.js') }}"></script>
