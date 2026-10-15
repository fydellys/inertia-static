import { defineConfig } from 'tsup'

export default defineConfig({
  entry: {
    index: 'js/src/index.ts',
    vue: 'js/src/vue/index.ts',
    react: 'js/src/react/index.ts',
    'inertia2/index': 'js/src/inertia2/index.ts',
    'inertia2/react': 'js/src/inertia2/react.ts',
  },
  outDir: 'js/dist',
  format: ['esm'],
  dts: true,
  clean: true,
  sourcemap: true,
  target: 'es2022',
  external: ['@inertiajs/core', 'vue', 'react', 'axios'],
  // js/dist is committed and shipped in the Composer package: chunk names only
  // change when their content does.
  esbuildOptions(options) {
    options.chunkNames = 'shared-[hash]'
  },
})
