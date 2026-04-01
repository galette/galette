---
description: Run Galette E2E tests (Playwright). Handles the full 3-step sequence: init data → start PHP server → run tests.
---

Run the Galette E2E test suite from `/var/www/html/private/galette.git`. Three steps in sequence:

**Step 1 — Initialize test data:**
```
php tests/init_test_data.php
```

**Step 2 — Start PHP built-in server** (background):
```
DB=mysql php -S 0.0.0.0:8080 -t galette/webroot tests/router_e2e.php
```
CRITICAL: always use `tests/router_e2e.php` — not the default router, not `tests/router.php`.
Default to `mysql` unless the user specified `pgsql`.
Start in background, then verify the server is up: `curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/` should return 200 or 302.

**Step 3 — Run Playwright:**
```
npm run test:chromium
```

Report results. If tests fail, show the output and note that `npm run report` shows the HTML report.
Remind the user to kill the PHP server when done.
