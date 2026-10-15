#!/usr/bin/env bash
# End-to-end check of the demo with playwright-cli (headed by default, through WSLg).
#
#   ./e2e.sh                 # Chromium + Firefox, windows visible
#   HEADED= ./e2e.sh         # headless
#   CDN=varnish ./e2e.sh     # through Varnish (docs/varnish.vcl), see compose.varnish.yml
#
# Requires: docker compose up -d (app + cdn + browser). The "cdn" is nginx with
# proxy_cache + ngx_cache_purge; it deliberately strips X-Inertia from
# /__inertia/* responses (the trigger of inertiajs/inertia#1923).
set -uo pipefail
cd "$(dirname "$0")"

CDN=${CDN:-nginx}
case "$CDN" in
  nginx)   BASE=http://localhost:8088; CREDENTIALS_ENV=NGINX_CACHE_HOSTS ;;
  varnish) BASE=http://localhost:8089; CREDENTIALS_ENV=VARNISH_HOSTS ;;
  *) echo "unknown CDN=$CDN (nginx|varnish)"; exit 1 ;;
esac
HEADED=${HEADED---headed}
FAILED=0
PASSED=0
STATE="$(cat e2e-state.js)"
TMP=$(mktemp -d)

# ---------------------------------------------------------------- helpers
pw() { ./pw "$@"; }
artisan() { # fails loudly: a purge error must never be silent
  local out; if ! out=$(docker compose exec -T app php artisan "$@" 2>&1); then ko "php artisan $*" "$out"; fi
}
result() { pw "$@" 2>&1 | sed -n '/^### Result/,/^### Ran/p' | sed '1d;$d'; }
state() { result "$1" eval "$STATE"; }
requests() { result "$1" eval "() => [...document.querySelectorAll('[data-testid=requests] tbody tr')].map(tr => [...tr.children].map(td => td.textContent.trim()))"; }
text() { result "$1" eval "() => document.querySelector('[data-testid=$2]')?.textContent?.trim() ?? null"; }
origin() { curl -s "$BASE/__origin/stats"; }
reset_origin() { curl -s "$BASE/__origin/reset" >/dev/null; }
click() { pw "$1" click "getByTestId('$2')" >/dev/null; sleep "${PAUSE:-0.8}"; }
walk() { for target in nav-about nav-blog next post-post-6 back nav-home nav-blog post-post-1 nav-about; do click "$1" "$target"; done; }
version() { docker compose exec -T app php -r 'echo hash_file("xxh128", "public/build/manifest.json");'; }
json_url() { local p="${1#/}"; echo "/__inertia/$(version)/${p:-index}.json"; }

# fetch URL through the CDN; sets $H (headers) and $B (body)
get() { rm -f "$TMP/body"; H=$(curl -s -D - -o "$TMP/body" "${@:2}" "$BASE$1" | tr -d '\r'); B=$(cat "$TMP/body" 2>/dev/null); }
hdr() { echo "$H" | grep -i "^$1:" | head -1 | cut -d' ' -f2-; }
cdn() { get "$@"; hdr X-Cache-Status; }
rendered() { # fetch; sets $AT = renderedAt of the page (SSR HTML or page JSON). No subshell!
  get "$@"
  AT=$(echo "$B" | sed 's/&quot;/"/g' | grep -o '"renderedAt":"[^"]*"' | head -1 | cut -d'"' -f4)
}

ok() { printf '  \033[32m✔\033[0m %s\n' "$1"; PASSED=$((PASSED + 1)); }
ko() { printf '  \033[31m✘ %s\033[0m\n    %s\n' "$1" "$(echo "$2" | tr -d '\n' | cut -c1-300)"; FAILED=$((FAILED + 1)); }
check() { # description, json, python expression over `d`
  if python3 -c "import json,sys; d=json.loads(sys.argv[1]); sys.exit(0 if ($3) else 1)" "$2" 2>/dev/null; then ok "$1"; else ko "$1" "$2"; fi
}
eq() { [ "$2" = "$3" ] && ok "$1" || ko "$1" "expected [$3], got [$2]"; }
ne() { [ "$2" != "$3" ] && [ -n "$2" ] && ok "$1" || ko "$1" "equal/empty values: [$2] [$3]"; }
has() { [[ "$2" == *"$3"* ]] && ok "$1" || ko "$1" "[$2] does not contain [$3]"; }

