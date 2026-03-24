# AI Coding Agent Instructions for Galette

## Quick Reference

**Project:** Galette - Membership management web application for non-profit organizations  
**License:** GPL-3.0  
**Main Branch:** `develop` (development), `master` (stable releases)  
**Language:** PHP 8.1+ with Twig templates, JavaScript/CSS frontend  
**Documentation:** https://doc.galette.eu/

## Essential Commands

```bash
# Install all dependencies (PHP + JavaScript)
bin/install_deps

# Set up database for testing
bin/console galette:install [options]

# Build frontend assets
npm run build

# Run tests (MySQL)
DB=mysql galette/vendor/bin/phpunit --test-suffix=.php tests/Galette/

# Run tests (PostgreSQL)
DB=pgsql galette/vendor/bin/phpunit --test-suffix=.php tests/Galette/

# Check code quality
vendor/bin/php-cs-fixer fix --dry-run
vendor/bin/phpcs
vendor/bin/phpstan analyse
```

## Project Structure

```
galette/
├── .github/workflows/    # CI/CD pipelines
├── bin/
│   ├── install_deps      # Install all dependencies (USE THIS)
│   └── console           # CLI tool (includes galette:install)
├── galette/              # Main application
│   ├── lib/              # Core PHP code
│   ├── templates/        # Twig templates
│   ├── webroot/          # Public web root
│   ├── config/           # Configuration files
│   ├── data/             # Data storage (writable)
│   └── lang/             # Translations (gettext .po/.mo)
├── tests/                # PHPUnit test suite
├── ui/                   # Frontend source files
├── patches/              # Database migrations
├── stubs/                # IDE helper stubs
├── composer.json         # PHP dependencies
├── package.json          # JavaScript dependencies
├── phpunit.xml           # Test configuration
├── phpstan.neon          # Static analysis config
├── .php-cs-fixer.dist.php # Code style config
└── .phpcs.xml            # CodeSniffer config
```

## Versions

Minor PHP, MySQL, Postgres versions are defined in `galette/includes/sys_config/versions.inc.php` (see `GALETTE_PHP_MIN`, `GALETTE_MYSQL_MIN`, `GALETTE_PGSQL_MIN`).

## Technology Stack

- **Backend:** PHP with Slim Framework
- **Frontend:** JavaScript, Gulp build system, Semantic UI
- **Templates:** Twig
- **Databases:** MySQL/MariaDB or PostgreSQL (required)
- **Testing:** PHPUnit with pcov for coverage
- **Quality Tools:** PHPStan, PHP-CS-Fixer, PHPCS, Rector

## Setup & Build Process

### Prerequisites

- **PHP:** (see versions)
- **PHP Extensions:** curl, date, dom, fileinfo, gd, gettext, intl, json, mbstring, pdo (pdo_mysql or pdo_pgsql), session, SimpleXML, ssl, tidy, xml
- **pcov** (for coverage — not Xdebug): `pecl install pcov`
- **Node.js** (LTS), **Composer** 2.x
- **Database:** MySQL/MariaDB or PostgreSQL (see versions)

### First-Time Setup

1. **Install dependencies:**
   ```bash
   bin/install_deps
   ```
   This script handles both `composer install` and `npm install`.
   **Note:** If you encounter permission errors, do NOT use `sudo`. Check directory permissions for `vendor/` and `node_modules/`.

2. **Set up database (required for tests):**
   ```bash
   bin/console galette:install \
     --db-type mysql \
     --db-host localhost \
     --db-name galette_test \
     --db-user galette \
     --db-pass password \
     --admin-user admin \
     --admin-pass admin
   ```
   Check `.github/workflows/ci-linux.yml` for reference parameters.

3. **Build frontend assets:**
   ```bash
   npm run build
   ```

### Development Workflow

**When you start working:**
1. Pull latest changes
2. Run `bin/install_deps` to update dependencies
3. Run `npm run build` if frontend files changed

**Before committing:**
1. Fix code style: `vendor/bin/php-cs-fixer fix`
2. Check standards: `vendor/bin/phpcs`
3. Run static analysis: `vendor/bin/phpstan analyse`
4. Run tests: `DB=mysql galette/vendor/bin/phpunit --test-suffix=.php tests/Galette/`
5. Build assets: `npm run build` (if you modified `ui/` directory)

## Testing

### Running Tests

**CRITICAL:** Tests require a database. The `DB` environment variable is mandatory.

