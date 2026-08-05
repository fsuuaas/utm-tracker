<?php

namespace Fsuuaas\UtmTracker\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class UtmRecord extends Model
{
    protected $table = 'utm_records';

    protected $fillable = [
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid',
        'first_utm_source', 'first_utm_medium', 'first_utm_campaign', 'first_utm_term',
        'first_utm_content', 'first_gclid',
        'mcf_utm_source', 'mcf_utm_medium', 'mcf_utm_campaign', 'mcf_utm_term',
        'mcf_utm_content', 'mcf_timestamp',
        'referrer', 'landing_page', 'ip_address', 'user_agent', 'session_count',
    ];

    public function utmable()
    {
        return $this->morphTo();
    }

    public function scopeByCampaign(Builder $q, string $campaign)
    {
        return $q->where('utm_campaign', $campaign);
    }

    public function scopeFromDateRange(Builder $q, $from, $to)
    {
        return $q->whereBetween('created_at', [$from, $to]);
    }

    public function scopeWithSource(Builder $q, string $source)
    {
        return $q->where('utm_source', $source);
    }

    public function scopeWithMedium(Builder $q, string $medium)
    {
        return $q->where('utm_medium', $medium);
    }

    public function scopeWithTerm(Builder $q, string $term)
    {
        return $q->where('utm_term', $term);
    }

    public function scopeWithGclid(Builder $q, string $gclid)
    {
        return $q->where('gclid', $gclid);
    }

    public function scopeWithReferrer(Builder $q, string $referrer)
    {
        return $q->where('referrer', $referrer);
    }

    public function scopeWithLandingPage(Builder $q, string $landingPage)
    {
        return $q->where('landing_page', $landingPage);
    }

    public function scopeWithIpAddress(Builder $q, string $ipAddress)
    {
        return $q->where('ip_address', $ipAddress);
    }

    public function scopeWithUserAgent(Builder $q, string $userAgent)
    {
        return $q->where('user_agent', 'like', '%'.$userAgent.'%');
    }
}