queue_idle() { # waits until the purge queue is empty (debounced jobs included)
  for _ in $(seq 1 30); do
    [ "$(docker compose exec -T app php artisan tinker --execute='echo DB::table("jobs")->count();' 2>/dev/null | tr -dc 0-9)" = "0" ] && return 0
    sleep 1
  done
  ko "purge queue did not drain" ""
}
until_cdn() { # url, expected status: polls the CDN (background revalidation is async)
  for _ in $(seq 1 10); do rendered "$1"; [ "$(hdr X-Cache-Status)" = "$2" ] && return 0; sleep 1; done
}

ready() { # waits until Vue hydrated the page (the network panel renders on mount only)
  for _ in $(seq 1 30); do
    [ "$(result "$1" eval "() => !!document.querySelector('[data-testid=network-panel]')")" = "true" ] && return 0
    sleep 0.5
  done
}

clear_cdn() { # empties the CDN cache without the package (test setup only)
  case "$CDN" in
    nginx)   docker compose exec -T cdn sh -c 'rm -rf /var/cache/nginx/inertia/*' ;;
    varnish) docker compose -f compose.yml -f compose.varnish.yml exec -T varnish varnishadm 'ban req.url ~ .' >/dev/null ;;
  esac
}

section() { printf '\n\033[1m▶ %s\033[0m\n' "$1"; }

# ---------------------------------------------------------------- setup
section "0. setup ($CDN): CDN cache, tag registry and origin counter reset"
clear_cdn
artisan inertia-static:purge --all
docker compose exec -T app php artisan tinker --execute='App\Models\Post::find(1)?->update(["title" => "Post number 1"]);' >/dev/null 2>&1
queue_idle # let the debounced purge of that update run
clear_cdn
pw close-all >/dev/null 2>&1
reset_origin
ok "clean environment"

# ---------------------------------------------------------------- 1-2 CDN
section "1. warming the CDN (Chromium)"
pw -s=warm open "$BASE/" $HEADED --browser=chromium >/dev/null; ready -s=warm
walk -s=warm
pw -s=warm close >/dev/null
ok "9 page transitions done"

section "2. new visitor, warm CDN: no page reaches the origin"
reset_origin
pw -s=visitor open "$BASE/" $HEADED --browser=chromium >/dev/null; ready -s=visitor
sleep 1
walk -s=visitor
sleep 1
S=$(state -s=visitor); R=$(requests -s=visitor); O=$(origin)
check "initial HTML served by the CDN (HIT)" "$S" "'CDN HIT' in d['initial'] and 'status 200' in d['initial']"
check "no synchronous origin hit: static origin hits <= background refreshes (STALE)" "{\"o\": $O, \"r\": $R}" "d['o']['static'] <= sum(1 for r in d['r'] if r[4] in ('STALE','UPDATING'))"
check "persistent layout: the /api/me island was fetched ONCE for 9 navigations" "$O" "d['dynamic'] == 1 and d['log'][-1]['uri'] == '/api/me'"
check "every transition came from the CDN cache" "$R" "len(d) == 9 and all(r[4] in ('HIT','STALE') for r in d)"
check "every transition used the versioned JSON URL" "$R" "all(r[2].startswith('/__inertia/') for r in d)"
[ "$CDN" = nginx ] && check "the CDN stripped X-Inertia (#1923 scenario) on all of them" "$R" "all(r[6] == 'NO' for r in d)"
check "no Inertia error / invalid-response modal" "$S" "d['errors'] == 'Inertia errors: none' and d['modal1923'] == 0 and not d['bodyHasPlainJsonError']"

# ---------------------------------------------------------------- 3-5 privacy
section "3. Alice signs in and browses static pages"
pw -s=alice open "$BASE/login" $HEADED --browser=chromium >/dev/null; ready -s=alice
click -s=alice submit; sleep 1
S=$(result -s=alice eval "() => ({ secret: document.querySelector('[data-testid=secret]')?.textContent, flash: document.querySelector('[data-testid=flash]')?.textContent, props: document.querySelector('[data-testid=prop-keys]')?.textContent })")
check "dynamic dashboard shows private data" "$S" "'Alice' in d['secret'] and d['flash'] == 'Signed in!' and 'auth' in d['props']"
for page in nav-home nav-blog post-post-2; do
  click -s=alice "$page"; sleep 0.5
  check "  [$page] signed in: props without auth/flash/csrf" "$(text -s=alice prop-keys)" "'auth' not in d and 'flash' not in d and 'csrf' not in d"
