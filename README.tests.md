# Galette Testing Guide

This document explains how to set up and run tests for Galette.

## Setup

1. **Install dependencies**:
   ```bash
   bin/install_deps
   ```
   This handles both PHP (Composer) and JavaScript (npm) dependencies and builds the initial assets.

2. **Initialize test data (e2e)**:
   ```bash
   php tests/init_test_data.php
   ```
   This prepares the `tests/tests-data/` directory with necessary subfolders and permissions.

   This is only required for e2e tests; PHPunit bootstrap file automatically call it.

3. **Install Galette for testing**:
   You must install Galette into the test configuration folder. Use the `GALETTE_TESTS=1` environment variable to target `tests/config/`.

   **MySQL Example:**
   ```bash
   GALETTE_TESTS=1 DB=mysql bin/console galette:install \
     --dbtype=mysql --dbhost=localhost --dbname=galette_tests \
     --dbuser=galette_tests --dbpass=g@l3tte \
     --admin=admin --password=admin --no-interaction
   ```

   **PostgreSQL Example:**
   ```bash
   GALETTE_TESTS=1 DB=pgsql bin/console galette:install \
     --dbtype=pgsql --dbhost=localhost --dbname=galette_tests \
     --dbuser=galette_tests --dbpass=g@l3tte \
     --admin=admin --password=admin --no-interaction
   ```

Configuration is stored in `tests/config/<db>/config.inc.php`. You can use a `tests/config/<db>/local_config.inc.php` file to override settings.

## Running PHPUnit Tests (Unit & Integration)

PHPUnit tests require the `DB` environment variable.

```bash
# Run with MySQL
DB=mysql galette/vendor/bin/phpunit --test-suffix=.php tests/Galette/

# Run with PostgreSQL
DB=pgsql galette/vendor/bin/phpunit --test-suffix=.php tests/Galette/
```

## Running E2E Tests (Playwright)

See https://playwright.dev/docs/running-tests
E2E tests require a running PHP built-in server configured for the test environment.

1. **Start the PHP server**:
   In a separate terminal (or in background), run:
   ```bash
   DB=pgsql php -S 0.0.0.0:8080 -t galette/webroot tests/router_e2e.php
   ```
   *Note: Use `DB=mysql` if you initialized a MySQL test database.*

2. **Run Playwright tests**:
   ```bash
   # Install browsers (first time only)
   npx playwright install --with-deps

   # Run tests on Chromium only
   npx playwright test --project=chromium
   # Run tests on Firefox only
   npx playwright test --project=firefox
   # Run tests on all browsers
   npm run test:full

   # Run with UI mode (recommended for debugging)
   npx playwright test --ui
   ```

## Cleanup

To remove all generated test data (logs, sessions, uploads):
```bash
npm run test:clean
```

## Troubleshooting

Ensure `tests/tests-data/` is writable.

You can download Playwright test results and screenshots from GitHub Actions for debugging failed tests. 
Extract the `test-results.zip` artifact from the latest CI run in a directory (say `/tmp/playwright-results`) and open the Playwright UI:
```bash
npx playwright show-report /tmp/playwright-results
```