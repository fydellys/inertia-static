import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
// From the Composer package: no npm install of @fydellys/inertia-static needed.
import inertiaStatic from './vendor/fydellys/inertia-static/js/vite.js';

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
});