done
check "island shows Alice (loaded in the browser)" "$(text -s=alice island)" "'Alice' in d"

section "4. anonymous visitor right after Alice: none of her data"
pw -s=anon open "$BASE/blog/post-2" $HEADED --browser=chromium >/dev/null; ready -s=anon
for page in back nav-home nav-blog; do click -s=anon "$page"; done
S=$(result -s=anon eval "() => ({ alice: document.documentElement.outerHTML.includes('Alice'), island: document.querySelector('[data-testid=island]').textContent })")
check "anonymous HTML/props/island without 'Alice'" "$S" "not d['alice'] and 'guest' in d['island']"
for u in / /blog /blog/post-2 "$(json_url /blog/post-2)"; do
  get "$u"
  eq "  $u: no Set-Cookie" "$(echo "$H" | grep -ci '^set-cookie')" "0"
  eq "  $u: no 'Alice' in the body" "$(echo "$B" | grep -c Alice)" "0"
done
eq "HTML without the per-request Inertia DevTools id" "$(curl -s "$BASE/" | grep -c devtools)" "0"

section "5. sign out from a static page (same URL)"
click -s=alice nav-home
click -s=alice logout; sleep 1
check "island goes back to guest" "$(text -s=alice island)" "'guest' in d"
click -s=alice nav-dashboard
check "dashboard requires sign in again" "$(state -s=alice)" "d['url'] == '/login'"

# ---------------------------------------------------------------- 5b prefetch / polling
section "5b. prefetch and polling"
reset_origin
pw -s=anon goto "$BASE/" >/dev/null; ready -s=anon
pw -s=anon hover "getByTestId('prefetch-link')" >/dev/null; sleep 1.5
R=$(requests -s=anon)
check "hover prefetch used the rewritten JSON URL" "$R" "len(d) >= 1 and '/blog/post-11.json' in d[0][2]"
N=$(echo "$R" | python3 -c "import json,sys; print(len(json.load(sys.stdin)))")
click -s=anon prefetch-link; sleep 1
check "click after prefetch: no new request (served from the prefetch cache)" "$(requests -s=anon)" "len(d) == $N"
check "  and the page is the prefetched post" "$(state -s=anon)" "d['url'] == '/blog/post-11' and d['component'] == 'Blog/Show'"
click -s=anon nav-home
T0=$(text -s=anon rendered-at)
click -s=anon poll-start; sleep 4; click -s=anon poll-stop
R=$(requests -s=anon)
check "polling uses the never-cached live endpoint (never the HTML URL)" "$R" "all(r[2].startswith('/__inertia/live/') and r[2].endswith('/index.json') for r in d[:2])"
check "  answered by the origin with BYPASS (never cached)" "$R" "all('BYPASS' in r[5] for r in d[:2])"
check "  the CDN did not serve it from cache" "$R" "all(r[4] not in ('HIT','STALE') for r in d[:2])"
ne "  renderedAt was refreshed by the poll" "$(text -s=anon rendered-at)" "$T0"
check "  no Inertia errors" "$(state -s=anon)" "d['errors'] == 'Inertia errors: none'"

section "5c. clicking the link of the current page: cached JSON, never the cached HTML (inertia#1923)"
pw -s=anon goto "$BASE/" >/dev/null; ready -s=anon
click -s=anon nav-home; click -s=anon nav-home
R=$(requests -s=anon)
check "same-page click uses the cacheable JSON URL (not live, not the HTML URL)" "$R" "all(r[2].startswith('/__inertia/') and not r[2].startswith('/__inertia/live/') and r[3] == '200' for r in d[:2])"
check "  served from the CDN cache" "$R" "all(r[4] in ('HIT','STALE') for r in d[:2])"
check "  no Inertia error / modal" "$(state -s=anon)" "d['errors'] == 'Inertia errors: none' and d['modal1923'] == 0"
get /about -H "X-Inertia: true" -H "X-Inertia-Version: x"
eq "  (server side) an Inertia request on the HTML URL is never stored" "$(hdr X-Inertia-Static-Cache)" "bypass"

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

