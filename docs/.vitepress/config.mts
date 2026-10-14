import { defineConfig } from 'vitepress'

export default defineConfig({
  title: 'Inertia Static',
  description: 'SSG + ISR for Laravel + Inertia.js, CDN-agnostic.',
  base: '/inertia-static/',
  lang: 'en-US',
  cleanUrls: true,
  // Needs git history (available in the GitHub Pages workflow).
  lastUpdated: process.env.GITHUB_ACTIONS === 'true',
  srcExclude: ['README.md'],
  head: [['meta', { name: 'theme-color', content: '#7c3aed' }]],
  themeConfig: {
    nav: [
      { text: 'Guide', link: '/guide/introduction' },
      { text: 'CDNs', link: '/cdn/overview' },
      { text: 'API', link: '/api/' },
      { text: 'Reference', link: '/reference/configuration' },
      { text: 'Changelog', link: 'https://github.com/fydellys/inertia-static/blob/main/CHANGELOG.md' },
    ],
    sidebar: [
      {
        text: 'Getting started',
        items: [
          { text: 'Introduction', link: '/guide/introduction' },
          { text: 'Installation', link: '/guide/installation' },
          { text: 'Quick start', link: '/guide/quick-start' },
        ],
      },
      {
        text: 'Guide',
        items: [
          { text: 'Static & ISR routes', link: '/guide/routes' },
          { text: 'Static paths', link: '/guide/static-paths' },
          { text: 'Responses & tags', link: '/guide/responses' },
          { text: 'Client (Vue)', link: '/guide/client-vue' },
          { text: 'Islands, forms & CSRF', link: '/guide/islands' },
          { text: 'Revalidation', link: '/guide/revalidation' },
          { text: 'Headers-only mode', link: '/guide/headers-only' },
          { text: 'Preview mode', link: '/guide/preview' },
          { text: 'Artisan commands', link: '/guide/commands' },
          { text: 'Deploying', link: '/guide/deploy' },
        ],
      },
      {
        text: 'API Reference',
        collapsed: false,
        items: [
          { text: 'Overview', link: '/api/' },
          { text: '->isr() / ->static()', link: '/api/route-isr' },
          { text: '#[StaticPage]', link: '/api/static-page-attribute' },
          { text: 'StaticPathsProvider', link: '/api/static-paths-provider' },
          { text: '->revalidate()', link: '/api/response-revalidate' },
          { text: '->tags()', link: '/api/response-tags' },
          { text: 'purgeTags()', link: '/api/purge-tags' },
          { text: 'purgeUrls()', link: '/api/purge-urls' },
          { text: 'purgePrefix()', link: '/api/purge-prefix' },
          { text: 'purgeAll()', link: '/api/purge-all' },
          { text: 'purgeNow()', link: '/api/purge-now' },
          { text: 'canPurge()', link: '/api/can-purge' },
          { text: 'enablePreview()', link: '/api/preview' },
          { text: 'HasStaticTags', link: '/api/has-static-tags' },
          { text: 'inertiaStatic()', link: '/api/inertia-static' },
          { text: 'useStaticIsland()', link: '/api/use-static-island' },
          { text: 'ensureCsrfCookie()', link: '/api/ensure-csrf-cookie' },
          { text: 'Vite plugin', link: '/api/vite-plugin' },
        ],
      },
      {
        text: 'CDNs',
        items: [
          { text: 'Overview & custom drivers', link: '/cdn/overview' },
          { text: 'Cloudflare', link: '/cdn/cloudflare' },
          { text: 'Fastly', link: '/cdn/fastly' },
          { text: 'Bunny', link: '/cdn/bunny' },
          { text: 'Varnish', link: '/cdn/varnish' },
          { text: 'nginx', link: '/cdn/nginx' },
        ],
      },
      {
        text: 'Concepts',
        items: [
          { text: 'How it works', link: '/concepts/how-it-works' },
          { text: 'Security', link: '/concepts/security' },
          { text: 'Cache key normalization', link: '/concepts/cache-key' },
          { text: 'SSR & SEO', link: '/concepts/ssr-seo' },
          { text: 'Compared to Next.js', link: '/concepts/nextjs' },
        ],
      },
      {
        text: 'Reference',
        items: [
          { text: 'Configuration', link: '/reference/configuration' },
          { text: 'Headers', link: '/reference/headers' },
          { text: 'PHP API', link: '/reference/php-api' },
          { text: 'JavaScript API', link: '/reference/js-api' },
        ],
      },
      {
        text: 'More',
        items: [
          { text: 'Testing & E2E', link: '/testing/e2e' },
          { text: 'Troubleshooting / FAQ', link: '/faq' },
        ],
      },
    ],
    socialLinks: [{ icon: 'github', link: 'https://github.com/fydellys/inertia-static' }],
    editLink: {
      pattern: 'https://github.com/fydellys/inertia-static/edit/main/docs/:path',
      text: 'Edit this page on GitHub',
    },
    search: { provider: 'local' },
    footer: { message: 'Released under the MIT License.', copyright: 'Copyright © 2026 fydellys' },
  },
})
