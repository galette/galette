import { defineConfig, devices } from '@playwright/test';
import { config } from 'dotenv' ;

// Load .env file so it is available everywhere.
config({path: './tests/e2e/.env.local', quiet: true});
config({path: './tests/e2e/.env', quiet: true});

/**
 * Playwright configuration file
 *
 * See:
 * - https://playwright.dev/docs/test-configuration.
 * - https://playwright.dev/docs/api/class-testconfig
 */
export default defineConfig({
    // Directory that will be recursively scanned for test files.
    // See: https://playwright.dev/docs/api/class-testconfig#test-config-test-dir
    // /!\ Playwright will fail with "no tests files" if a directory, like `galette/data/cache` is not readable /!/
    testDir: '.',

    // Run tests in files in parallel
    // See: https://playwright.dev/docs/api/class-testconfig#test-config-fully-parallel
    fullyParallel: true,

    // Fail the build on CI if you accidentally left test.only in the source code.
    // See: https://playwright.dev/docs/api/class-testconfig#test-config-forbid-only
    forbidOnly: !!process.env.CI,

  // Report: generated HTML in playwright-report/
  reporter: process.env.CI ? [
    ['html', { open: 'never' }],
    ['list'],
    [
        "playwright-ctrf-json-reporter",
        { outputDir: "end2end-report", outputFile: "ci-nighly-test.json" },
    ]
  ] : [
    ['html', { open: 'never' }],
    ['list']
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
