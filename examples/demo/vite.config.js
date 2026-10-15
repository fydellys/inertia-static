import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
// From the Composer package: no npm install of @fydellys/inertia-static needed.
import inertiaStatic from './vendor/fydellys/inertia-static/js/vite.js';
import { readFileSync } from 'node:fs';

// The demo runs on Inertia 2 and 3, with Vue (resources/js) or React (resources/react, DEMO_FRONTEND=react).
const inertiaMajor = JSON.parse(readFileSync('./node_modules/@inertiajs/core/package.json', 'utf8')).version.split('.')[0];

export default defineConfig(async ({ mode }) => {
    const react = loadEnv(mode, process.cwd(), '').DEMO_FRONTEND === 'react';
    const root = react ? 'resources/react' : 'resources/js';

    return {
        plugins: [
            laravel({
                input: ['resources/css/app.css', react ? `${root}/app.tsx` : `${root}/app.ts`],
                ssr: react ? `${root}/ssr.tsx` : `${root}/ssr.ts`,
                refresh: true,
            }),
            react ? (await import('@vitejs/plugin-react')).default() : vue(),
            inertiaStatic(),
            tailwindcss(),
        ],
        resolve: {
            alias: { '@inertia-version': `/${root}/inertia/v${inertiaMajor}.ts` },
        },
    };
});
