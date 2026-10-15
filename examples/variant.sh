#!/usr/bin/env bash
# Builds a copy of the demo with other versions in examples/.demo-<name>, then
# run it with demo-docker/compose.variant.yml (DEMO=.demo-<name>):
#
#   INERTIA=2 ./variant.sh inertia2             # inertia-laravel 2.x + @inertiajs/vue3 2.x
#   LARAVEL=^12.0 ./variant.sh laravel12        # Laravel 12, Inertia 3
#   INERTIA=2 INERTIA_LARAVEL=2.0.22 INERTIA_VUE=2.3.11 VUE=3.5.26 AXIOS=1.13.2 ./variant.sh app   # exact versions
set -euo pipefail
cd "$(dirname "$0")"

NAME=${1:?usage: ./variant.sh <name>}
LARAVEL=${LARAVEL:-}
INERTIA=${INERTIA:-3}
if [ "$INERTIA" = 2 ]; then
  INERTIA_LARAVEL=${INERTIA_LARAVEL:-^2.0.16}; INERTIA_VUE=${INERTIA_VUE:-^2.3}
else
  INERTIA_LARAVEL=${INERTIA_LARAVEL:-^3.0}; INERTIA_VUE=${INERTIA_VUE:-^3.0}
fi
VUE=${VUE:-^3.5}
AXIOS=${AXIOS:-^1.12}

export DEMO=".demo-$NAME"
rm -rf "$DEMO" && mkdir "$DEMO"
tar -C demo --exclude=./vendor --exclude=./node_modules --exclude=./public/build --exclude=./public/hot \
    --exclude=./bootstrap/ssr --exclude='./storage/logs/*' -cf - . | tar -C "$DEMO" -xf -

./run composer require ${LARAVEL:+"laravel/framework:$LARAVEL"} "inertiajs/inertia-laravel:$INERTIA_LARAVEL" -W --no-interaction --no-progress
./run npm install --no-audit --no-fund
./run npm install --no-audit --no-fund "@inertiajs/vue3@$INERTIA_VUE" "@inertiajs/core@$INERTIA_VUE" "vue@$VUE" \
    $([ "$INERTIA" = 2 ] && echo "axios@$AXIOS")
./run php artisan inertia-static:manifest
./run npm run build
