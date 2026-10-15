#!/usr/bin/env bash
# Builds a copy of the demo on Inertia 2 (inertia-laravel 2.x + @inertiajs/vue3 2.x)
# in examples/.demo-inertia2, then run it with demo-docker/compose.inertia2.yml.
#
#   INERTIA_LARAVEL=2.0.22 INERTIA_VUE=2.3.11 VUE=3.5.26 AXIOS=1.13.2 ./inertia2.sh   # exact versions of an app
set -euo pipefail
INERTIA_LARAVEL=${INERTIA_LARAVEL:-^2.0.16}
INERTIA_VUE=${INERTIA_VUE:-^2.3}
# Vue 3.5.44 breaks the SSR of Inertia 2 (innerHTML is stringified before the render promise resolves).
VUE=${VUE:-3.5.43}
AXIOS=${AXIOS:-^1.12}
cd "$(dirname "$0")"

rm -rf .demo-inertia2 && mkdir .demo-inertia2
tar -C demo --exclude=./vendor --exclude=./node_modules --exclude=./public/build --exclude=./public/hot \
    --exclude=./bootstrap/ssr --exclude='./storage/logs/*' -cf - . | tar -C .demo-inertia2 -xf -

export DEMO=.demo-inertia2
./run composer require "inertiajs/inertia-laravel:$INERTIA_LARAVEL" -W --no-interaction --no-progress
./run npm install --no-audit --no-fund
./run npm install --no-audit --no-fund "@inertiajs/vue3@$INERTIA_VUE" "@inertiajs/core@$INERTIA_VUE" "axios@$AXIOS" "vue@$VUE"
./run php artisan inertia-static:manifest
./run npm run build
