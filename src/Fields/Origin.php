<?php

declare(strict_types=1);

namespace Fsuuaas\UtmTracker\Fields;

/**
 * Where a field's value comes from — which decides whether it may be read from
 * the request at all.
 */
enum Origin
{
    /** Read from the URL query string, round-trips through the cookie and a hidden input. */
    case Query;

    /** Derived by the browser (referrer, landing_page, session_count) and sent as a hidden input. */
    case Client;

    /**
     * Derived server-side from the request itself (ip_address, user_agent).
     * Never rendered as a hidden input and never read from request input — accepting
     * these from the client would let anyone forge them.
     */
    case Server;
}
