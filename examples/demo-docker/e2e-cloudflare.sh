#!/usr/bin/env bash
# End-to-end check against a REAL Cloudflare zone (the demo origin exposed through a Cloudflare Tunnel).
#
#   export CF_ENV_FILE=/path/outside/repo/cloudflare.env   # CLOUDFLARE_API_TOKEN / CLOUDFLARE_ZONE_ID
#   export CF_HOST=local.example.com                       # tunnel hostname -> localhost:80
#   docker compose stop app                                # one queue worker only: the Cloudflare origin's
#   docker compose -f compose.yml -f compose.cloudflare.yml up -d origin
#   ./e2e-cloudflare.sh
#
# Safe for production zones: every purge is scoped to CF_HOST (URLs, tags of this app,
# prefixes, "purge all" = hosts purge). purge_everything is never used.
set -uo pipefail
cd "$(dirname "$0")"

: "${CF_ENV_FILE:?set CF_ENV_FILE}" "${CF_HOST:?set CF_HOST}"
export CF_ENV_FILE CF_HOST

BASE="https://$CF_HOST"
HEADED=${HEADED---headed}
FAILED=0
PASSED=0
STATE="$(cat e2e-state.js)"
TMP=$(mktemp -d)
DC="docker compose -f compose.yml -f compose.cloudflare.yml"

# ---------------------------------------------------------------- helpers
pw() { ./pw "$@"; }
artisan() { local out; if ! out=$($DC exec -T origin php artisan "$@" 2>&1); then ko "php artisan $*" "$out"; fi; }
tinker() { $DC exec -T origin php artisan tinker --execute="$1" 2>/dev/null; }
result() { pw "$@" 2>&1 | sed -n '/^### Result/,/^### Ran/p' | sed '1d;$d'; }
state() { result "$1" eval "$STATE"; }
requests() { result "$1" eval "() => [...document.querySelectorAll('[data-testid=requests] tbody tr')].map(tr => [...tr.children].map(td => td.textContent.trim()))"; }
text() { result "$1" eval "() => document.querySelector('[data-testid=$2]')?.textContent?.trim() ?? null"; }
origin() { curl -s "$BASE/__origin/stats"; }
reset_origin() { curl -s "$BASE/__origin/reset" >/dev/null; }
click() { pw "$1" click "getByTestId('$2')" >/dev/null; sleep "${PAUSE:-1.2}"; }
walk() { for target in nav-about nav-blog next post-post-6 back nav-home nav-blog post-post-1 nav-about; do click "$1" "$target"; done; }
version() { curl -s "$BASE/__inertia/manifest.json" >/dev/null; $DC exec -T origin php -r 'echo hash_file("xxh128", "public/build/manifest.json");'; }
json_url() { local p="${1#/}"; echo "/__inertia/$VERSION/${p:-index}.json"; }

get() { rm -f "$TMP/body"; H=$(curl -s -D - -o "$TMP/body" "${@:2}" "$BASE$1" | tr -d '\r'); B=$(cat "$TMP/body" 2>/dev/null); }
hdr() { echo "$H" | grep -i "^$1:" | head -1 | cut -d' ' -f2-; }
cf() { get "$@"; hdr cf-cache-status; }
rendered() { get "$@"; AT=$(echo "$B" | grep -o '"renderedAt":"[^"]*"' | head -1 | cut -d'"' -f4); }
until_cf() { # url, expected statuses (regex): Cloudflare purges propagate asynchronously
  for _ in $(seq 1 20); do rendered "$1"; [[ "$(hdr cf-cache-status)" =~ ^($2)$ ]] && return 0; sleep 1; done
}
queue_idle() {
  for _ in $(seq 1 40); do [ "$(tinker 'echo DB::table("jobs")->count();' | tr -dc 0-9)" = "0" ] && return 0; sleep 1; done
  ko "purge queue did not drain" ""
}

