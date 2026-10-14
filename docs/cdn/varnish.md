# Varnish

```ini
INERTIA_STATIC_CDN=varnish
VARNISH_HOSTS=http://varnish-1:6081,http://varnish-2:6081
```

## Headers

```http
Cache-Control: public, max-age=0, s-maxage=3600, stale-while-revalidate=86400, stale-if-error=604800
xkey: posts post:42
```

## Purge protocol

| Operation | Request (to every host) |
|---|---|
| URL | `PURGE /path` with the public `Host` |
| Tags | `PURGE /` with `xkey-purge: posts post:42` (xkey vmod, soft purge) |
| Prefix | `BAN /` with `X-Ban-Prefix: /blog` (+ `X-Ban-Host`) |
| All | `BAN /` with `X-Ban-Prefix: /` |

## VCL

Requires [varnish-modules](https://github.com/varnish/varnish-modules) (`xkey`; included in the official
`varnish` Docker image).

<<< @/varnish.vcl{txt}

What it does:

| Part | Effect |
|---|---|
| `vcl_backend_response` safety net | only responses with `X-Inertia-Static-Cache: cacheable` are stored; everything else becomes hit-for-miss |
| `vcl_recv` `X-Inertia` pass | an Inertia visit on an HTML URL always goes to the origin (Varnish also honours `Vary: X-Inertia`; this is defense in depth) |
| `xkey.softpurge` | tag purges mark objects stale: the next request gets the old page instantly (`STALE`) while Varnish refetches |
| `grace` from `stale-while-revalidate` | time-based revalidation in the background |
| `X-Cache: HIT / STALE / MISS` | debug header |

## Verified (Varnish 7.7, 2026-10-14)

This VCL was run against the demo origin using the `varnish` driver:

- static page and page JSON: `MISS` → `HIT`; a dynamic route with a careless `Cache-Control: public` is never stored;
  `/login` (session) is never stored; an Inertia request on an HTML URL reaches the origin;
- `purge --tags` (xkey soft purge): `STALE` once, then fresh content;
- `purge --url` (`PURGE`), `--prefix` (`BAN`) and `--all`: the next request is a `MISS` with fresh content.

::: tip Non-default ports
Varnish hashes the full `Host` header (`example.com:8080`). The driver keeps the port of `INERTIA_STATIC_BASE_URL` in
`PURGE` and `X-Ban-Host`. Earlier versions dropped it, so purges missed on non-default ports (fixed after this test).
:::
