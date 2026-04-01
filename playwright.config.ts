import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  // Common root so that --ui mode watches all test files (core + plugins).
  // Each project uses testMatch to narrow down the files it picks up.
  testDir: '.',

  // Timeout per test
  timeout: 30_000,

  // Report: generated HTML in playwright-report/
  reporter: [
    ['html', { open: 'never' }],
    ['list'],
        [
        "playwright-ctrf-json-reporter",
        { outputDir: "end2end-report", outputFile: "ci-nighly-test.json" },
    ]
  ],

  use: {
    // Priority: --base-url (CLI) > E2E_BASE_URL (env) > default value
    baseURL: process.env.E2E_BASE_URL ?? 'http://127.0.0.1:8080',

    // Screenshot and trace only on failure
    screenshot: 'only-on-failure',
    trace: 'retain-on-failure',

    // Capture console logs for better debugging on CI
    // video: 'on-first-retry',

    // Browser lang aligned with default Galette lang
    locale: 'fr-FR',
  },

  projects: [
    // ── Core Galette tests ──
    {
      name: 'chromium',
      testMatch: 'tests/e2e/specs/**/*.spec.ts',
      use: { ...devices['Desktop Chrome'] },
    },
    {
      name: 'firefox',
      testMatch: 'tests/e2e/specs/**/*.spec.ts',
      use: { ...devices['Desktop Firefox'] },
    },
    {
      name: 'webkit',
      testMatch: 'tests/e2e/specs/**/*.spec.ts',
      use: { ...devices['Desktop Safari'] },
    },
    // ── Plugin tests (discovered via testMatch glob) ──
    {
      name: 'plugins-chromium',
      // Also match plugin specs in tests/plugins when present.
      testMatch: [
        'galette/plugins/*/tests/e2e/specs/**/*.spec.ts',
        'tests/plugins/*/tests/e2e/specs/**/*.spec.ts',
      ],
      use: { ...devices['Desktop Chrome'] },
    },
    {
      name: 'plugins-firefox',
      // Same fallback pattern list for Firefox plugin tests.
      testMatch: [
        'galette/plugins/*/tests/e2e/specs/**/*.spec.ts',
        'tests/plugins/*/tests/e2e/specs/**/*.spec.ts',
      ],
      use: { ...devices['Desktop Firefox'] },
    },
    {
      name: 'plugins-webkit',
      // Also match plugin specs in tests/plugins when present.
      testMatch: [
        'galette/plugins/*/tests/e2e/specs/**/*.spec.ts',
        'tests/plugins/*/tests/e2e/specs/**/*.spec.ts',
      ],
      use: { ...devices['Desktop Safari'] },
    },
  ],
});