ok() { printf '  \033[32m✔\033[0m %s\n' "$1"; PASSED=$((PASSED + 1)); }
ko() { printf '  \033[31m✘ %s\033[0m\n    %s\n' "$1" "$(echo "$2" | tr -d '\n' | cut -c1-300)"; FAILED=$((FAILED + 1)); }
check() { if python3 -c "import json,sys; d=json.loads(sys.argv[1]); sys.exit(0 if ($3) else 1)" "$2" 2>/dev/null; then ok "$1"; else ko "$1" "$2"; fi; }
eq() { [ "$2" = "$3" ] && ok "$1" || ko "$1" "expected [$3], got [$2]"; }
ne() { [ "$2" != "$3" ] && [ -n "$2" ] && ok "$1" || ko "$1" "equal/empty values: [$2] [$3]"; }
has() { [[ "$2" == *"$3"* ]] && ok "$1" || ko "$1" "[$2] does not contain [$3]"; }
match() { [[ "$2" =~ $3 ]] && ok "$1" || ko "$1" "[$2] does not match /$3/"; }
ready() { # waits until Vue hydrated the page (the network panel renders on mount only)
  for _ in $(seq 1 30); do
    [ "$(result "$1" eval "() => !!document.querySelector('[data-testid=network-panel]')")" = "true" ] && return 0
    sleep 0.5
  done
}

section() { printf '\n\033[1m▶ %s\033[0m\n' "$1"; }

# ---------------------------------------------------------------- 0
section "0. setup ($CF_HOST through Cloudflare)"
tinker 'App\Models\Post::find(1)?->update(["title" => "Post number 1"]);' >/dev/null
queue_idle
artisan inertia-static:purge --all   # hosts purge: only $CF_HOST
VERSION=$(version)
sleep 2
reset_origin
pw close-all >/dev/null 2>&1
eq "origin answers through the tunnel" "$(curl -s -o /dev/null -w '%{http_code}' "$BASE/")" "200"
has "  served by Cloudflare" "$(curl -s -D - -o /dev/null "$BASE/" | tr -d '\r' | grep -i '^server:')" "cloudflare"
eq "  headers-only mode is off (credentials present)" "$(tinker 'echo Fydellys\InertiaStatic\Facades\StaticCache::canPurge() ? "yes" : "no";' | tr -dc a-z)" "yes"

# ---------------------------------------------------------------- 1
section "1. Cloudflare caches the HTML and the page JSON"
artisan inertia-static:purge --url=/blog/post-8
until_cf /blog/post-8 "MISS|EXPIRED"
match "HTML 1st request: MISS" "$(hdr cf-cache-status)" "^(MISS|EXPIRED)$"
eq "HTML 2nd request: HIT" "$(cf /blog/post-8)" "HIT"
eq "  no Set-Cookie on the cached HTML" "$(echo "$H" | grep -ci '^set-cookie')" "0"
eq "  X-Inertia-Static-Cache: cacheable" "$(hdr x-inertia-static-cache)" "cacheable"
cf "$(json_url /blog/post-8)" >/dev/null
eq "page JSON 2nd request: HIT" "$(cf "$(json_url /blog/post-8)")" "HIT"
eq "  JSON content type" "$(hdr content-type)" "application/json"
for u in /login /api/me; do
  get "$u"
  match "dynamic $u is never cached by Cloudflare" "$(hdr cf-cache-status)" "^(BYPASS|DYNAMIC)$"
done
get /blog -H "Authorization: Bearer abc"
match "Authorization request bypasses the cache" "$(hdr cf-cache-status)" "^(BYPASS|DYNAMIC)$"
has "  origin marked it BYPASS" "$(hdr x-inertia-static)" "BYPASS; reason=authorization"

