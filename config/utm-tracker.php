<?php

return [

    /*
    |---------------------------------------------------------------------------
    | Storage
    |---------------------------------------------------------------------------
    */

    // Eloquent model used by the HasUtm/SavesUtm concerns. Point this at your own
    // subclass if the app already has a UtmRecord it needs to keep.
    'model' => \Fsuuaas\UtmTracker\Models\UtmRecord::class,

    'table' => env('UTM_TRACKER_TABLE', 'utm_records'),

    /*
    |---------------------------------------------------------------------------
    | Capture behaviour
    |---------------------------------------------------------------------------
    */

    // Record automatically when a model using SavesUtm is created. Turn this off
    // to capture only where you explicitly call $model->recordUtm().
    'auto_capture' => env('UTM_TRACKER_AUTO_CAPTURE', true),

    // Wait for the surrounding database transaction to commit before writing.
    // Keeps a failed UTM insert from poisoning the transaction (fatal on
    // Postgres) and from recording attribution for a parent that rolled back.
    //
    // Test suites using RefreshDatabase run inside a transaction that never
    // commits, so set this to false in phpunit.xml if your tests assert on
    // utm_records rows.
    'defer_until_commit' => env('UTM_TRACKER_DEFER', true),

    // One record per model: a resubmission updates the existing row instead of
    // accumulating duplicates. Set false to keep an append-only audit trail.
    'single_record' => env('UTM_TRACKER_SINGLE_RECORD', true),

    // Every web request carries an IP and a user agent, so leaving this true
    // means admin-created records also get a (campaign-less) UTM row. Set false
    // to record only genuine marketing touches.
    'record_without_attribution' => env('UTM_TRACKER_RECORD_ALL', true),

    // How the client IP is stored: 'request' (as resolved through TrustProxies),
    // 'anonymize' (host portion zeroed), or 'none'.
    'ip' => env('UTM_TRACKER_IP', 'request'),

    /*
    |---------------------------------------------------------------------------
    | Browser
    |---------------------------------------------------------------------------
    */

    // First-party cookie the capture script reads and writes.
    'cookie_name' => env('UTM_TRACKER_COOKIE', 'utm_data'),

    // How long attribution persists, in days.
    'cookie_days' => env('UTM_TRACKER_COOKIE_DAYS', 365),

    // Cookie domain. Set to a bare domain (".example.com") so attribution follows
    // the visitor across subdomains; null keeps the cookie host-only.
    'cookie_domain' => env('UTM_TRACKER_COOKIE_DOMAIN'),

    'same_site' => env('UTM_TRACKER_SAME_SITE', 'Lax'),

    // Max touches kept in each multi-touch (mcf_*) chain.
    'mcf_max_touches' => env('UTM_TRACKER_MCF_MAX_TOUCHES', 10),

    // What a direct visit does to existing attribution:
    //   'first_only' — record direct only when nothing is credited yet. This is
    //                  the default and matches the last non-direct click model
    //                  analytics tools use: someone who arrived via a campaign
    //                  and later types the URL is still credited to it.
    //   'always'     — treat every direct visit as a new touch.
    //   'ignore'     — never record direct traffic.
    'direct_handling' => env('UTM_TRACKER_DIRECT_HANDLING', 'first_only'),

    // Hard ceiling on the encoded cookie, in bytes. Browsers drop cookies over
    // roughly 4KB silently, which would stop attribution updating with no error.
    'max_bytes' => env('UTM_TRACKER_MAX_BYTES', 3500),

    // Older cookie formats to read and upgrade from. Values are never written
    // back and the old cookies are left in place, so existing readers keep
    // working until you retire them.
    'legacy_cookies' => ['traffic_source'],

    /*
    |---------------------------------------------------------------------------
    | Reporting
    |---------------------------------------------------------------------------
    */

    // Display labels for raw utm_source values, used by UtmTracker::referralSource().
    // Anything unlisted falls back to title-casing the raw value.
    'referral_sources' => [
        'google' => 'Google',
        'googleads' => 'GoogleAds',
        'google-ads' => 'GoogleAds',
        'facebook' => 'Facebook',
        'fb' => 'Facebook',
        'instagram' => 'Instagram',
        'linkedin' => 'LinkedIn',
        'twitter' => 'Twitter',
        'x' => 'Twitter',
        'bing' => 'Bing',
        'youtube' => 'YouTube',
        'newsletter' => 'Newsletter',
    ],
];
