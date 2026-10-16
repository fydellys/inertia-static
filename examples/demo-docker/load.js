// Load test of the demo with k6 (see load.sh). PHASE selects what runs:
//   cdn    - cached HTML and page JSON through the CDN (nginx), no origin render expected
//   origin - straight to the app (no CDN): a static page rendered on every request, and the signed-in dashboard
//   leak   - signed-in and anonymous visitors at the same time on the same workers: no static page may contain
//            the signed-in user, the dashboard always does
import http from 'k6/http'
import { check } from 'k6'

const CDN = __ENV.CDN_URL || 'http://localhost:8088'
const ORIGIN = __ENV.ORIGIN_URL || 'http://app:8000'
const PHASE = __ENV.PHASE || 'cdn'
const VUS = Number(__ENV.VUS || 50)
const DURATION = __ENV.DURATION || '20s'
const SECRET = 'Alice Secret' // the seeded user's name: must never appear on a static page

const phases = {
  cdn: {
    cdn_html: { executor: 'constant-vus', vus: VUS, duration: DURATION, exec: 'cdnHtml' },
    cdn_json: { executor: 'constant-vus', vus: VUS, duration: DURATION, exec: 'cdnJson', startTime: DURATION },
  },
  origin: {
    origin_static: { executor: 'constant-vus', vus: VUS, duration: DURATION, exec: 'originStatic' },
    origin_dynamic: { executor: 'constant-vus', vus: VUS, duration: DURATION, exec: 'originDynamic', startTime: DURATION },
  },
  leak: {
    leak_signed_in: { executor: 'constant-vus', vus: Math.ceil(VUS / 2), duration: DURATION, exec: 'leakSignedIn' },
    leak_anonymous: { executor: 'constant-vus', vus: Math.ceil(VUS / 2), duration: DURATION, exec: 'leakAnonymous' },
  },
}

const scenarios = phases[PHASE]
const thresholds = { checks: ['rate==1'], http_req_failed: ['rate<0.01'] }
for (const name of Object.keys(scenarios)) {
  // Per-scenario figures in the summary.
  thresholds[`http_req_duration{scenario:${name}}`] = ['p(95)<10000']
  thresholds[`http_reqs{scenario:${name}}`] = ['count>0']
}

// noCookiesReset: k6 empties the cookie jar after every iteration by default, which would sign the VU out.
export const options = { scenarios, thresholds, noCookiesReset: true, summaryTrendStats: ['avg', 'med', 'p(95)', 'p(99)', 'max'] }

export function setup() {
  const page = http.get(`${CDN}/blog/post-1`).body
  const version = (page.match(/&quot;version&quot;:&quot;([^&]+)&quot;/) || page.match(/"version":"([^"]+)"/) || [])[1]

  return { version }
}

// Signs in once per VU (k6 keeps one cookie jar per VU).
let signedIn = false
function signIn(base) {
  if (signedIn) {
    return
  }
  http.get(`${base}/login`)
  const token = decodeURIComponent((http.cookieJar().cookiesForURL(`${base}/login`)['XSRF-TOKEN'] || [''])[0])
  const res = http.post(`${base}/login`, { email: 'alice@example.com', password: 'password' }, {
    headers: { 'X-XSRF-TOKEN': token, Origin: base, Referer: `${base}/login` },
    redirects: 0,
  })
  check(res, { 'signed in': (r) => r.status === 302 && (r.headers.Location || '').endsWith('/dashboard') })
  signedIn = true
}

export function cdnHtml() {
  const res = http.get(`${CDN}/blog/post-1`)
  check(res, { 'cdn html: 200 HIT': (r) => r.status === 200 && r.headers['X-Cache-Status'] === 'HIT' })
}

export function cdnJson(data) {
  const res = http.get(`${CDN}/__inertia/${data.version}/blog/post-1.json`)
  check(res, { 'cdn json: 200 HIT': (r) => r.status === 200 && r.headers['X-Cache-Status'] === 'HIT' })
}

export function originStatic() {
  const res = http.get(`${ORIGIN}/blog/post-1`)
  check(res, { 'origin static: 200 cacheable': (r) => r.status === 200 && r.headers['X-Inertia-Static-Cache'] === 'cacheable' })
}

export function originDynamic() {
  signIn(ORIGIN)
  const res = http.get(`${ORIGIN}/dashboard`)
  check(res, { 'origin dynamic: 200 with the user': (r) => r.status === 200 && r.body.includes(SECRET) })
}

export function leakSignedIn() {
  signIn(ORIGIN)
  const page = http.get(`${ORIGIN}/blog/post-2`)
  check(page, { 'signed in, static page: no user data': (r) => r.status === 200 && !r.body.includes(SECRET) && !r.headers['Set-Cookie'] })
  const dashboard = http.get(`${ORIGIN}/dashboard`)
  check(dashboard, { 'signed in, dashboard: has the user': (r) => r.status === 200 && r.body.includes(SECRET) })
}

export function leakAnonymous(data) {
  const page = http.get(`${ORIGIN}/blog/post-2`)
  check(page, { 'anonymous, static page: no user data': (r) => r.status === 200 && !r.body.includes(SECRET) })
  const json = http.get(`${ORIGIN}/__inertia/${data.version}/blog/post-2.json`)
  check(json, { 'anonymous, page JSON: no user data': (r) => r.status === 200 && !r.body.includes(SECRET) })
  const me = http.get(`${ORIGIN}/api/me`)
  check(me, { 'anonymous, island: guest': (r) => r.status === 200 && !r.body.includes(SECRET) })
}
