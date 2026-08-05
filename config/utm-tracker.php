<?php

return [

    // Name of the first-party cookie the JS capture script writes/reads.
    'cookie_name' => env('UTM_TRACKER_COOKIE', 'utm_data'),

    // How long the cookie (and therefore attribution) persists, in days.
    'cookie_days' => env('UTM_TRACKER_COOKIE_DAYS', 365),

    // Max number of touches kept in the multi-touch-funnel (mcf_*) chain.
    'mcf_max_touches' => env('UTM_TRACKER_MCF_MAX_TOUCHES', 10),

    // Query-string params captured as "current touch" UTM fields.
    'fields' => ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid'],

    // Eloquent model used by the HasUtm/SavesUtm traits. Override if a
    // consuming app already has its own UtmRecord model to migrate onto.
    'model' => \Fsuuaas\UtmTracker\Models\UtmRecord::class,
];
