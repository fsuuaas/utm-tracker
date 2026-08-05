{{--
    Hidden UTM fields for any <form data-utm-track>. `id` is namespaced with an
    optional prefix so multiple forms can coexist on one page without id
    collisions; `name` stays clean so the backend always receives plain keys.

    Usage: <x-utm-tracker::fields :prefix="'modal_'" />
--}}
@props(['prefix' => ''])
@foreach (\Fsuuaas\UtmTracker\UtmTracker::fields()->clientAttributes() as $utmField)
<input type="hidden" name="{{ $utmField }}" id="{{ $prefix }}{{ $utmField }}" data-utm-field="{{ $utmField }}" />
@endforeach