# ---------------------------------------------------------------- 2
section "2. warm Cloudflare, then a new browser: no page reaches the origin"
pw -s=warm open "$BASE/" $HEADED --browser=chromium >/dev/null; ready -s=warm
walk -s=warm; walk -s=warm
pw -s=warm close >/dev/null
reset_origin
pw -s=visitor open "$BASE/" $HEADED --browser=chromium >/dev/null; ready -s=visitor
walk -s=visitor; sleep 1
S=$(state -s=visitor); R=$(requests -s=visitor); O=$(origin)
check "SPA transitions used the versioned JSON URL" "$R" "len(d) == 9 and all(r[2].startswith('/__inertia/') for r in d)"
check "SPA transitions came from Cloudflare (HIT/STALE/UPDATING/REVALIDATED)" "$R" "all(r[4] in ('HIT','STALE','UPDATING','REVALIDATED') for r in d)"
check "no synchronous origin hit: static origin hits <= background refreshes (UPDATING/STALE)" "{\"o\": $O, \"r\": $R}" "d['o']['static'] <= sum(1 for r in d['r'] if r[4] in ('UPDATING','STALE'))"
check "persistent layout: the /api/me island was fetched ONCE for 9 navigations" "$O" "[l['uri'] for l in d['log'] if l['type'] == 'dynamic'] == ['/api/me']"
check "no Inertia errors" "$S" "d['errors'] == 'Inertia errors: none' and d['modal1923'] == 0 and not d['bodyHasPlainJsonError']"

# ---------------------------------------------------------------- 3
section "3. privacy through Cloudflare"
pw -s=alice open "$BASE/login" $HEADED --browser=chromium >/dev/null; ready -s=alice
click -s=alice submit; sleep 1.5
check "signed in: dynamic dashboard shows private data" "$(result -s=alice eval "() => document.querySelector('[data-testid=secret]')?.textContent")" "'Alice' in d"
for page in nav-home nav-blog post-post-2; do
  click -s=alice "$page"
  check "  [$page] signed in: props without auth/flash/csrf" "$(text -s=alice prop-keys)" "'auth' not in d and 'flash' not in d and 'csrf' not in d"
done
check "island shows Alice (client side)" "$(text -s=alice island)" "'Alice' in d"
pw -s=anon open "$BASE/blog/post-2" $HEADED --browser=chromium >/dev/null; ready -s=anon
for page in back nav-home nav-blog; do click -s=anon "$page"; done
check "anonymous right after: no 'Alice' anywhere" "$(result -s=anon eval "() => ({ alice: document.documentElement.outerHTML.includes('Alice'), island: document.querySelector('[data-testid=island]').textContent })")" "not d['alice'] and 'guest' in d['island']"
for u in / /blog /blog/post-2 "$(json_url /blog/post-2)"; do
  get "$u"
  eq "  $u from Cloudflare: no Set-Cookie, no 'Alice'" "$(echo "$H" | grep -ci '^set-cookie')/$(echo "$B" | grep -c Alice)" "0/0"
done

