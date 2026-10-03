---
description: Run Galette PHPUnit tests. Accepts a DB type (mysql/pgsql, default mysql) and an optional test path or class name.
---

Run the Galette PHPUnit test suite from the project root `/var/www/html/private/galette.git`.

Parse the request to determine:
- **DB**: `mysql` (default) or `pgsql` — the `DB` env var is mandatory
- **Path**: full suite (`tests/Galette/`) or a specific file (e.g. `tests/Galette/Core/MembersTest.php`)
- **Coverage**: if requested, add `--coverage-filter galette/lib --coverage-html tests/coverage`

Always use `--test-suffix=.php`. Binary: `galette/vendor/bin/phpunit`.

Examples:
- All tests, MySQL: `DB=mysql galette/vendor/bin/phpunit --test-suffix=.php tests/Galette/`
- All tests, PostgreSQL: `DB=pgsql galette/vendor/bin/phpunit --test-suffix=.php tests/Galette/`
- One file: `DB=mysql galette/vendor/bin/phpunit --test-suffix=.php tests/Galette/Core/MembersTest.php`

After completion: report pass/fail/skip counts and show any failure details. If coverage was generated, report the path: `tests/coverage/index.html`.
