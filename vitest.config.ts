import { defineConfig } from 'vitest/config'

export default defineConfig({
  test: {
    environment: 'happy-dom',
    globals: true,
    include: ['js/tests/**/*.test.ts'],
    environmentOptions: { happyDOM: { url: 'https://example.com/' } },
  },
})