# ---------------------------------------------------------------- forms
section "FORMS. forms on static and dynamic pages, return back(), no CSRF token"
reset_origin
pw -s=forms open "$BASE/about" $HEADED --browser=chromium >/dev/null; ready -s=forms
FSTATE='() => ({ url: location.pathname, aError: document.querySelector("[data-testid=a-error]").textContent, aFlash: document.querySelector("[data-testid=a-flash]").textContent, bError: document.querySelector("[data-testid=b-error]").textContent, bResult: document.querySelector("[data-testid=b-result]").textContent, errors: document.querySelector("[data-testid=inertia-errors]").textContent, modal: document.querySelectorAll("dialog, iframe").length })'
submit() { [ "${4:-}" = keep ] || pw -s=$1 cookie-clear >/dev/null 2>&1; pw -s=$1 fill "getByTestId('$2-email')" "$3" >/dev/null; click -s=$1 "$2-submit"; sleep 1.5; }
submit forms a nope
check "static page, useForm + back(), invalid, no cookies: validation error shown" "$(result -s=forms eval "$FSTATE")" "d['url'] == '/about' and 'valid email' in d['aError'] and d['modal'] == 0"
pw -s=forms goto "$BASE/about" >/dev/null; ready -s=forms
submit forms a ana@example.com
check "static page, useForm + back(), valid: Inertia flash shown, no modal (inertia#1923)" "$(result -s=forms eval "$FSTATE")" "d['url'] == '/about' and 'Subscribed ana@' in d['aFlash'] and d['errors'] == 'Inertia errors: none' and d['modal'] == 0"
check "  back() was sent to the live JSON URL, never to the cached HTML" "$(origin)" "any('/__inertia/live/' in l['uri'] and 'about.json' in l['uri'] for l in d['log'])"
submit forms b nope
check "static page, useHttp, invalid: 422 error shown" "$(result -s=forms eval "$FSTATE")" "'valid email' in d['bError']"
submit forms b ana@example.com
check "static page, useHttp, valid: JSON result shown" "$(result -s=forms eval "$FSTATE")" "'Subscribed ana@' in d['bResult'] and d['modal'] == 0"
get /about
eq "  the cached static HTML has nobody's flash" "$(echo "$B" | grep -c Subscribed)" "0"
pw -s=forms goto "$BASE/login" >/dev/null; ready -s=forms; click -s=forms submit; sleep 1.5
submit forms a nope keep
check "dynamic page, useForm + back(), invalid: validation error shown" "$(result -s=forms eval "$FSTATE")" "d['url'] == '/dashboard' and 'valid email' in d['aError']"
submit forms a bob@example.com keep
check "dynamic page, useForm + back(), valid: flash shown" "$(result -s=forms eval "$FSTATE")" "d['url'] == '/dashboard' and 'Subscribed bob@' in d['aFlash'] and d['modal'] == 0"
submit forms b nope keep
check "dynamic page, useHttp, invalid: 422 error shown" "$(result -s=forms eval "$FSTATE")" "'valid email' in d['bError']"
pw -s=forms close >/dev/null

# ---------------------------------------------------------------- ssr / seo
section "SSR & SEO. server-rendered HTML from the CDN, JavaScript disabled, hydration"
for u in / /about /blog /blog/post-1; do get "$u"; get "$u"
  has "  $u (from the CDN): server-rendered markup" "$B" 'data-server-rendered="true"'
  check "  $u: content inside #app, not only the page JSON" "{\"n\": $(echo "$B" | sed -n '/id="app"/,$p' | grep -c '<h1')}" "d['n'] >= 1"
  has "  $u: <title> from <Head>" "$B" '<title data-inertia="">'
done
get /blog/post-1
has "post: meta description" "$B" '<meta name="description" content="Public content of post 1."'
has "post: og:title" "$B" 'property="og:title"'
has "post: absolute canonical from APP_URL" "$B" "<link rel=\"canonical\" href=\"$BASE/blog/post-1\""
pw -s=seo open "about:blank" --browser=chromium >/dev/null 2>&1
JSOFF=$(pw -s=seo run-code "async page => { const ctx = await page.context().browser().newContext({ javaScriptEnabled: false }); const p = await ctx.newPage(); await p.goto('$BASE/blog/post-1'); const r = { title: await p.title(), h1: (await p.textContent('h1'))?.trim(), description: await p.getAttribute('meta[name=description]', 'content'), canonical: await p.getAttribute('link[rel=canonical]', 'href') }; await ctx.close(); return r }" 2>&1 | sed -n '/^### Result/,/^### Ran/p' | sed '1d;$d')
check "JavaScript disabled: title, h1, description and canonical are there (SEO)" "$JSOFF" "d['title'] == 'Post number 1' and d['h1'] == 'Post number 1' and d['description'] == 'Public content of post 1.' and d['canonical'] == '$BASE/blog/post-1'"
pw -s=hydra open "$BASE/blog/post-1" $HEADED --browser=chromium >/dev/null; ready -s=hydra
for t in nav-home nav-about nav-blog; do click -s=hydra "$t"; done
pw -s=hydra goto "$BASE/about" >/dev/null; ready -s=hydra
eq "hydration: no mismatch warning in the console (post, about)" "$(pw -s=hydra console 2>&1 | grep -ci 'hydrat')" "0"
eq "  no console errors" "$(pw -s=hydra console error 2>&1 | grep -o 'Errors: [0-9]*' | grep -o '[0-9]*')" "0"
pw -s=hydra close >/dev/null 2>&1

