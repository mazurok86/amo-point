/**
 * AmoPoint visit tracker — drop-in third-party collector.
 *
 * Embed:
 *   <script async src="https://your-host/track.js"></script>
 *
 * Sends a single POST to `<script-origin>/api/visits` per page load with:
 *   - visitor_uid  — UUID v4, stored in localStorage of the embedding site
 *   - page_url     — location.href
 *   - referrer     — document.referrer (omitted if empty)
 *   - user_agent   — navigator.userAgent
 *
 * Wire format:
 *   application/x-www-form-urlencoded via URLSearchParams. This is a
 *   "CORS-safelisted" content type — the browser does NOT trigger a
 *   preflight OPTIONS, so the collector works on any origin.
 *
 * Transport (in order, first that succeeds wins):
 *   1. navigator.sendBeacon — fire-and-forget, survives unload.
 *   2. fetch(..., {keepalive: true}) — fallback for browsers without sendBeacon.
 *
 * Robustness:
 *   - Whole file wrapped in try/catch via IIFE — a failure in the
 *     tracker MUST NOT break the host page.
 *   - localStorage disabled (private mode, blocked) — visitor_uid is
 *     omitted; the server falls back to md5(ip + ua + hour) per visit.
 *   - Endpoint cannot be resolved — script silently no-ops.
 */
(function () {
    'use strict';

    try {
        // 1. Find our own <script> tag → derive backend origin from its src.
        function findScriptUrl() {
            if (document.currentScript && document.currentScript.src) {
                return document.currentScript.src;
            }
            const scripts = document.getElementsByTagName('script');
            for (let i = scripts.length - 1; i >= 0; i--) {
                if (scripts[i].src && /\/track\.js(\?|$)/.test(scripts[i].src)) {
                    return scripts[i].src;
                }
            }
            return null;
        }

        const scriptUrl = findScriptUrl();
        if (!scriptUrl) {
            return;
        }
        const endpoint = new URL('/api/visits', scriptUrl).toString();

        // 2. visitor_uid: get or create UUID v4 in localStorage of host site.
        const STORAGE_KEY = '__amo_visit_uid';
        let visitorUid = null;
        try {
            visitorUid = localStorage.getItem(STORAGE_KEY);
            if (!visitorUid) {
                visitorUid = (window.crypto && crypto.randomUUID)
                    ? crypto.randomUUID()
                    : 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
                        const r = (Math.random() * 16) | 0;
                        return (c === 'x' ? r : (r & 0x3) | 0x8).toString(16);
                    });
                localStorage.setItem(STORAGE_KEY, visitorUid);
            }
        } catch (_e) {
            // localStorage disabled — server will use md5 fallback
        }

        // 3. Build form-encoded payload.
        const params = new URLSearchParams();
        if (visitorUid) {
            params.set('visitor_uid', visitorUid);
        }
        params.set('page_url', location.href);
        if (document.referrer) {
            params.set('referrer', document.referrer);
        }
        params.set('user_agent', navigator.userAgent);

        // 4. Try sendBeacon first.
        try {
            if (navigator.sendBeacon && navigator.sendBeacon(endpoint, params)) {
                return;
            }
        } catch (_e) {
            // fall through to fetch
        }

        // 5. Fallback to fetch with keepalive.
        if (window.fetch) {
            fetch(endpoint, {
                method: 'POST',
                body: params,
                keepalive: true,
            }).catch(function () {
                // swallow — tracker must never throw to host page
            });
        }
    } catch (_e) {
        // global safety net
    }
})();
