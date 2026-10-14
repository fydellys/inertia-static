# The documented configuration (docs/varnish.vcl) plus what the demo panel reads.
vcl 4.1;

include "/etc/varnish/inertia-static.vcl";

sub vcl_deliver {
    set resp.http.X-Cache-Status = resp.http.X-Cache;
    set resp.http.Server-Timing = "cdn;desc=" + resp.http.X-Cache;
}