# ---------------------------------------------------------------- 4
section "4. time-based revalidation on Cloudflare (/about: revalidate 10s)"
artisan inertia-static:purge --url=/about
until_cf /about "MISS|EXPIRED"; A=$AT
match "1st visit: MISS" "$(hdr cf-cache-status)" "^(MISS|EXPIRED)$"
rendered /about
eq "2nd visit: HIT, same content" "$AT|$(hdr cf-cache-status)" "$A|HIT"
echo "    … waiting 15s for the TTL (10s) to expire"; sleep 15
rendered /about; S1=$(hdr cf-cache-status); A1=$AT
echo "    Cloudflare answered [$S1] right after expiry"
if [[ "$S1" =~ ^(STALE|UPDATING)$ ]]; then
  eq "after expiry: old content served instantly (stale-while-revalidate)" "$A1" "$A"
else
  ne "after expiry: content regenerated" "$A1" "$A"
fi
until_cf /about "HIT"
ne "then: HIT with the regenerated content" "$AT" "$A"

# ---------------------------------------------------------------- 5
section "5. on-demand revalidation: model saved -> queue -> Cloudflare tag purge"
get /blog/post-1; get /blog/post-1; get "$(json_url /blog/post-1)"; get "$(json_url /blog/post-1)"; get "$(json_url /blog)"; get "$(json_url /blog)"
eq "post-1 cached (HIT)" "$(cf /blog/post-1)" "HIT"
TITLE="Revised title $(date +%H%M%S)"
pw -s=alice goto "$BASE/dashboard" >/dev/null; ready -s=alice
pw -s=alice fill "getByTestId('post-title-input')" "$TITLE" >/dev/null
click -s=alice rename
has "dashboard confirms and queues the purge" "$(text -s=alice flash)" "purge queued"
echo "    … waiting for the debounced purge job (5s) and Cloudflare propagation"; sleep 5; queue_idle
until_cf /blog/post-1 "MISS|EXPIRED"
has "HTML has the new title" "$B" "$TITLE"
until_cf "$(json_url /blog/post-1)" "MISS|EXPIRED|HIT"
has "page JSON has the new title" "$B" "$TITLE"
until_cf "$(json_url /blog)" "MISS|EXPIRED|HIT"
has "listing tagged 'posts' has the new title" "$B" "$TITLE"
click -s=anon nav-blog; click -s=anon post-post-1
eq "anonymous browser (SPA) sees the new title" "$(text -s=anon post-title)" "\"$TITLE\""

# ---------------------------------------------------------------- 6
section "6. inertia-static:purge against Cloudflare"
warm() { for u in "$@"; do get "$u"; get "$u"; done; }
warm /blog/post-3 /blog/post-4 "$(json_url /blog/post-3)"
artisan inertia-static:purge --url=/blog/post-3
until_cf /blog/post-3 "MISS|EXPIRED"
match "--url: HTML purged" "$(hdr cf-cache-status)" "^(MISS|EXPIRED)$"
until_cf "$(json_url /blog/post-3)" "MISS|EXPIRED"
match "--url: JSON purged with it" "$(hdr cf-cache-status)" "^(MISS|EXPIRED)$"
eq "--url: other page untouched" "$(cf /blog/post-4)" "HIT"
warm /blog/post-3 /blog/post-5
artisan inertia-static:purge --tags=post:5
until_cf /blog/post-5 "MISS|EXPIRED"
match "--tags=post:5: tagged page purged (Cache-Tag)" "$(hdr cf-cache-status)" "^(MISS|EXPIRED)$"
eq "--tags=post:5: post-3 untouched" "$(cf /blog/post-3)" "HIT"
warm /blog /blog/post-3 /
artisan inertia-static:purge --prefix=/blog
until_cf /blog/post-3 "MISS|EXPIRED"
match "--prefix=/blog: /blog/post-3 purged" "$(hdr cf-cache-status)" "^(MISS|EXPIRED)$"
eq "--prefix=/blog: home untouched" "$(cf /)" "HIT"
warm / /blog
artisan inertia-static:purge --all
until_cf / "MISS|EXPIRED"
match "--all (hosts purge of $CF_HOST only): home purged" "$(hdr cf-cache-status)" "^(MISS|EXPIRED)$"
artisan inertia-static:purge --url=/blog/post-9 --warm
eq "--warm: HIT on the first visit after the purge" "$(cf /blog/post-9)" "HIT"

