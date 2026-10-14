import { defineConfig } from 'tsup'

export default defineConfig({
  entry: { index: 'js/src/index.ts', vue: 'js/src/vue/index.ts' },
  outDir: 'js/dist',
  format: ['esm'],
  dts: true,
  clean: true,
  sourcemap: true,
  target: 'es2022',
  external: ['@inertiajs/core', 'vue'],
  // Stable file names: js/dist is committed and shipped in the Composer package.
  esbuildOptions(options) {
    options.chunkNames = 'shared'
  },
})
