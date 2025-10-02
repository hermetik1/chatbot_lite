// Vitest config to exclude Playwright E2E tests
import { defineConfig } from 'vitest/config';

export default defineConfig({
  test: {
    exclude: [
      'tests/e2e/**', // Exclude Playwright E2E tests from Vitest
      'node_modules',
      'dist',
    ],
    globals: true,
  },
});
