#!/usr/bin/env bash
# Records the README GIF: a new visitor browsing the demo through Cloudflare, every page from the CDN (HIT).
#
#   cd examples/demo-docker && docker compose up -d browser   # origin exposed on CF_HOST (see e2e-cloudflare.sh)
#   CF_HOST=local.example.com ../../.github/art/record-demo.sh
#
# Needs the "browser" container (playwright-cli) and any image with ffmpeg (FFMPEG_IMAGE).
set -euo pipefail
cd "$(dirname "$0")/../../examples/demo-docker"

: "${CF_HOST:?set CF_HOST}"
B="https://$CF_HOST"
FFMPEG_IMAGE=${FFMPEG_IMAGE:-linuxserver/ffmpeg}
PAGES=(/ /blog /blog/post-1 /blog/post-2)

# Warm every Cloudflare data center this network reaches (HTML and page JSON).
VERSION=$(curl -s "$B/" | sed 's/&quot;/"/g' | grep -o '"version":"[^"]*"' | head -1 | cut -d'"' -f4)
warm() {
  for u in "${PAGES[@]}"; do
    p="${u#/}"
    for x in "$u" "/__inertia/$VERSION/${p:-index}.json"; do for _ in $(seq 1 12); do curl -s -o /dev/null "$B$x"; done; done
  done
}
warm; sleep 3; warm # the 2nd pass after any background refresh (no UPDATING in the recording)
curl -s "$B/__origin/reset" >/dev/null

# Recording only: hide the demo's debug columns and sections; "status" is the status on the wire
# (a 304 revalidation shows as 200 to JavaScript, the demo reads it from Resource Timing).
CSS='div.mx-auto{max-width:1040px} div.mx-auto > section:last-child{display:none} section.grid{display:none}
[data-testid=network-panel]{font-size:15px} [data-testid=requests]{font-size:14px}
[data-testid=requests] td:nth-child(3){white-space:nowrap} [data-testid=requests] td,[data-testid=requests] th{padding:3px 8px}
[data-testid=requests] th:nth-child(4),[data-testid=requests] td:nth-child(4),
[data-testid=requests] th:nth-child(n+6):not(:nth-child(8)):not(:nth-child(9)):not(:nth-child(11)),
[data-testid=requests] td:nth-child(n+6):not(:nth-child(8)):not(:nth-child(9)):not(:nth-child(11)){display:none}
[data-testid=requests] th:nth-child(9){font-size:0} [data-testid=requests] th:nth-child(9)::after{content:"status";font-size:14px}
[data-testid=requests] tr{display:grid;grid-template-columns:40px 70px 1fr 90px 80px 80px 50px}
[data-testid=requests] thead tr,[data-testid=requests] tbody tr{align-items:center}
[data-testid=requests] tr>*{order:3} [data-testid=requests] tr>:nth-child(-n+3){order:1} [data-testid=requests] tr>:nth-child(9){order:2}'

pw() { ./pw "$@"; }
pw close-all >/dev/null 2>&1 || true
pw -s=gif open "$B/" --browser=chromium >/dev/null
pw -s=gif resize 1100 760 >/dev/null; sleep 3
pw -s=gif run-code "async page => { await page.addStyleTag({ content: \`$CSS\` }) }" >/dev/null
pw -s=gif video-start /work/demo.webm --size=1100x760 --cursor >/dev/null; sleep 2
for target in nav-blog post-post-1 nav-blog post-post-1 nav-home nav-blog; do pw -s=gif click "getByTestId('$target')" >/dev/null; sleep 1.6; done
sleep 2.5
pw -s=gif video-stop >/dev/null; pw -s=gif close >/dev/null

docker run --rm -u "$(id -u):$(id -g)" -v "$PWD/artifacts:/w" --entrypoint ffmpeg "$FFMPEG_IMAGE" -loglevel error -y -i /w/demo.webm \
  -vf "setpts=PTS/1.5,fps=10,scale=960:-1:flags=lanczos,split[a][b];[a]palettegen=max_colors=96:stats_mode=diff[p];[b][p]paletteuse=dither=bayer:bayer_scale=5:diff_mode=rectangle" \
  /w/demo.gif
cp artifacts/demo.gif ../../.github/art/demo.gif
echo "written .github/art/demo.gif"
