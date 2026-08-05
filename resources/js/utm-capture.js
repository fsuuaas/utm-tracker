/**
 * UtmTracker — first-party UTM capture + form population.
 * Config injected by <x-utm-tracker::script /> as window.UtmTrackerConfig.
 */
(function (window, document) {
    var cfg = window.UtmTrackerConfig || {};
    var COOKIE_NAME = cfg.cookie_name || 'utm_data';
    var COOKIE_DAYS = cfg.cookie_days || 365;
    var MCF_MAX = cfg.mcf_max_touches || 10;
    var FIELDS = cfg.fields || ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid'];

    function readCookie() {
        var match = document.cookie.match(new RegExp('(?:^|; )' + COOKIE_NAME + '=([^;]*)'));
        if (!match) return {};
        try {
            return JSON.parse(decodeURIComponent(match[1])) || {};
        } catch (e) {
            return {};
        }
    }

    function writeCookie(data) {
        var expires = new Date();
        expires.setTime(expires.getTime() + COOKIE_DAYS * 24 * 60 * 60 * 1000);
        document.cookie = COOKIE_NAME + '=' + encodeURIComponent(JSON.stringify(data)) +
            '; expires=' + expires.toUTCString() + '; path=/';
    }

    function pushToChain(chain, value) {
        var parts = chain ? chain.split('>') : [];
        parts.push(value);
        if (parts.length > MCF_MAX) parts = parts.slice(parts.length - MCF_MAX);
        return parts.join('>');
    }

    function capture() {
        var params = new URLSearchParams(window.location.search);
        var current = {};
        var hasTouch = false;

        FIELDS.forEach(function (key) {
            var value = params.get(key) || '';
            current[key] = value;
            if (value) hasTouch = true;
        });

        if (!hasTouch) return;

        var data = readCookie();

        FIELDS.forEach(function (key) {
            // last-touch: always reflects the most recent touch with UTM params
            data[key] = current[key];

            // first-touch: locked once set
            var firstKey = 'first_' + key;
            if (!data[firstKey]) data[firstKey] = current[key];

            // multi-touch funnel chain
            var mcfKey = 'mcf_' + key;
            data[mcfKey] = pushToChain(data[mcfKey], current[key]);
        });

        data.mcf_timestamp = pushToChain(data.mcf_timestamp, new Date().toISOString());

        if (!data.referrer) data.referrer = document.referrer || '';
        if (!data.landing_page) data.landing_page = window.location.href;

        writeCookie(data);
    }

    function populate(form) {
        var data = readCookie();
        if (!data || !Object.keys(data).length) return;

        form.querySelectorAll('[data-utm-field]').forEach(function (el) {
            var key = el.getAttribute('data-utm-field');
            if (key in data) el.value = data[key] || '';
        });
    }

    capture();

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('form[data-utm-track]').forEach(populate);
    });

    window.UtmTracker = { capture: capture, populate: populate, readCookie: readCookie };
})(window, document);