# ---------------------------------------------------------------- inertia features
section "FEATURES. Inertia features on a static (ISR) page: /features"
MAJOR=$(docker compose exec -T app php -r 'echo explode(".", json_decode(file_get_contents("node_modules/@inertiajs/core/package.json"))->version)[0];')
reset_origin
get /features; get /features
eq "HTML of /features cached by the CDN" "$(hdr X-Cache-Status)" "HIT"
eq "  no deferred value in the cached HTML (loaded later, from the live URL)" "$(echo "$B" | grep -c 'deferred at')" "0"
eq "  no WhenVisible value in the cached HTML" "$(echo "$B" | grep -c 'visible at')" "0"
has "  the once prop is in the cached HTML" "$B" "plans at"

FT() { result "$1" eval "() => Object.fromEntries(['features-title','deferred','plans','log-count','posts-count','visible','form-error','form-ok','likes'].map(id => [id, document.querySelector('[data-testid='+id+']')?.textContent?.trim() ?? null]).concat([['url', location.pathname + location.search], ['note', document.querySelector('[data-testid=remember-input]')?.value ?? null], ['errors', document.querySelector('[data-testid=inertia-errors]')?.textContent], ['modal', document.querySelectorAll('dialog, iframe').length]]))"; }
live_req() { # session, path fragment: the last request to it went to the live URL, uncached
  check "$3" "$(requests "$1")" "any(r[2].startswith('/__inertia/live/') and '$2' in r[2] and r[3] == '200' and 'BYPASS' in r[5] and r[4] not in ('HIT','STALE') for r in d)"
}

pw -s=feat open "$BASE/features" $HEADED --browser=chromium >/dev/null; ready -s=feat; sleep 1.5
F=$(FT -s=feat)
check "Deferred: value loaded after the page" "$F" "(d['deferred'] or '').startswith('deferred at')"
live_req -s=feat features.json "  through the live URL, never cached"
check "WhenVisible: not loaded before it is visible" "$F" "d['visible'] == 'waiting…'"
pw -s=feat eval "() => window.scrollTo(0, document.body.scrollHeight)" >/dev/null; sleep 1.5
check "WhenVisible: loaded when scrolled into view" "$(FT -s=feat)" "(d['visible'] or '').startswith('visible at')"
live_req -s=feat features.json "  through the live URL, never cached"
pw -s=feat eval "() => window.scrollTo(0, 0)" >/dev/null

click -s=feat log-add; click -s=feat log-add; sleep 0.5
check "merge props: entries appended by partial reloads" "$(FT -s=feat)" "d['log-count'] == '3'"
click -s=feat log-reset; sleep 0.5
check "  reset: replaced instead of merged" "$(FT -s=feat)" "d['log-count'] == '1'"
live_req -s=feat features.json "  partial reloads through the live URL"

click -s=feat scroll-more; sleep 1
F=$(FT -s=feat)
check "InfiniteScroll: next page appended" "$F" "d['posts-count'] == '8'"
check "  the URL follows the page (?page=2)" "$F" "d['url'] == '/features?page=2'"
check "  loaded through the live URL with ?page=2" "$(requests -s=feat)" "any(r[2].startswith('/__inertia/live/') and 'features.json?page=2' in r[2] and 'BYPASS' in r[5] for r in d)"
click -s=feat scroll-more; sleep 1
check "  third page appended" "$(FT -s=feat)" "d['posts-count'] == '12'"
get /features?page=2; get /features?page=2
eq "  /features?page=2 opened directly is its own cached page (query whitelist)" "$(hdr X-Cache-Status)" "HIT"

P1=$(text -s=feat plans)
click -s=feat features-two; sleep 1
F=$(FT -s=feat)
check "once prop: present after visiting another page that has it" "$F" "d['features-title'] == 'Features two' and (d['plans'] or '').startswith('plans at')"
check "  the visit used the cacheable JSON URL" "$(requests -s=feat)" "d[0][2].startswith('/__inertia/') and not d[0][2].startswith('/__inertia/live/') and d[0][2].endswith('/features/two.json')"
artisan inertia-static:purge --url=/features/two --hard
J=$(json_url /features/two)
get "$J" -H "X-Inertia: true" -H "X-Inertia-Except-Once-Props: plans"
get "$J"
has "  a visit sending X-Inertia-Except-Once-Props never caches a page without the once prop" "$B" "plans at"

