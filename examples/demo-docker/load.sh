#!/usr/bin/env bash
# Load test with k6 (load.js) against the stack: through the CDN, straight to the origin, and signed-in +
# anonymous visitors at the same time. Run it on the Octane variant for production-like numbers:
#
#   DEMO=.demo-octane OCTANE_WORKERS=4 SESSION_DRIVER=file \
#     docker compose -f compose.yml -f compose.octane.yml up -d --force-recreate app
#   ./load.sh                 # VUS=50 DURATION=20s by default
set -uo pipefail
cd "$(dirname "$0")"
K6=grafana/k6:1.3.0
VUS=${VUS:-50}; DURATION=${DURATION:-20s}
stats() { curl -s http://localhost:8088/__origin/stats | grep -o '"static":[0-9]*,"dynamic":[0-9]*'; }
k6() { docker run --rm -i --network container:inertia-static-demo-cdn-1 -e PHASE="$1" -e VUS="$VUS" -e DURATION="$DURATION" \
  "$K6" run --quiet --summary-mode=compact - < load.js; }

# Cold start, then warm the two cached URLs the CDN phase reads.
docker compose exec -T app php artisan inertia-static:purge --all >/dev/null
VERSION=$(curl -s http://localhost:8088/blog/post-1 | sed 's/&quot;/"/g' | grep -o '"version":"[^"]*"' | head -1 | cut -d'"' -f4)
curl -s -o /dev/null http://localhost:8088/blog/post-1
curl -s -o /dev/null "http://localhost:8088/__inertia/$VERSION/blog/post-1.json"
curl -s -o /dev/null "http://localhost:8088/__inertia/$VERSION/blog/post-1.json"

echo "▶ origin hits before the CDN phase: $(stats)"
k6 cdn
echo "▶ origin hits after the CDN phase:  $(stats)"
k6 origin
k6 leak
