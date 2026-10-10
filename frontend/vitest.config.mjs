import { fileURLToPath } from 'node:url'
import { mergeConfig, defineConfig, configDefaults } from 'vitest/config'
import viteConfig from './vite.config.mjs'

export default mergeConfig(
  viteConfig,
  defineConfig({
    test: {
      environment: 'jsdom',
      include: ['src/**/__tests__/**/*.spec.ts'],
      exclude: [...configDefaults.exclude, 'e2e/**', 'cypress/**'],
      setupFiles: ['src/__tests__/setup.ts'],
      restoreMocks: true,
      root: fileURLToPath(new URL('./', import.meta.url))
    }
  })
)
