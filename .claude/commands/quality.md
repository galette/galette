---
description: Run the full PHP code quality suite for Galette in the correct order (php-cs-fixer → phpcbf → phpcs → phpstan). Use before committing.
---

Run the Galette PHP code quality suite from the project root `/var/www/html/private/galette.git`. Execute these four steps in order — do not skip any or change the sequence:

1. **php-cs-fixer** — auto-fix code style:
   `galette/vendor/bin/php-cs-fixer fix`
   Report how many files were fixed.

2. **phpcbf** — auto-fix remaining PHPCS issues:
   `galette/vendor/bin/phpcbf`
   Report what was fixed.

3. **phpcs** — check for remaining violations:
   `galette/vendor/bin/phpcs`
   If phpcs reports errors, stop and show them. Do not proceed to phpstan until phpcs is clean.

4. **phpstan** — static analysis:
   `galette/vendor/bin/phpstan analyse`
   Report errors or confirm "no errors found". Do not auto-fix phpstan errors without asking — they require understanding the business logic.

After all four steps pass cleanly, confirm: "Code quality checks passed. Ready to commit."