# ---------------------------------------------------------------- 7
section "7. Firefox through Cloudflare"
pw -s=firefox open "$BASE/blog" $HEADED --browser=firefox >/dev/null; ready -s=firefox
walk -s=firefox
for i in 1 2 3; do pw -s=firefox go-back >/dev/null; sleep 0.8; done
pw -s=firefox goto "$BASE/__origin/stats" >/dev/null; ready -s=firefox; pw -s=firefox go-back >/dev/null; sleep 2
check "Firefox: no Inertia error / modal" "$(state -s=firefox)" "d['errors'] == 'Inertia errors: none' and d['modal1923'] == 0 and not d['bodyHasPlainJsonError']"
check "Firefox: back from another site shows HTML" "$(result -s=firefox eval "() => document.contentType")" "d == 'text/html'"

# ---------------------------------------------------------------- 7b
section "7b. same-page clicks, reloads and polling through Cloudflare (inertia#1923)"
pw -s=visitor goto "$BASE/about" >/dev/null; ready -s=visitor
for i in 1 2 3; do click -s=visitor nav-about; done
R=$(requests -s=visitor)
check "clicking the current page 3x: cacheable JSON (not live, not the HTML URL), 200" "$R" "all(r[2].startswith('/__inertia/') and not r[2].startswith('/__inertia/live/') and r[3] == '200' for r in d[:3])"
check "  served by Cloudflare (no synchronous origin hit)" "$R" "all(r[4] in ('HIT','STALE','UPDATING','REVALIDATED') for r in d[:3])"
check "  no Inertia error / modal" "$(state -s=visitor)" "d['errors'] == 'Inertia errors: none' and d['modal1923'] == 0"
click -s=visitor nav-home
T0=$(text -s=visitor rendered-at)
click -s=visitor poll-start; sleep 5; click -s=visitor poll-stop
R=$(requests -s=visitor)
check "polling through Cloudflare: live endpoint, BYPASS, 200" "$R" "all(r[2].startswith('/__inertia/live/') and r[3] == '200' and r[4] in ('BYPASS','DYNAMIC') for r in d[:2])"
ne "  renderedAt refreshed by the poll" "$(text -s=visitor rendered-at)" "$T0"
check "  no Inertia error / modal" "$(state -s=visitor)" "d['errors'] == 'Inertia errors: none' and d['modal1923'] == 0"

# ---------------------------------------------------------------- 8
section "8. safety net: Cache Response Rule (no-store unless X-Inertia-Static-Cache: cacheable)"
PROBE="/careless?probe=$(date +%s%N)"
get "$PROBE"; get "$PROBE"
match "careless dynamic route with 'Cache-Control: public' is NOT stored" "$(hdr cf-cache-status)" "^(BYPASS|DYNAMIC|MISS|EXPIRED)$"
[ "$(hdr cf-cache-status)" = "HIT" ] && echo "    hint: create the Cloudflare Cache Response Rule (docs/cdn/cloudflare.md)"
match "  while static pages are still served from the cache" "$(cf /blog/post-9)" "^(HIT|UPDATING|STALE)$"

# ---------------------------------------------------------------- summary
rm -rf "$TMP"
echo
if [ "$FAILED" = 0 ]; then printf '\033[32mALL %d CLOUDFLARE CHECKS PASSED\033[0m\n' "$PASSED"; else printf '\033[31m%d FAILURE(S), %d ok\033[0m\n' "$FAILED" "$PASSED"; fi
exit $FAILED