```bash
# Run all tests on MySQL/MariaDB
DB=mysql galette/vendor/bin/phpunit --test-suffix=.php tests/Galette/

# Run all tests on PostgreSQL  
DB=pgsql galette/vendor/bin/phpunit --test-suffix=.php tests/Galette/

# Run specific test file
DB=mysql galette/vendor/bin/phpunit --test-suffix=.php tests/Galette/Core/SpecificTest.php

# Run with HTML coverage report (requires pcov)
DB=mysql galette/vendor/bin/phpunit --test-suffix=.php \
  --coverage-filter galette/lib \
  --coverage-html tests/coverage \
  tests/Galette/

# Run with clover XML for CI tools
DB=mysql galette/vendor/bin/phpunit --test-suffix=.php \
  --coverage-filter galette/lib \
  --coverage-clover tests/clover.xml \
  tests/Galette/
```

### Test Requirements

- Database must be installed via `bin/console galette:install`
- Database user needs full privileges: CREATE, DROP, INSERT, UPDATE, DELETE, SELECT
- Always use `--test-suffix=.php` flag
- Always set `DB` environment variable (mysql or pgsql)
- Coverage requires pcov extension: `pecl install pcov`

## Code Quality Standards

### PHP-CS-Fixer (Code Style)

```bash
# Check what would be fixed
vendor/bin/php-cs-fixer fix --dry-run --diff

# Fix code style automatically
vendor/bin/php-cs-fixer fix

# Fix specific directory
vendor/bin/php-cs-fixer fix galette/lib/
```

**Config:** `.php-cs-fixer.dist.php` (PSR-12 based)  
**Rule:** ALWAYS run before committing PHP changes.

### PHPCS (Code Standards)

There are some changes done with rector/php-cs-fixer that cause PHPCS issues. Run `phpcbf` as last step.

```bash
# Check code standards
vendor/bin/phpcs

# Auto-fix where possible
vendor/bin/phpcbf

# Check specific directory
vendor/bin/phpcs galette/lib/
```

**Config:** `.phpcs.xml`

### PHPStan (Static Analysis)

```bash
# Run analysis
vendor/bin/phpstan analyse

# Run with maximum strictness
vendor/bin/phpstan analyse --level=max

# Generate baseline for existing issues
vendor/bin/phpstan analyse --generate-baseline
```

**Config:** `phpstan.neon`  
**Rule:** Fix errors, don't ignore them (unless adding to baseline for legacy code).

### Rector (Automated Refactoring)

```bash
# Preview changes
vendor/bin/rector process --dry-run

# Apply changes
vendor/bin/rector process

# Process specific directory
vendor/bin/rector process galette/lib/
```

**Config:** `rector.php`  
**Warning:** Review changes before committing. Run tests after applying.

## Composer run

If possible, especially when updating dependencies or adding new ones, composer commands must be run with the correct version. `php --version` will give you the information. You can also check for the presence of the `php82` command.
Currently, `laminas-db` composer library limit composer usage to PHP 8.2.  

composer files are as usual at the root of the project, but vendor directory is located at galette/vendor.

## CI/CD Pipeline

### What CI Checks

The `.github/workflows/ci-linux.yml` workflow runs on every push/PR:

1. Tests on multiple PHP versions (8.2 => 8.5)
2. Tests on both MySQL and PostgreSQL
3. Code style check (PHP-CS-Fixer)
4. Code standards check (PHPCS)
5. Static analysis (PHPStan)
6. Frontend build verification

### Replicate CI Locally

```bash
# Full CI check sequence
bin/install_deps
bin/console galette:install [options]
npm run build
DB=mysql galette/vendor/bin/phpunit --test-suffix=.php tests/Galette/
vendor/bin/php-cs-fixer fix --dry-run
vendor/bin/phpcs
vendor/bin/phpstan analyse
```

**If any of these fail locally, CI will fail.**

## Common Tasks

### Adding a New Feature

1. Branch from `develop`: `git checkout -b feature/feature-name develop`
2. Install dependencies: `bin/install_deps`
3. Set up test database if needed: `bin/console galette:install [options]`
4. Write code in `galette/lib/` or `galette/templates/`
5. Add tests in `tests/`
6. If frontend changes: modify `ui/` and run `npm run build`
7. Run quality checks (PHP-CS-Fixer, PHPCS, PHPStan)
8. Run tests: `DB=mysql galette/vendor/bin/phpunit --test-suffix=.php tests/Galette/`
9. Commit with clear message
10. Push and create PR

### Fixing a Bug

1. Branch from appropriate base (`develop` or `master`)
2. Write failing test that reproduces bug
3. Fix the bug
4. Verify test passes: `DB=mysql galette/vendor/bin/phpunit --test-suffix=.php tests/Galette/[TestFile].php`
5. Run full test suite and quality checks
6. Commit and create PR

### Modifying Frontend

1. Edit files in `ui/` directory (NOT in `galette/webroot/`)
2. Build: `npm run build`
3. Test in browser
4. For development, use watch mode: `npm run dev`
5. Built files appear in `galette/webroot/themes/default/ui/`

### Database Changes

