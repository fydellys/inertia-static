import { defineConfig } from 'tsup'

export default defineConfig({
  entry: { index: 'js/src/index.ts', vue: 'js/src/vue/index.ts', inertia2: 'js/src/inertia2.ts' },
  outDir: 'js/dist',
  format: ['esm'],
  dts: true,
  clean: true,
  sourcemap: true,
  target: 'es2022',
  external: ['@inertiajs/core', 'vue'],
  // js/dist is committed and shipped in the Composer package: chunk names only
  // change when their content does.
  esbuildOptions(options) {
    options.chunkNames = 'shared-[hash]'
  },
})
