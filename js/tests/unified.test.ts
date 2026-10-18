// @vitest-environment node
// One setup for Inertia 2 and 3: withInertiaStatic() + the Vite plugin picking the adapter.
import axios from 'axios'
import { mkdirSync, mkdtempSync, writeFileSync } from 'node:fs'
import { tmpdir } from 'node:os'
import { join } from 'node:path'
import { describe, expect, it } from 'vitest'
import { StaticHttpClient } from '../src/client'
import { withInertiaStatic as inertia2Vue } from '../src/inertia2'
import { withInertiaStatic as inertia2React } from '../src/inertia2/react'
import { withInertiaStatic as vue, ssrRender } from '../src/vue'
import { withInertiaStatic as react } from '../src/react'
import { aliases, dedupe, installedInertia } from '../vite.js'
import imported from './fixtures/manifest.json'
import { manifest } from './helpers'

function project(version?: string): string {
  const root = mkdtempSync(join(tmpdir(), 'inertia-static-'))

  if (version) {
    mkdirSync(join(root, 'node_modules', '@inertiajs', 'core'), { recursive: true })
    writeFileSync(join(root, 'node_modules', '@inertiajs', 'core', 'package.json'), JSON.stringify({ version }))
  }

  return root
}

describe('withInertiaStatic()', () => {
  it('Inertia 3 (Vue and React): returns the http option', () => {
    expect(vue({ manifest }).http).toBeInstanceOf(StaticHttpClient)
    expect(react({ manifest }).http).toBeInstanceOf(StaticHttpClient)
  })

  it('takes the manifest imported from the JSON file the Vite plugin writes', () => {
    expect(vue({ manifest: imported }).http).toBeInstanceOf(StaticHttpClient)
  })

  it('Inertia 2 (Vue and React): installs the adapter on the app axios, once, and adds no option', () => {
    const network = async () => ({ status: 200, data: '', headers: {} })
    axios.defaults.adapter = network as never

    expect(inertia2Vue({ manifest })).toEqual({})
    const installed = axios.defaults.adapter

    expect(installed).not.toBe(network)
    expect(inertia2React({ manifest })).toEqual({})
    expect(axios.defaults.adapter).toBe(installed)
  })

  it('ssrRender is a no-op on Inertia 3, so ssr.ts is the same on both versions', () => {
    const render = async () => '<div></div>'

    expect(ssrRender(render)).toBe(render)
  })
})

describe('Vite plugin', () => {
  it('detects the installed Inertia major, from the project or a parent node_modules', () => {
    expect(installedInertia(project('2.3.29'))).toBe(2)
    expect(installedInertia(project('3.9.1'))).toBe(3)

    const parent = project('2.3.11')
    const child = join(parent, 'resources')
    mkdirSync(child)
    expect(installedInertia(child)).toBe(2)
  })

  it('on Inertia 2, points /vue and /react to the Inertia 2 adapters', () => {
    const resolve = (inertia: number | null, id: string) => {
      const alias = (aliases(inertia) as { find: RegExp; replacement: string }[]).find((entry) => entry.find.test(id))!

      return id.replace(alias.find, alias.replacement)
    }

    expect(resolve(2, '@fydellys/inertia-static/vue')).toMatch(/dist[\\/]inertia2[\\/]index\.js$/)
    expect(resolve(2, '@fydellys/inertia-static/react')).toMatch(/dist[\\/]inertia2[\\/]react\.js$/)
    expect(resolve(3, '@fydellys/inertia-static/vue')).toMatch(/dist[\\/]vue$/)
    expect(resolve(null, '@fydellys/inertia-static/react')).toMatch(/dist[\\/]react$/)
    expect(resolve(2, '@fydellys/inertia-static/inertia2/react')).toMatch(/dist[\\/]inertia2[\\/]react$/)
  })

  it('dedupes axios only on Inertia 2, where the app may not even have it installed otherwise', () => {
    expect(dedupe(2)).toContain('axios')
    expect(dedupe(3)).not.toContain('axios')
    expect(dedupe(null)).not.toContain('axios')
    expect(dedupe(3)).toEqual(expect.arrayContaining(['@inertiajs/core', 'vue']))
  })
})
