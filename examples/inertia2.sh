#!/usr/bin/env bash
# Builds a copy of the demo on Inertia 2 (inertia-laravel 2.x + @inertiajs/vue3 2.x)
# in examples/.demo-inertia2, then run it with demo-docker/compose.inertia2.yml.
set -euo pipefail
cd "$(dirname "$0")"

rm -rf .demo-inertia2 && mkdir .demo-inertia2
tar -C demo --exclude=./vendor --exclude=./node_modules --exclude=./public/build --exclude=./public/hot \
    --exclude=./bootstrap/ssr --exclude='./storage/logs/*' -cf - . | tar -C .demo-inertia2 -xf -

export DEMO=.demo-inertia2
./run composer require "inertiajs/inertia-laravel:^2.0.16" -W --no-interaction --no-progress
./run npm install --no-audit --no-fund
# Vue 3.5.44 breaks the SSR of Inertia 2 (innerHTML is stringified before the render promise resolves).
./run npm install --no-audit --no-fund "@inertiajs/vue3@^2.3" "axios@^1.12" "vue@3.5.43"
./run php artisan inertia-static:manifest
./run npm run build