pw -s=feat fill "getByTestId('remember-input')" "remember me" >/dev/null
click -s=feat nav-about
pw -s=feat go-back >/dev/null; sleep 1.5
F=$(FT -s=feat)
check "useRemember: value restored on back (encrypted history)" "$F" "d['note'] == 'remember me' and d['features-title'] == 'Features two'"
check "  history entry is encrypted (no props in clear text)" "$(result -s=feat eval "() => JSON.stringify(history.state ?? {}).includes('remember me') || JSON.stringify(history.state ?? {}).includes('plans at')")" "d == False"

pw -s=feat cookie-clear >/dev/null 2>&1
pw -s=feat fill "getByTestId('form-email')" "nope" >/dev/null; click -s=feat form-submit; sleep 1.5
check "<Form>: validation error on a static page" "$(FT -s=feat)" "'valid email' in (d['form-error'] or '') and d['modal'] == 0"
pw -s=feat fill "getByTestId('form-email')" "ana@example.com" >/dev/null; click -s=feat form-submit; sleep 1.5
check "<Form>: valid submit, back on the static page" "$(FT -s=feat)" "d['form-ok'] == 'subscribed' and d['url'] == '/features/two' and d['errors'] == 'Inertia errors: none' and d['modal'] == 0"

if [ "$MAJOR" -ge 3 ]; then
  L0=$(text -s=feat likes)
  click -s=feat like; sleep 1.5
  check "Inertia 3 optimistic update: like counted, page still static" "$(FT -s=feat)" "int(d['likes']) == $L0 + 1 and d['errors'] == 'Inertia errors: none'"
  click -s=feat instant-about; sleep 1
  check "Inertia 3 instant visit: About shown" "$(state -s=feat)" "d['url'] == '/about' and d['component'] == 'About' and d['errors'] == 'Inertia errors: none'"
  check "  then fetched from the cacheable JSON URL" "$(requests -s=feat)" "d[0][2].startswith('/__inertia/') and not d[0][2].startswith('/__inertia/live/') and d[0][2].endswith('/about.json')"
fi
eq "no console errors on the features page" "$(pw -s=feat console error 2>&1 | grep -o 'Errors: [0-9]*' | grep -o '[0-9]*')" "0"
pw -s=feat close >/dev/null 2>&1
get /features
eq "the cached /features HTML has nobody's data" "$(echo "$B" | grep -cE 'remember me|ana@example|Subscribed')" "0"

# ---------------------------------------------------------------- ssr / seo
section "SSR & SEO. server-rendered HTML from the CDN, JavaScript disabled, hydration"
for u in / /about /blog /blog/post-1; do get "$u"; get "$u"
  check "  $u (from the CDN): server-rendered markup" "{\"n\": $(echo "$B" | grep -cE '<div [^>]*id="app"[^>]*>[[:space:]]*<[a-z]')}" "d['n'] >= 1"
  check "  $u: content inside #app, not only the page JSON" "{\"n\": $(echo "$B" | sed -n '/id="app"/,$p' | grep -c '<h1')}" "d['n'] >= 1"
  check "  $u: <title> from <Head>" "{\"n\": $(echo "$B" | grep -cE '<title (data-inertia=\"\"|inertia)>')}" "d['n'] >= 1"
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

# ---------------------------------------------------------------- 6 time based
section "6. time-based revalidation (/about: revalidate 10s, stale-while-revalidate)"
queue_idle # a queued purge from an earlier section must not run during the wait
artisan inertia-static:purge --url=/about
rendered /about; A=$AT
eq "1st visit: MISS (rendered at the origin)" "$(hdr X-Cache-Status)" "MISS"
eq "  origin marked it cacheable (X-Inertia-Static: MISS)" "$(hdr X-Inertia-Static)" "MISS"
rendered /about
eq "2nd visit before expiry: HIT, same content" "$AT|$(hdr X-Cache-Status)" "$A|HIT"
echo "    … waiting 13s for the TTL (10s) to expire"; sleep 13
rendered /about
[ "$(hdr X-Cache-Status)" = "STALE" ] || echo "    debug: $(echo "$H" | grep -iE '^(date|age|x-cache-status|x-inertia-static|x-accel)' | tr '\n' ' ')"
eq "after expiry: STALE served instantly (old content)" "$AT|$(hdr X-Cache-Status)" "$A|STALE"
until_cdn /about HIT
eq "  then: HIT with the version revalidated in the background" "$(hdr X-Cache-Status)" "HIT"
ne "  content was regenerated (renderedAt changed)" "$AT" "$A"
# same flow for the page JSON, visible in the browser panel
pw -s=anon goto "$BASE/" >/dev/null; ready -s=anon
click -s=anon nav-about; JA=$(text -s=anon rendered-at)
echo "    … waiting 13s (JSON in the browser)"; sleep 13
click -s=anon nav-home; click -s=anon nav-about
R=$(requests -s=anon)
check "browser: expired JSON came STALE from the CDN" "$R" "d[0][4] == 'STALE'"
sleep 3; click -s=anon nav-home; click -s=anon nav-about
R=$(requests -s=anon)
check "browser: then HIT with new content" "$R" "d[0][4] == 'HIT'"
ne "  displayed renderedAt changed" "$(text -s=anon rendered-at)" "$JA"

