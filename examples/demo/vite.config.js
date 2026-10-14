import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
// From the Composer package: no npm install of @fydellys/inertia-static needed.
import inertiaStatic from './vendor/fydellys/inertia-static/js/vite.js';
import { readFileSync } from 'node:fs';

// The demo runs on Inertia 2 and 3: pick the adapter of the installed version.
const inertiaMajor = JSON.parse(readFileSync('./node_modules/@inertiajs/core/package.json', 'utf8')).version.split('.')[0];

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts'],
            ssr: 'resources/js/ssr.ts',
            refresh: true,
        }),
        vue(),
        inertiaStatic(),
        tailwindcss(),
    ],
    resolve: {
        alias: { '@inertia-version': `/resources/js/inertia/v${inertiaMajor}.ts` },
    },
});
