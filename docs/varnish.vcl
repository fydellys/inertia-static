# Varnish 7 + varnish-modules (xkey) configuration matching the "varnish" driver.
vcl 4.1;

import std;
import xkey;

backend default { .host = "app"; .port = "8000"; }

acl purge { "localhost"; "127.0.0.1"; "10.0.0.0"/8; "172.16.0.0"/12; }

sub vcl_recv {
    if (req.method == "PURGE" || req.method == "BAN") {
        if (!client.ip ~ purge) { return (synth(405, "Not allowed")); }

        # Tag purge: "xkey-purge: posts post:1"
        if (req.http.xkey-purge) {
            set req.http.n-gone = xkey.softpurge(req.http.xkey-purge);
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

    # Inertia visits on HTML URLs must never be answered from the cache
    # (Varnish honours "Vary: X-Inertia", this is defense in depth).
    if (req.http.X-Inertia && req.url !~ "^/__inertia/") {
        return (pass);
    }

    # Static pages ignore cookies anyway; cookies would make builtin.vcl pass.
    if (req.url !~ "^/(login|logout|dashboard|api|admin)") { unset req.http.Cookie; }
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

    # Map stale-while-revalidate to grace when the app sends it.
    if (beresp.http.Cache-Control ~ "stale-while-revalidate=") {
        set beresp.grace = std.duration(regsub(beresp.http.Cache-Control, ".*stale-while-revalidate=([0-9]+).*", "\1") + "s", 0s);
    }
}

sub vcl_deliver {
    # HIT / STALE (grace: soft-purged or expired, refreshing in the background) / MISS
    if (obj.hits > 0 && obj.ttl <= 0s) {
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