# ---------------------------------------------------------------- 7 on demand
section "7. on-demand revalidation: model saved → HasStaticTags → queue → purge"
get /blog/post-1 >/dev/null; get "$(json_url /blog/post-1)"; get /blog; get "$(json_url /blog)"
eq "post-1 cached (HIT)" "$(cdn /blog/post-1)" "HIT"
TITLE="Revised title $(date +%H%M%S)"
pw -s=alice goto "$BASE/login" >/dev/null; ready -s=alice; click -s=alice submit; sleep 1
pw -s=alice fill "getByTestId('post-title-input')" "$TITLE" >/dev/null
click -s=alice rename; sleep 0.5
has "dashboard confirms and queues the purge" "$(text -s=alice flash)" "purge queued"
get /blog/post-1
eq "during the debounce (5s) the CDN still serves the old page (HIT)" "$(hdr X-Cache-Status)" "HIT"
has "  content still old" "$B" "Post number 1"
echo "    … waiting for the purge job (5s debounce + worker)"; sleep 5; queue_idle
get /blog/post-1
eq "after the purge: HTML MISS" "$(hdr X-Cache-Status)" "MISS"
has "  HTML has the new title" "$B" "$TITLE"
get "$(json_url /blog/post-1)"
eq "page JSON was purged too (MISS)" "$(hdr X-Cache-Status)" "MISS"
has "  JSON has the new title" "$B" "$TITLE"
get "$(json_url /blog)"
eq "listing tagged 'posts' purged too" "$(hdr X-Cache-Status)" "MISS"
has "  listing has the new title" "$B" "$TITLE"
eq "and it is a HIT again right after" "$(cdn /blog/post-1)" "HIT"
click -s=anon nav-blog; click -s=anon post-post-1
eq "anonymous browser sees the new title" "$(text -s=anon post-title)" "\"$TITLE\""

# ---------------------------------------------------------------- 8 artisan purge
section "8. inertia-static:purge (url, prefix, tags, all) and --warm"
warmup() { for u in "$@"; do get "$u"; get "$u"; done; }
warmup / /blog /blog/post-2 /blog/post-3 /blog/post-4 "$(json_url /blog/post-2)" "$(json_url /blog/post-3)"
artisan inertia-static:purge --url=/blog/post-2
eq "--url: HTML purged" "$(cdn /blog/post-2)" "MISS"
eq "--url: JSON purged with it" "$(cdn "$(json_url /blog/post-2)")" "MISS"
eq "--url: other page untouched" "$(cdn /blog/post-3)" "HIT"
warmup /blog/post-2 /blog/post-3 "$(json_url /blog/post-3)"
artisan inertia-static:purge --tags=post:3
eq "--tags=post:3: tagged page purged" "$(cdn /blog/post-3)" "MISS"
eq "--tags=post:3: tagged JSON purged" "$(cdn "$(json_url /blog/post-3)")" "MISS"
eq "--tags=post:3: post-4 untouched" "$(cdn /blog/post-4)" "HIT"
warmup /blog /blog/post-2 /blog/post-3 "$(json_url /blog/post-2)"
artisan inertia-static:purge --prefix=/blog
eq "--prefix=/blog: /blog purged" "$(cdn /blog)" "MISS"
eq "--prefix=/blog: /blog/post-2 purged" "$(cdn /blog/post-2)" "MISS"
eq "--prefix=/blog: prefix JSON purged" "$(cdn "$(json_url /blog/post-2)")" "MISS"
S=$(cdn /); [[ "$S" == HIT || "$S" == STALE ]] && ok "--prefix=/blog: home untouched ($S: still cached, not purged)" || ko "--prefix=/blog: home untouched" "got [$S]"
warmup / /blog
artisan inertia-static:purge --all
eq "--all: home purged" "$(cdn /)" "MISS"
eq "--all: blog purged" "$(cdn /blog)" "MISS"
artisan inertia-static:purge --url=/blog/post-5 --warm
eq "--warm: already a HIT on the first visit after the purge" "$(cdn /blog/post-5)" "HIT"
eq "--warm: JSON warmed too" "$(cdn "$(json_url /blog/post-5)")" "HIT"

