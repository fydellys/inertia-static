# Varnish 7 + varnish-modules (xkey) configuration matching the "varnish" driver.
vcl 4.1;

import std;
import xkey;

backend default { .host = "app"; .port = "8000"; }

acl purge { "localhost"; "127.0.0.1"; "10.0.0.0"/8; "172.16.0.0"/12; }

sub vcl_recv {
    unset req.http.X-Cache-Pass;

    if (req.method == "PURGE" || req.method == "BAN") {
        if (!client.ip ~ purge) { return (synth(405, "Not allowed")); }

        # Tag purges: "xkey-softpurge: posts post:1" marks stale (served in grace while
        # refetched); "xkey-purge" removes now.
        if (req.http.xkey-softpurge) {
            set req.http.n-gone = xkey.softpurge(req.http.xkey-softpurge);
            return (synth(200, "Soft-purged " + req.http.n-gone));
        }
        if (req.http.xkey-purge) {
            set req.http.n-gone = xkey.purge(req.http.xkey-purge);
            return (synth(200, "Purged " + req.http.n-gone));
        }

        # Prefix purge: "X-Ban-Prefix: /blog" (+ optional X-Ban-Host)
        if (req.method == "BAN") {
            if (req.http.X-Ban-Host) {
                ban("obj.http.x-host == " + req.http.X-Ban-Host + " && obj.http.x-url ~ ^" + req.http.X-Ban-Prefix);
            } else {
                ban("obj.http.x-url ~ ^" + req.http.X-Ban-Prefix);
            }
            return (synth(200, "Banned"));
        }

        # URL purge
        return (purge);
    }

    if (req.method != "GET" && req.method != "HEAD") {
        return (pass);
    }

    # Signed requests and preview mode (StaticCache::enablePreview()): never from the cache.
    if (req.http.Authorization || req.http.Cookie ~ "inertia_static_preview=") {
        return (pass);
    }

    # Inertia visits on HTML URLs must never be answered from the cache
    # (Varnish honours "Vary: X-Inertia", this is defense in depth).
    if (req.http.X-Inertia && req.url !~ "^/__inertia/") {
        return (pass);
    }

    # Look up even when the request has cookies (builtin.vcl would pass): the cache key
    # ignores them, and only responses marked cacheable are stored (vcl_backend_response).
    # Those come from static routes, which drop the cookies themselves. Dynamic routes are
    # never stored, so they still receive the visitor's cookies and session.
    return (hash);
}

sub vcl_pass {
    set req.http.X-Cache-Pass = "1";
}

sub vcl_backend_response {
    # Safety net: only store what the app marks as shared-cacheable.
    if (beresp.http.X-Inertia-Static-Cache != "cacheable") {
        set beresp.uncacheable = true;
        set beresp.ttl = 120s;   # hit-for-miss window
        return (deliver);
    }

    # Lurker-friendly bans.
    set beresp.http.x-url = bereq.url;
    set beresp.http.x-host = bereq.http.host;

    # TTL and grace come from Surrogate-Control, removed here so browsers never see it
    # (their Cache-Control is "max-age=0, must-revalidate").
    if (beresp.http.Surrogate-Control ~ "(^|[ ,])max-age=[0-9]+") {
        set beresp.ttl = std.duration(regsub(beresp.http.Surrogate-Control, "^(.*[ ,])?max-age=([0-9]+).*$", "\2") + "s", 0s);
    }
    if (beresp.http.Surrogate-Control ~ "stale-while-revalidate=[0-9]+") {
        set beresp.grace = std.duration(regsub(beresp.http.Surrogate-Control, "^.*stale-while-revalidate=([0-9]+).*$", "\1") + "s", 0s);
    }
    unset beresp.http.Surrogate-Control;

    return (deliver);
}

sub vcl_deliver {
    # HIT / STALE (grace: soft-purged or expired, refreshing in the background) / MISS / BYPASS
    if (req.http.X-Cache-Pass) {
        set resp.http.X-Cache = "BYPASS";
    } elseif (obj.hits > 0 && obj.ttl <= 0s) {
        set resp.http.X-Cache = "STALE";
    } elseif (obj.hits > 0) {
        set resp.http.X-Cache = "HIT";
    } else {
        set resp.http.X-Cache = "MISS";
    }
    unset resp.http.x-url;
    unset resp.http.x-host;
    unset resp.http.xkey;
}