1. Create migration in `patches/` following naming: `YYYY-MM-DD_description.sql`
2. Test on clean database
3. Test on existing database with data
4. Update model classes in `galette/lib/`
5. Add tests for schema changes
6. Test on both MySQL and PostgreSQL if possible

## File Permissions

**Writable directories (production):**
- `galette/config/` - Only during installation
- `galette/data/` - Always writable (attachments, cache, logs, photos, exports, imports, files, tempimages)

**Security:** Only expose `galette/webroot/` to the web server.

## Common Issues & Solutions

### "composer install" fails with memory error
```bash
php -d memory_limit=512M $(which composer) install
```

### "npm run build" fails
```bash
rm -rf node_modules package-lock.json
npm install
npm run build
```

### Tests fail: "Connection refused"
1. Verify database server is running
2. Run: `bin/console galette:install [options]`
3. Check database credentials
4. Ensure `DB` environment variable is set

### Tests fail: "Class not found"
```bash
composer dump-autoload
```

### PHP-CS-Fixer changes many files
This is expected when switching branches. Run and commit:
```bash
vendor/bin/php-cs-fixer fix
```

### Coverage doesn't work
```bash
# Install pcov (not xdebug)
pecl install pcov

# Verify it's enabled
php -m | grep pcov
```

## Development Best Practices

### Code Style
- Follow PSR-12
- Use type declarations (parameters and return types)
- Document public methods with PHPDoc
- Keep methods under 50 lines when possible

### Testing
- Write tests for all new features
- Test names should be descriptive: `testMethodNameDoesExpectedBehaviorWhenCondition()`
- Mock external dependencies in unit tests
- Aim for high coverage on business logic

### Git
- Branch from `develop` for features
- Use descriptive names: `feature/add-export`, `fix/email-validation`
- Write clear commit messages (what and why)
- Keep PRs focused (one feature/fix per PR)
- Squash small "fix" commits before merging

### Database
- Always use prepared statements
- Never concatenate SQL strings
- Test migrations on both empty and populated databases
- Include rollback capability when possible

### Frontend
- Edit source files in `ui/`, not built files
- Always run `npm run build` after changes
- Test in multiple browsers if possible

## Performance Considerations

- Galette is used by organizations with thousands of members
- Optimize database queries (use EXPLAIN on complex queries)
- Avoid N+1 query problems (use eager loading)
- Consider pagination for large datasets
- Cache expensive operations when appropriate

## Translation & i18n

- Translation files: `galette/lang/`
- Format: gettext (.po/.mo files)
- Managed via Weblate: https://hosted.weblate.org/projects/galette/
- After updating translatable strings: `bin/update_strings.sh`

## Additional Resources

- **Bug Tracker:** https://bugs.galette.eu/projects/galette
- **Mailing Lists:**
  - Users: https://lists.mailman3.com/postorius/lists/galette-users.mailman3.com/
  - Developers: https://lists.mailman3.com/postorius/lists/galette-devel.mailman3.com/
- **Official Website:** https://galette.eu
- **Documentation:** https://doc.galette.eu/
- **Documentation Repo:** https://github.com/galette/galettedoc (reStructuredText, built with Sphinx, hosted on ReadTheDocs)

## Instructions for AI Agents

1. **Follow these instructions first.** Only search for additional information if these instructions are incomplete or contradictory.

2. **Use provided scripts.** Always use `bin/install_deps` for dependencies (it's what CI uses).

3. **Database is required for tests.** Set it up with `bin/console galette:install` before testing.

4. **Use correct test command.** Always include `DB=mysql` (or `pgsql`) and `--test-suffix=.php`:
   ```bash
   DB=mysql galette/vendor/bin/phpunit --test-suffix=.php tests/Galette/
   ```

5. **Code quality is mandatory.** Run PHP-CS-Fixer, PHPCS, and PHPStan before every commit.

6. **Build frontend when needed.** If you modify `ui/`, run `npm run build`.

7. **Check CI workflows for examples.** Look at `.github/workflows/ci-linux.yml` for reference commands.

8. **Test on appropriate database.** Use the database type relevant to the task (MySQL or PostgreSQL).

9. **Read error messages carefully.** They usually explain what's wrong.

10. **Consider performance.** Galette handles thousands of members; optimize accordingly.

11. **Create migrations for schema changes.** Never modify database structure without a migration file in `patches/`.

12. **Work on develop branch.** Unless told otherwise, branch from and merge to `develop`.

13. **Prefer LSP over Grep for code navigation.** Warn the user if you do not have access. Use LSP operations (`goToDefinition`, `findReferences`, `hover`, `documentSymbol`, `workspaceSymbol`, `incomingCalls`, `outgoingCalls`) for symbol navigation. Fall back to Grep only for non-symbol searches (string literals, comments, regex patterns) or when LSP is unavailable.