section "9. inertia-static:warm (deploy)"
artisan inertia-static:purge --all
artisan inertia-static:warm --only=home --only=blog.index --only=blog.show
for u in / /blog /blog/post-7 "$(json_url /blog/post-12)"; do eq "warm: $u is a HIT on the first visit" "$(cdn "$u")" "HIT"; done

# ---------------------------------------------------------------- 10 edge cases
if [ "$CDN" = varnish ]; then
  section "9b. soft purge (xkey-softpurge): stale served once, refreshed in the background"
  rendered /blog/post-4; rendered /blog/post-4; A=$AT
  eq "post-4 cached (HIT)" "$(hdr X-Cache-Status)" "HIT"
  docker compose exec -T -e INERTIA_STATIC_PURGE_MODE=soft app php artisan inertia-static:purge --tags=post:4 >/dev/null
  rendered /blog/post-4
  eq "right after the soft purge: STALE, old content, no wait" "$AT|$(hdr X-Cache-Status)" "$A|STALE"
  until_cdn /blog/post-4 HIT
  ne "  then HIT with content rendered after the purge" "$AT" "$A"
fi

section "10. edge cases"
get /blog/does-not-exist
eq "404: status" "$(echo "$H" | head -1 | cut -d' ' -f2)" "404"
eq "404: cacheable (short not_found_ttl)" "$(hdr X-Inertia-Static)" "MISS"
eq "404: cached on the 2nd request" "$(cdn /blog/does-not-exist)" "HIT"
get "/blog?page=2&utm_source=news&fbclid=x"
eq "query outside the whitelist: 301" "$(echo "$H" | head -1 | cut -d' ' -f2)" "301"
eq "  to the canonical URL (base_url)" "$(hdr Location)" "$BASE/blog?page=2"
get /blog -H "Authorization: Bearer abc"
eq "Authorization: never cached (origin)" "$(hdr X-Inertia-Static-Reason)" "authorization"
eq "  the CDN bypassed too" "$(hdr X-Cache-Status)" "BYPASS"
get /; ETAG=$(hdr ETag)
get / -H "If-None-Match: $ETAG"
eq "strong ETag + If-None-Match: 304 at the CDN" "$(echo "$H" | head -1 | cut -d' ' -f2)" "304"
eq "  304 without body" "${#B}" "0"
get /leak
eq "leak detector: auth()->user() on a static page fails (local)" "$(echo "$H" | head -1 | cut -d' ' -f2)" "500"
eq "  and is not cached (reason: leak:auth)" "$(hdr X-Inertia-Static-Reason)" "leak:auth"
eq "  the CDN did not store it" "$(cdn /leak)" "MISS"
docker compose exec -T app php artisan inertia:stop-ssr >/dev/null 2>&1; sleep 1
artisan inertia-static:purge --url=/about
get /about
eq "SSR down: degraded HTML is not cached" "$(hdr X-Inertia-Static-Reason)" "ssr-unavailable"
has "  Cache-Control no-store" "$(hdr Cache-Control)" "no-store"
eq "  the CDN did not store it (MISS again)" "$(cdn /about)" "MISS"
docker compose exec -d app sh -c 'php artisan inertia:start-ssr > storage/logs/ssr.log 2>&1'; sleep 3
get /about; get /about
eq "SSR back: cacheable again" "$(hdr X-Cache-Status)" "HIT"

