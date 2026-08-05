<?php

namespace Fsuuaas\UtmTracker\Traits;

/**
 * Auto-persists UTM data submitted alongside a model's creation request.
 * Requires the model to also use HasUtm.
 */
trait SavesUtm
{
    public static function bootSavesUtm()
    {
        static::created(function ($model) {
            $request = request();
            $utmData = [];

            $keys = [
                'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid',
                'first_utm_source', 'first_utm_medium', 'first_utm_campaign', 'first_utm_term',
                'first_utm_content', 'first_gclid',
                'mcf_utm_source', 'mcf_utm_medium', 'mcf_utm_campaign', 'mcf_utm_term',
                'mcf_utm_content', 'mcf_timestamp',
                'referrer', 'landing_page', 'ip_address', 'user_agent', 'session_count',
            ];

            foreach ($keys as $key) {
                if ($key === 'ip_address') {
                    $value = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? null;
                } elseif ($key === 'user_agent') {
                    $value = $request->header('User-Agent');
                } elseif ($key === 'referrer' && ! $request->filled('referrer')) {
                    $value = $request->header('referer');
                } else {
                    $value = $request->filled($key) ? $request->input($key) : null;
                }

                if (! is_null($value)) {
                    $utmData[$key] = $value;
                }
            }

            if (! empty($utmData)) {
                $model->utm()->create($utmData);
            }
        });
    }
}
