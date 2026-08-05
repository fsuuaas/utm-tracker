/**
 * UtmTracker — first-party UTM capture and form population.
 *
 * Config is injected by <x-utm-tracker::script /> as window.UtmTrackerConfig.
 *
 * State model: ONE cookie, read-modify-write, never deleted. The consolidate-
 * then-delete approach loses all attribution on the second pageview, because
 * the rebuild reads a jar the previous pageview emptied.
 *
 * A "touch" is an atomic classified snapshot. Last-touch is replaced whole or
 * not at all, so a bare ?gclid= can never blank a previously captured
 * utm_source. Traffic is always classified — organic, social, referral and
 * direct all produce a touch, rather than being dropped for lacking utm_*.
 */
(function (window, document) {
    'use strict';

    var cfg = window.UtmTrackerConfig || {};

    var COOKIE = cfg.cookie_name || 'utm_data';
    var DAYS = parseInt(cfg.cookie_days, 10) || 365;
    var DOMAIN = cfg.cookie_domain || null;
    var SAME_SITE = cfg.same_site || 'Lax';
    var MAX_TOUCHES = parseInt(cfg.mcf_max_touches, 10) || 10;
    var MAX_BYTES = parseInt(cfg.max_bytes, 10) || 3500;
    var LEGACY = cfg.legacy_cookies || [];

    var PARAMS = cfg.params || ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid'];
    var FIRST = cfg.first || ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'gclid'];
    var MCF = cfg.mcf || ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];
    var LIMITS = cfg.limits || {};
    var TS_KEY = cfg.timestamp_key || 'mcf_timestamp';

    var VERSION = 2;
    var NONE = '(none)';
    var DIRECT = '(direct)';
    var LOCK = '_f';
    var PRIMARY = MCF[0] || 'utm_source';

    /**
     * Control and format characters, mirroring the PHP side's \p{Cc}\p{Cf}
     * strip: C0 range, DEL, then zero-width and bidi-override characters.
     * Written as escapes — literal control bytes in source break diffs,
     * minifiers and git's text normalisation.
     */
    var CONTROL_CHARS = /[\x00-\x1F\x7F​-‏‪-‮⁠﻿]/g;

    /* ---------------------------------------------------------------------
     * Referrer classification
     * ------------------------------------------------------------------ */

    var SEARCH_ENGINES = {
        google: 'google', bing: 'bing', yahoo: 'yahoo', duckduckgo: 'duckduckgo',
        yandex: 'yandex', baidu: 'baidu', ecosia: 'ecosia', brave: 'brave',
        ask: 'ask', aol: 'aol', naver: 'naver', seznam: 'seznam', qwant: 'qwant'
    };

    var SOCIAL_SITES = {
        facebook: 'facebook', fb: 'facebook', instagram: 'instagram',
        twitter: 'twitter', x: 'twitter', t: 'twitter',
        linkedin: 'linkedin', lnkd: 'linkedin', pinterest: 'pinterest',
        reddit: 'reddit', youtube: 'youtube', youtu: 'youtube',
        tiktok: 'tiktok', whatsapp: 'whatsapp', telegram: 'telegram',
        quora: 'quora', medium: 'medium', tumblr: 'tumblr', snapchat: 'snapchat'
    };

    function hostOf(url) {
        try {
            return new URL(url, window.location.href).hostname.toLowerCase().replace(/^www\./, '');
        } catch (e) {
            return '';
        }
    }

    /** Bare registrable-ish domain. Naive on multi-part TLDs, deliberately so. */
    function bareDomain(host) {
        var parts = String(host || '').split('.');
        return parts.length > 2 ? parts.slice(-2).join('.') : host;
    }

    /** Match any label of a hostname against a known-provider map. */
    function classify(host, map) {
        var labels = String(host || '').split('.');
        for (var i = 0; i < labels.length; i++) {
            if (Object.prototype.hasOwnProperty.call(map, labels[i])) {
                return map[labels[i]];
            }
        }
        return null;
    }

    function isInternal(host) {
        if (!host) return false;
        var here = window.location.hostname.toLowerCase().replace(/^www\./, '');
        return host === here || bareDomain(host) === bareDomain(here);
    }

    /* ---------------------------------------------------------------------
     * Value handling
     * ------------------------------------------------------------------ */

    function limitFor(key) {
        var n = parseInt(LIMITS[key], 10);
        return n > 0 ? n : 255;
    }

    function clean(value, limit) {
        if (value === null || value === undefined) return '';
        var out = String(value).replace(CONTROL_CHARS, '').trim();
        return out.length > limit ? out.slice(0, limit) : out;
    }

    /* Chain encoding — must stay identical to PHP Support\Chain. */

    function chainEscape(value) {
        return String(value).split('%').join('%25').split('>').join('%3E');
    }

    function chainUnescape(value) {
        return String(value).split('%3E').join('>').split('%25').join('%');
    }

    function chainParse(chain) {
        if (!chain) return [];
        return String(chain).split('>').filter(function (p) {
            return p !== '';
        }).map(chainUnescape);
    }

    function chainBuild(touches) {
        return touches.filter(function (t) {
            return t !== '';
        }).map(chainEscape).join('>');
    }

    /**
     * Append a touch. Skips empties and consecutive duplicates. When capping,
     * the FIRST touch is kept and the middle dropped — first-touch attribution
     * is the part that cannot be reconstructed later.
     */
    function chainPush(chain, value, max) {
        if (!value) return chain || '';

        var touches = chainParse(chain);

        if (touches.length && touches[touches.length - 1] === value) {
            return chain || '';
        }

        touches.push(value);

        var cap = max || MAX_TOUCHES;
        if (cap > 0 && touches.length > cap) {
            touches = [touches[0]].concat(touches.slice(-(cap - 1)));
        }

        return chainBuild(touches);
    }

    /* ---------------------------------------------------------------------
     * Cookie I/O
     * ------------------------------------------------------------------ */

    function escapeRegExp(s) {
        return String(s).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    function rawCookie(name) {
        var m = document.cookie.match(new RegExp('(?:^|; )' + escapeRegExp(name) + '=([^;]*)'));
        return m ? m[1] : null;
    }

    function b64decode(value) {
        try {
            var bin = window.atob(value);
            return decodeURIComponent(Array.prototype.map.call(bin, function (c) {
                return '%' + ('00' + c.charCodeAt(0).toString(16)).slice(-2);
            }).join(''));
        } catch (e) {
            try {
                return window.atob(value);
            } catch (e2) {
                return null;
            }
        }
    }

    function parseMaybe(raw) {
        if (!raw) return null;

        var text = raw;
        try {
            text = decodeURIComponent(raw);
        } catch (e) { /* value was not URL-encoded */ }

        try {
            return JSON.parse(text);
        } catch (e) { /* not plain JSON — try base64 */ }

        var decoded = b64decode(text);
        if (!decoded) return null;

        try {
            return JSON.parse(decoded);
        } catch (e) {
            return null;
        }
    }

    /** cc-website's original format: plain JSON, 5 fields, no version marker. */
    function upgradeV0(blob) {
        var data = { v: VERSION };

        PARAMS.forEach(function (p) {
            if (blob[p]) data[p] = clean(blob[p], limitFor(p));
        });

        if (blob.referrer) data.referrer = clean(blob.referrer, limitFor('referrer'));
        if (blob.landing_page) data.landing_page = clean(blob.landing_page, limitFor('landing_page'));

        // The old format had no first-touch fields, but it was first-touch by
        // policy: it refused to overwrite an existing cookie. Treat it as such.
        FIRST.forEach(function (p) {
            if (blob[p]) data['first_' + p] = clean(blob[p], limitFor(p));
        });

        if (blob.utm_source) data[LOCK] = 1;

        return data;
    }

    /** breeze10's format: base64 JSON, 23 keys, already 1:1 with ours. */
    function upgradeLegacyBlob(blob) {
        var data = { v: VERSION };

        Object.keys(blob).forEach(function (key) {
            if (key === 'ip_address' || key === 'user_agent') return; // server-derived
            var value = clean(blob[key], limitFor(key));
            if (value) data[key] = value;
        });

        // That format stored ISO-8601 timestamps; ours are epoch milliseconds.
        if (data[TS_KEY]) {
            data[TS_KEY] = chainBuild(chainParse(data[TS_KEY]).map(function (t) {
                if (/^\d+$/.test(t)) return t;
                var parsed = Date.parse(t);
                return isNaN(parsed) ? '' : String(parsed);
            }));
        }

        if (data['first_' + PRIMARY]) data[LOCK] = 1;

        return data;
    }

    function read() {
        var blob = parseMaybe(rawCookie(COOKIE));

        if (blob && typeof blob === 'object') {
            return blob.v === VERSION ? blob : upgradeV0(blob);
        }

        for (var i = 0; i < LEGACY.length; i++) {
            var legacy = parseMaybe(rawCookie(LEGACY[i]));
            if (legacy && typeof legacy === 'object' && Object.keys(legacy).length) {
                // Never deleted: other readers may still depend on it.
                return upgradeLegacyBlob(legacy);
            }
        }

        return {};
    }

    function encode(data) {
        return encodeURIComponent(JSON.stringify(data));
    }

    /**
     * Keep the cookie under the browser's ~4KB limit. Over it, browsers drop the
     * write silently and attribution simply stops updating. Last-touch and
     * first-touch are never sacrificed.
     */
    function fit(data) {
        if (encode(data).length <= MAX_BYTES) return data;

        if (data.landing_page) {
            data.landing_page = data.landing_page.split('?')[0];
            if (encode(data).length <= MAX_BYTES) return data;
        }

        var shrinkable = ['mcf_utm_term', 'mcf_utm_content', 'mcf_utm_medium', TS_KEY];
        for (var i = 0; i < shrinkable.length; i++) {
            var key = shrinkable[i];
            if (!data[key]) continue;
            var touches = chainParse(data[key]);
            if (touches.length > 2) {
                data[key] = chainBuild([touches[0], touches[touches.length - 1]]);
            }
            if (encode(data).length <= MAX_BYTES) return data;
        }

        var droppable = ['referrer', 'mcf_utm_term', 'mcf_utm_content', TS_KEY, 'landing_page'];
        for (var j = 0; j < droppable.length; j++) {
            delete data[droppable[j]];
            if (encode(data).length <= MAX_BYTES) return data;
        }

        return data;
    }

    function write(data) {
        data.v = VERSION;
        fit(data);

        var maxAge = DAYS * 24 * 60 * 60;
        var expires = new Date(Date.now() + maxAge * 1000).toUTCString();

        var cookie = COOKIE + '=' + encode(data) +
            '; path=/' +
            '; max-age=' + maxAge +
            '; expires=' + expires +
            '; SameSite=' + SAME_SITE;

        if (DOMAIN) cookie += '; domain=' + DOMAIN;
        if (window.location.protocol === 'https:') cookie += '; Secure';

        document.cookie = cookie;
    }

    /* ---------------------------------------------------------------------
     * Touch resolution
     * ------------------------------------------------------------------ */

    function fill(touch) {
        PARAMS.forEach(function (p) {
            if (!touch[p]) touch[p] = NONE;
        });
        return touch;
    }

    /**
     * Classify this pageview. Returns null only for internal navigation, which
     * is not a new touch.
     */
    function resolveTouch() {
        var params = new URLSearchParams(window.location.search);
        var touch = {};
        var hasQuery = false;

        PARAMS.forEach(function (p) {
            var value = clean(params.get(p), limitFor(p));
            if (value) {
                touch[p] = value;
                hasQuery = true;
            }
        });

        // Autotagged Google Ads clicks carry gclid and often nothing else.
        if (touch.gclid) {
            if (!touch.utm_source) touch.utm_source = 'google';
            if (!touch.utm_medium) touch.utm_medium = 'cpc';
            return fill(touch);
        }

        if (hasQuery) return fill(touch);

        var referrer = document.referrer;

        if (referrer) {
            var host = hostOf(referrer);

            if (isInternal(host)) return null;

            if (host) {
                var search = classify(host, SEARCH_ENGINES);
                var social = classify(host, SOCIAL_SITES);

                touch.utm_source = clean(search || social || host, limitFor('utm_source'));
                touch.utm_medium = search ? 'organic' : (social ? 'social' : 'referral');

                return fill(touch);
            }
        }

        touch.utm_source = DIRECT;
        touch.utm_medium = DIRECT;

        return fill(touch);
    }

    function isNewTouch(touch, data) {
        for (var i = 0; i < PARAMS.length; i++) {
            var p = PARAMS[i];
            if ((touch[p] || '') !== (data[p] || '')) return true;
        }
        return false;
    }

    function isDirect(touch) {
        return touch.utm_source === DIRECT;
    }

    /** Whether the stored data already credits a real campaign. */
    function hasAttribution(data) {
        var source = data.utm_source;
        return !!source && source !== DIRECT && source !== NONE;
    }

    /**
     * Should a direct visit be recorded as a touch?
     *
     * Default is "first_only", matching the last non-direct click model every
     * analytics tool uses: someone who arrived via a campaign and later types
     * the URL should still be credited to that campaign. Recording direct
     * unconditionally would overwrite the campaign that earned the conversion.
     */
    function acceptsDirect(data) {
        var mode = cfg.direct_handling || 'first_only';

        if (mode === 'ignore') return false;
        if (mode === 'always') return true;

        return ! hasAttribution(data);
    }

    function bumpSession(data) {
        try {
            if (window.sessionStorage.getItem('utm_tracker_session')) return;
            window.sessionStorage.setItem('utm_tracker_session', '1');
        } catch (e) {
            if (data.session_count) return; // no sessionStorage: count once, ever
        }
        data.session_count = String((parseInt(data.session_count, 10) || 0) + 1);
    }

    function capture() {
        var data = read();
        var touch = resolveTouch();
        var changed = false;

        // A direct visit must not overwrite a campaign that is already credited.
        if (touch && isDirect(touch) && ! acceptsDirect(data)) {
            touch = null;
        }

        if (touch && isNewTouch(touch, data)) {
            // Last touch is replaced as a whole snapshot, never field by field.
            PARAMS.forEach(function (p) {
                data[p] = touch[p] || '';
            });

            // First touch locks once, for the whole touch — not per field, which
            // would compose a first touch that never actually happened.
            if (!data[LOCK]) {
                FIRST.forEach(function (p) {
                    data['first_' + p] = touch[p] || '';
                });
                data[LOCK] = 1;
            }

            var before = data['mcf_' + PRIMARY] || '';

            MCF.forEach(function (p) {
                if (touch[p]) {
                    data['mcf_' + p] = chainPush(data['mcf_' + p], touch[p]);
                }
            });

            // Timestamp appends only when the primary chain actually grew, so
            // mcf_timestamp stays index-aligned with mcf_<primary>.
            if ((data['mcf_' + PRIMARY] || '') !== before) {
                data[TS_KEY] = chainPush(data[TS_KEY], String(Date.now()));
            }

            changed = true;
        }

        if (!data.referrer) {
            data.referrer = clean(document.referrer, limitFor('referrer')) || NONE;
            changed = true;
        }

        if (!data.landing_page) {
            var loc = window.location;
            data.landing_page = clean(loc.pathname + loc.search, limitFor('landing_page'));
            changed = true;
        }

        var sessions = data.session_count;
        bumpSession(data);
        if (data.session_count !== sessions) changed = true;

        if (changed) write(data);

        return data;
    }

    /* ---------------------------------------------------------------------
     * Form population
     * ------------------------------------------------------------------ */

    function populate(root) {
        var data = read();
        if (!data || !Object.keys(data).length) return;

        var scope = root && root.querySelectorAll ? root : document;
        var inputs = scope.querySelectorAll('[data-utm-field]');

        for (var i = 0; i < inputs.length; i++) {
            var key = inputs[i].getAttribute('data-utm-field');
            if (key && Object.prototype.hasOwnProperty.call(data, key)) {
                inputs[i].value = data[key] === NONE ? '' : (data[key] || '');
            }
        }
    }

    function observe() {
        if (!window.MutationObserver) return;

        var pending = false;

        new MutationObserver(function (mutations) {
            if (pending) return;

            for (var i = 0; i < mutations.length; i++) {
                if (mutations[i].addedNodes.length) {
                    pending = true;
                    window.requestAnimationFrame(function () {
                        pending = false;
                        populate(document);
                    });
                    return;
                }
            }
        }).observe(document.documentElement, { childList: true, subtree: true });
    }

    /* ---------------------------------------------------------------------
     * Boot
     * ------------------------------------------------------------------ */

    capture();

    // Capture phase, so the fields are current before any submit handler
    // serialises the form. This is what lets existing FormData(form) call sites
    // keep working untouched.
    document.addEventListener('submit', function (event) {
        if (event.target && event.target.tagName === 'FORM') {
            populate(event.target);
        }
    }, true);

    function ready() {
        populate(document);
        observe();
        document.dispatchEvent(new CustomEvent('utm-tracker:ready'));
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', ready);
    } else {
        ready();
    }

    window.UtmTracker = {
        capture: capture,
        populate: populate,
        data: read,
        readCookie: read,
        chain: { parse: chainParse, build: chainBuild, push: chainPush },
        reset: function () {
            document.cookie = COOKIE + '=; path=/; max-age=0' + (DOMAIN ? '; domain=' + DOMAIN : '');
        }
    };
})(window, document);
