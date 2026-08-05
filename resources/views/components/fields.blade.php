{{--
    Hidden UTM fields for any <form data-utm-track>. `id` is namespaced with an
    optional prefix so multiple forms can coexist on one page without id
    collisions; `name` stays clean so the backend always receives plain keys.

    Usage: <x-utm-tracker::fields :prefix="'modal_'" />
--}}
@php($prefix = $prefix ?? '')
<input type="hidden" name="utm_source" id="{{ $prefix }}utm_source" data-utm-field="utm_source" />
<input type="hidden" name="utm_medium" id="{{ $prefix }}utm_medium" data-utm-field="utm_medium" />
<input type="hidden" name="utm_campaign" id="{{ $prefix }}utm_campaign" data-utm-field="utm_campaign" />
<input type="hidden" name="utm_term" id="{{ $prefix }}utm_term" data-utm-field="utm_term" />
<input type="hidden" name="utm_content" id="{{ $prefix }}utm_content" data-utm-field="utm_content" />
<input type="hidden" name="gclid" id="{{ $prefix }}gclid" data-utm-field="gclid" />
<input type="hidden" name="first_utm_source" id="{{ $prefix }}first_utm_source" data-utm-field="first_utm_source" />
<input type="hidden" name="first_utm_medium" id="{{ $prefix }}first_utm_medium" data-utm-field="first_utm_medium" />
<input type="hidden" name="first_utm_campaign" id="{{ $prefix }}first_utm_campaign" data-utm-field="first_utm_campaign" />
<input type="hidden" name="first_utm_term" id="{{ $prefix }}first_utm_term" data-utm-field="first_utm_term" />
<input type="hidden" name="first_utm_content" id="{{ $prefix }}first_utm_content" data-utm-field="first_utm_content" />
<input type="hidden" name="first_gclid" id="{{ $prefix }}first_gclid" data-utm-field="first_gclid" />
<input type="hidden" name="mcf_utm_source" id="{{ $prefix }}mcf_utm_source" data-utm-field="mcf_utm_source" />
<input type="hidden" name="mcf_utm_medium" id="{{ $prefix }}mcf_utm_medium" data-utm-field="mcf_utm_medium" />
<input type="hidden" name="mcf_utm_campaign" id="{{ $prefix }}mcf_utm_campaign" data-utm-field="mcf_utm_campaign" />
<input type="hidden" name="mcf_utm_term" id="{{ $prefix }}mcf_utm_term" data-utm-field="mcf_utm_term" />
<input type="hidden" name="mcf_utm_content" id="{{ $prefix }}mcf_utm_content" data-utm-field="mcf_utm_content" />
<input type="hidden" name="mcf_timestamp" id="{{ $prefix }}mcf_timestamp" data-utm-field="mcf_timestamp" />
<input type="hidden" name="referrer" id="{{ $prefix }}referrer" data-utm-field="referrer" />
<input type="hidden" name="landing_page" id="{{ $prefix }}landing_page" data-utm-field="landing_page" />