# ---------------------------------------------------------------- 10b cache header / headers only
section "10b. X-Inertia-Static-Cache header and \"headers only\" mode (no CDN credentials)"
get /blog
eq "static page: X-Inertia-Static-Cache: cacheable" "$(hdr X-Inertia-Static-Cache)" "cacheable"
get /blog -H "Authorization: Bearer abc"
eq "bypassed response: X-Inertia-Static-Cache: bypass" "$(hdr X-Inertia-Static-Cache)" "bypass"
get /login
eq "dynamic page: no X-Inertia-Static-Cache header" "$(hdr X-Inertia-Static-Cache)" ""
eq "  so the CDN rule never stores it" "$(cdn /login)" "MISS"
NOCRED="docker compose exec -T -e $CREDENTIALS_ENV= app php artisan"
eq "without credentials: StaticCache::canPurge() is false" "$($NOCRED tinker --execute='echo Fydellys\InertiaStatic\Facades\StaticCache::canPurge() ? "yes" : "no";' 2>/dev/null | tr -dc a-z)" "no"
has "  purge command refuses with a clear message" "$($NOCRED inertia-static:purge --all 2>&1)" "headers only"
BEFORE=$(docker compose exec -T app php artisan tinker --execute='echo DB::table("jobs")->count();' 2>/dev/null | tr -dc 0-9)
$NOCRED tinker --execute='App\Models\Post::find(2)->touch();' >/dev/null 2>&1
AFTER=$(docker compose exec -T app php artisan tinker --execute='echo DB::table("jobs")->count();' 2>/dev/null | tr -dc 0-9)
eq "  model saved: no purge job queued (pages expire by TTL)" "$AFTER" "$BEFORE"
get /blog; get /blog
eq "  pages are still cached by the CDN (headers do not need a token)" "$(hdr X-Cache-Status)" "HIT"

# ---------------------------------------------------------------- 11 deploy
section "11. deploy mid-session (new build + purge --all)"
artisan inertia-static:purge --all
pw -s=anon goto "$BASE/" >/dev/null; ready -s=anon
OLD=$(version)
docker compose exec -T app sh -c 'cp public/build/manifest.json /tmp/manifest.bak && echo " " >> public/build/manifest.json'
NEW=$(version)
ne "new build: assetVersion changed" "$NEW" "$OLD"
artisan inertia-static:purge --all
reset_origin
click -s=anon nav-blog; sleep 1.5
O=$(origin)
check "old window requested /__inertia/<old version>/… and got 409" "$O" "any(l['status'] == 409 and '$OLD' in l['uri'] for l in d['log'])"
check "Inertia did a full reload: page now on the new version" "$(result -s=anon eval "() => JSON.parse(document.querySelector('script[data-page]')?.textContent ?? document.getElementById('app').dataset.page).version")" "d == '$NEW'"
click -s=anon nav-about
check "next navigation already uses the new version" "$(requests -s=anon)" "'$NEW' in d[0][2] and d[0][3] == '200'"
check "no Inertia errors across the version change" "$(state -s=anon)" "d['errors'] == 'Inertia errors: none'"
docker compose exec -T app sh -c 'cp /tmp/manifest.bak public/build/manifest.json'
artisan inertia-static:purge --all

# ---------------------------------------------------------------- 12 firefox
section "12. Firefox: transitions, history and leave/come back (#1923 / #900)"
pw -s=firefox open "$BASE/blog" $HEADED --browser=firefox >/dev/null; ready -s=firefox
walk -s=firefox
for i in 1 2 3 4; do pw -s=firefox go-back >/dev/null; sleep 0.6; done
for i in 1 2; do pw -s=firefox go-forward >/dev/null; sleep 0.6; done
pw -s=firefox goto "$BASE/__origin/stats" >/dev/null; ready -s=firefox
pw -s=firefox go-back >/dev/null; sleep 1.5
S=$(state -s=firefox)
check "Firefox: no Inertia error / modal" "$S" "d['errors'] == 'Inertia errors: none' and d['modal1923'] == 0 and not d['bodyHasPlainJsonError']"
check "Firefox: coming back from another site shows HTML, not raw JSON" "$(result -s=firefox eval "() => document.contentType")" "d == 'text/html'"
eq "Firefox: console without errors" "$(pw -s=firefox console error 2>&1 | grep -o 'Errors: [0-9]*' | grep -o '[0-9]*')" "0"

# ---------------------------------------------------------------- summary
rm -rf "$TMP"
echo
if [ "$FAILED" = 0 ]; then
  printf '\033[32mALL %d CHECKS PASSED\033[0m\n' "$PASSED"
else
  printf '\033[31m%d FAILURE(S), %d ok\033[0m\n' "$FAILED" "$PASSED"
fi
exit $FAILED
