/// <reference types="vitest/config" />
import { defineConfig, loadEnv, type Plugin } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'
import fs from 'node:fs'
import path from 'node:path'

const DEFAULT_PROD_API = 'https://saliha-health-api.duckdns.org/api'

/**
 * En-têtes de sécurité servis par `serve` en production (nixpacks.toml :
 * `npx serve -s dist`), qui lit dist/serve.json. Généré au build pour que
 * connect-src suive VITE_API_BASE_URL. La CSP autorise exactement ce que
 * l'application charge : ses propres scripts, Google Fonts (index.html),
 * les styles en ligne posés par les composants, images data:/blob:
 * (exports, aperçus) et l'API.
 */
function securityHeaders(apiBaseUrl: string): Plugin {
  const apiOrigin = new URL(apiBaseUrl).origin
  const csp = [
    "default-src 'self'",
    "script-src 'self'",
    "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
    "font-src 'self' data: https://fonts.gstatic.com",
    "img-src 'self' data: blob:",
    `connect-src 'self' ${apiOrigin}`,
    "object-src 'none'",
    "base-uri 'self'",
    "form-action 'self'",
    "frame-ancestors 'none'",
  ].join('; ')

  return {
    name: 'saliha-security-headers',
    apply: 'build',
    closeBundle() {
      const config = {
        headers: [
          {
            source: '**',
            headers: [
              { key: 'Content-Security-Policy', value: csp },
              { key: 'Strict-Transport-Security', value: 'max-age=31536000; includeSubDomains' },
              { key: 'X-Frame-Options', value: 'DENY' },
              { key: 'X-Content-Type-Options', value: 'nosniff' },
              { key: 'Referrer-Policy', value: 'strict-origin-when-cross-origin' },
              { key: 'Permissions-Policy', value: 'camera=(), microphone=(), geolocation=()' },
            ],
          },
        ],
      }

      fs.writeFileSync(path.resolve(import.meta.dirname, 'dist/serve.json'), JSON.stringify(config, null, 2))
    },
  }
}

// https://vite.dev/config/
export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, import.meta.dirname, 'VITE_')

  return {
    plugins: [react(), tailwindcss(), securityHeaders(env.VITE_API_BASE_URL || DEFAULT_PROD_API)],
    resolve: {
      alias: {
        '@': path.resolve(import.meta.dirname, './src'),
      },
    },
    server: {
      port: 5173,
    },
    test: {
      environment: 'jsdom',
      setupFiles: ['./src/test/setup.ts'],
      globals: true,
    },
  }
})
