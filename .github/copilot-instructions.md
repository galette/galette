# Galette Copilot Agent Instructions

## Project Overview
Galette is a membership management web application for non-profit organizations, released under GPLv3. It provides features for managing members, contributions, transactions, mailings, and more. The project is primarily written in PHP with Twig templates and includes JavaScript/CSS frontend assets.

**Repository:** https://github.com/galette/galette/  
**Documentation:** https://doc.galette.eu/  
**Current Stable Version:** 1.2.1  
**Main Branch:** `develop` (development), `master` (stable releases)

## Technology Stack
- **Backend:** PHP 8.1+ (Slim Framework)
- **Frontend:** JavaScript, Gulp for asset compilation
- **Templating:** Twig
- **Database:** MySQL/MariaDB or PostgreSQL (required for tests)
- **Dependency Management:** 
  - PHP: Composer
  - JavaScript: npm
- **Testing:** PHPUnit
- **Code Quality:** PHPStan, PHP-CS-Fixer, PHPCS, Rector

## Repository Structure

### Root Directory Files
- `composer.json` - PHP dependencies
- `package.json` - Node.js dependencies  
- `gulpfile.js` - Build tasks for frontend assets
- `.php-cs-fixer.dist.php` - PHP-CS-Fixer configuration
- `.phpcs.xml` - PHP CodeSniffer configuration
- `phpstan.neon` - PHPStan static analysis configuration
- `rector.php` - Rector refactoring configuration
- `semantic.json` - Semantic UI configuration
- `composer-dependency-analyser.php` - Dependency analysis tool
- `CONTRIBUTING.rst` - Contribution guidelines
- `LICENSE.md` - GPL-3.0 license
- `README.md` - Project readme
- `SECURITY.md` - Security policy

### Key Directories
- **`galette/`** - Main application code
  - `galette/lib/` - Core PHP libraries
  - `galette/templates/` - Twig templates
  - `galette/webroot/` - Public web root (entry point)
  - `galette/config/` - Configuration files
  - `galette/data/` - Data storage (writable)
  - `galette/lang/` - Translations
- **`tests/`** - PHPUnit test suite
- **`ui/`** - Frontend source files (semantic UI customization)
- **`bin/`** - Executable scripts
  - `bin/install_deps` - Install all dependencies (Composer + npm) - **USE THIS**
  - `bin/console` - Galette console tool (includes `galette:install` command for database setup)
- **`patches/`** - Database patches/migrations
- **`stubs/`** - IDE helper stubs
- **`.github/`** - GitHub workflows and CI/CD configuration

## Build & Development Setup

### Prerequisites
- **PHP:** 8.1 or higher
- **PHP Extensions Required:** 
  - curl, date, dom, fileinfo, gd, gettext, intl, json, mbstring, pdo (pdo_mysql or pdo_pgsql), session, SimpleXML, ssl, tidy, xml
  - **pcov** (for code coverage - optional, not required for basic testing)
- **Node.js:** Latest LTS recommended (for npm/gulp)
- **Composer:** 2.x
- **Database:** MySQL 5.7+/MariaDB 10.3+ or PostgreSQL 11+

### Initial Setup Commands

**IMPORTANT: The project provides a convenient script for dependency installation:**

```bash
# Install all dependencies (Composer + npm) - RECOMMENDED
bin/install_deps

# This script is used in CI and handles both:
# - composer install --no-interaction
# - npm install
```

**Alternative - Manual Installation:**
```bash
# 1. Install PHP dependencies
composer install --no-interaction

# 2. Install JavaScript dependencies
npm install
```

**After dependencies are installed, build frontend assets:**
```bash
# Build production assets
npm run build

# OR for development with watch mode:
npm run dev
```

**Note:** If you encounter permission errors, DO NOT use `sudo`. Instead, check directory permissions for `vendor/` and `node_modules/` directories.

## Build Commands

### Composer (PHP Dependencies)
```bash
# Install dependencies (production)
composer install --no-dev --optimize-autoloader

# Install dependencies (development)
composer install

# Update dependencies
composer update

# Update a specific package
composer update vendor/package-name
```

### NPM/Gulp (Frontend Assets)
```bash
# Install Node dependencies
npm install

# Build production assets (minified)
npm run build

# Build development assets with watch mode
npm run dev

# Alternative: using gulp directly
npx gulp build      # Production build
npx gulp watch      # Development with file watching
```

**Build Artifacts:** Built assets are output to `galette/webroot/themes/default/ui/` and `galette/webroot/themes/default/plugins/`.

## Testing

### Running PHPUnit Tests

**Prerequisites:** A database is **required** to run tests. Galette supports both MySQL/MariaDB and PostgreSQL.

**Database Setup for Tests:**

```bash
# Install/configure database using command line installer
bin/console galette:install [options]

# Required parameters (check bin/console galette:install or .github/workflows/*.yml for exact usage):
# - Database type (mysql or pgsql)
# - Database host
# - Database name
# - Database user
# - Database password
# - Admin username and password
# See GitHub Actions workflows for example parameters
```

**Running Tests:**

```bash
# Run all tests on MySQL/MariaDB
DB=mysql galette/vendor/bin/phpunit --test-suffix=.php tests/Galette/

# Run all tests on PostgreSQL
DB=pgsql galette/vendor/bin/phpunit --test-suffix=.php tests/Galette/

# Run specific test file
DB=mysql galette/vendor/bin/phpunit --test-suffix=.php tests/Galette/Core/SpecificTest.php

# Run with coverage (requires pcov extension) - for local development
DB=mysql galette/vendor/bin/phpunit --test-suffix=.php --coverage-filter galette/lib --coverage-html tests/coverage tests/Galette/

# Run with coverage for CI (clover format for Scrutinizer/Codecov)
DB=mysql galette/vendor/bin/phpunit --test-suffix=.php --coverage-filter galette/lib --coverage-clover tests/clover.xml tests/Galette/
```

**Test Configuration:** `phpunit.xml` in root directory

**Important Notes:**
- The `DB` environment variable is **required** - it specifies which database type to test against
- Always use `--test-suffix=.php` flag when running tests
- Tests use the configured database - ensure it's accessible and properly initialized
- Database must be installed via `bin/console galette:install` before running tests
- **Code Coverage:** Galette uses **pcov** (not Xdebug) for coverage. Install with: `pecl install pcov`
  - Coverage is filtered to `galette/lib` directory only
  - Use `--coverage-html tests/coverage` for local HTML reports
  - Use `--coverage-clover tests/clover.xml` for CI integration with Scrutinizer/Codecov

**Known Issues:**
- Tests require a properly configured and initialized database - they will fail without it
- If tests fail with "Class not found" errors, run `composer dump-autoload`
- Some tests may timeout if database is slow - increase timeout in phpunit.xml if needed
- Make sure the database user has sufficient privileges (CREATE, DROP, INSERT, UPDATE, DELETE, SELECT)
- For code coverage, **pcov** is required (not Xdebug). Install with `pecl install pcov` if needed
- Coverage output directory (`tests/coverage` or location of `tests/clover.xml`) should be writable

## Code Quality & Linting

### PHP-CS-Fixer (Code Style)
```bash
# Check code style (dry-run)
vendor/bin/php-cs-fixer fix --dry-run --diff

# Fix code style issues
vendor/bin/php-cs-fixer fix

# Fix specific directory
vendor/bin/php-cs-fixer fix galette/lib/
```

**Configuration:** `.php-cs-fixer.dist.php`  
**Important:** ALWAYS run PHP-CS-Fixer before committing PHP code changes.

### PHPCS (PHP CodeSniffer)
```bash
# Check code standards
vendor/bin/phpcs

# Check specific directory
vendor/bin/phpcs galette/lib/

# Auto-fix where possible
vendor/bin/phpcbf
```

**Configuration:** `.phpcs.xml`

### PHPStan (Static Analysis)
```bash
# Run static analysis
vendor/bin/phpstan analyse

# Run with specific level (0-9, or max)
vendor/bin/phpstan analyse --level=max

# Generate baseline for existing issues
vendor/bin/phpstan analyse --generate-baseline
```

**Configuration:** `phpstan.neon`  
**Current Level:** Check `phpstan.neon` for configured level  
**Note:** PHPStan errors should be fixed, not ignored, unless adding to baseline for legacy code

### Rector (Automated Refactoring)
```bash
# Dry-run (preview changes)
vendor/bin/rector process --dry-run

# Apply refactoring
vendor/bin/rector process

# Process specific directory
vendor/bin/rector process galette/lib/
```

**Configuration:** `rector.php`  
**Warning:** Always review Rector changes before committing. Run tests after applying Rector rules.

## GitHub Actions CI/CD

### Workflow Files Location
`.github/workflows/` directory contains CI configurations:
- `ci-linux.yml` - Main CI pipeline for Linux
- Other workflow files for specific checks

### CI Pipeline Overview
The CI runs on every push and pull request. It performs:
1. **PHP Compatibility Check** (multiple PHP versions: 8.1, 8.2, 8.3)
2. **Install Dependencies** - Via `bin/install_deps`
3. **Database Setup** - Using `bin/console galette:install` with test database
4. **NPM Build** - Build frontend assets
5. **PHPUnit Tests** - Run test suite with `DB=mysql galette/vendor/bin/phpunit --test-suffix=.php tests/Galette/`
6. **Code Quality Checks:**
   - PHPStan static analysis
   - PHP-CS-Fixer style check
   - PHPCS code standard check

### Running CI Checks Locally (Recommended Before Push)
```bash
# 1. Install dependencies
bin/install_deps
# OR manually: composer install && npm install

# 2. Set up test database
bin/console galette:install [options]
# See .github/workflows/*.yml for example parameters

# 3. Build assets
npm run build

# 4. Run tests (choose your database)
DB=mysql galette/vendor/bin/phpunit --test-suffix=.php tests/Galette/
# OR for PostgreSQL:
# DB=pgsql galette/vendor/bin/phpunit --test-suffix=.php tests/Galette/

# 5. Check code quality
vendor/bin/phpstan analyse
vendor/bin/php-cs-fixer fix --dry-run
vendor/bin/phpcs
```

**Critical:** If any of these commands fail locally, the CI will also fail. Fix all issues before pushing.

## Common Workflows & Patterns

### Adding a New Feature
1. Create feature branch from `develop`: `git checkout -b feature/my-feature develop`
2. Install dependencies: `bin/install_deps` (or `composer install && npm install`)
3. Set up test database: `bin/console galette:install [options]` (if not already done)
4. Make code changes in appropriate directory (`galette/lib/`, `galette/templates/`, etc.)
5. If modifying frontend: Update files in `ui/` and run `npm run build`
6. Add/update tests in `tests/`
7. Run quality checks: PHPStan, PHP-CS-Fixer, PHPCS
8. Run tests: `DB=mysql galette/vendor/bin/phpunit --test-suffix=.php tests/Galette/`
9. Commit with meaningful message following project conventions

### Fixing a Bug
1. Create bugfix branch from appropriate base (`develop` or `master`)
2. Add regression test in `tests/` that reproduces the bug
3. Fix the bug
4. Verify test now passes: `DB=mysql galette/vendor/bin/phpunit --test-suffix=.php tests/Galette/`
5. Run full test suite and quality checks
6. Commit and create PR

### Modifying Database Schema
1. Create new migration file in `patches/`
2. Follow existing naming convention: `YYYY-MM-DD_description.sql`
3. Test migration on clean database
4. Update relevant model classes
5. Add tests for new schema changes

## File Permissions & Directories

**Writable Directories Required:**
- `galette/config/` - During installation only (remove write access after install)
- `galette/data/` - Must always be writable (attachments, cache, logs, photos, exports, imports, files, tempimages)

**Security Note:** In production, expose ONLY `galette/webroot/` to the web server. All other directories should be outside the web root or protected by .htaccess.

## Common Issues & Solutions

### Composer Install Fails
**Issue:** `composer install` fails with memory limit error  
**Solution:** Increase PHP memory limit: `php -d memory_limit=512M $(which composer) install`

### NPM Build Fails
**Issue:** `npm run build` fails with "Cannot find module"  
**Solution:** Delete `node_modules/` and `package-lock.json`, then run `npm install` again

### PHPUnit Tests Fail with Database Errors
**Issue:** Tests fail with "Connection refused", "Access denied", or "Database not found"  
**Solution:** 
1. Ensure database server is running (MySQL/MariaDB or PostgreSQL)
2. Install/initialize the test database: `bin/console galette:install [options]`
   - Check `.github/workflows/*.yml` files for example parameters
   - Ensure database credentials are correct
3. Grant necessary permissions to database user (CREATE, DROP, INSERT, UPDATE, DELETE, SELECT)
4. Verify the `DB` environment variable matches your database type: `DB=mysql` or `DB=pgsql`
5. Check database connection settings in configuration files

### PHP-CS-Fixer Changes Too Many Files
**Issue:** PHP-CS-Fixer wants to modify many files after checkout  
**Solution:** This is expected if switching branches. Run `vendor/bin/php-cs-fixer fix` and commit the style fixes

### PHPStan Reports Errors in Vendor Code
**Issue:** PHPStan analyzes vendor/ directory  
**Solution:** This should not happen with proper configuration. Check `phpstan.neon` excludes paths and `vendor/` is in `.gitignore`

## Development Best Practices

### Code Style
- Follow PSR-12 coding standard
- Use type declarations for all method parameters and return types
- Document classes and public methods with PHPDoc blocks
- Keep methods focused and under 50 lines when possible

### Testing
- Write tests for all new features
- Aim for high coverage on business logic
- Use meaningful test names: `testMethodDoesExpectedThingWhenCondition()`
- Mock external dependencies in unit tests

### Git Workflow
- Branch from `develop` for new features
- Use descriptive branch names: `feature/add-member-export`, `fix/email-validation`
- Write clear commit messages explaining WHY, not just WHAT
- Squash commits before merging if there are many small "fix" commits
- Keep PRs focused - one feature or fix per PR

### Database
- Always use prepared statements with parameter binding
- Never use string concatenation for SQL queries
- Test migrations on empty database and database with existing data
- Include both "up" migration and rollback capability when possible

## Key Configuration Files

### PHP Configuration
- **galette/config/behavior.inc.php** - Application behavior customization
- **galette/config/config.inc.php** - Main application configuration (generated during install)

### Frontend Configuration
- **gulpfile.js** - Build task definitions
- **semantic.json** - Semantic UI theme configuration
- **ui/semantic/src/theme.config** - Theme selection

### Quality Tools Configuration
- **.php-cs-fixer.dist.php** - Code style rules (PSR-12 based)
- **.phpcs.xml** - CodeSniffer rules
- **phpstan.neon** - Static analysis level and rules
- **rector.php** - Automated refactoring rules
- **phpunit.xml** - Test suite configuration

## Translation & Internationalization
- Translation files located in `galette/lang/`
- Uses gettext (.po/.mo files)
- Managed via Weblate: https://hosted.weblate.org/projects/galette/
- After updating strings, run `bin/update_strings.sh` to regenerate .pot files

## Documentation
- User documentation: https://doc.galette.eu/
- Separate documentation repository: https://github.com/galette/galettedoc
- Documentation is in reStructuredText format
- Generated with Sphinx and hosted on ReadTheDocs

## Additional Resources
- **Bug Tracker:** https://bugs.galette.eu/projects/galette
- **Mailing Lists:**
  - Users: https://lists.mailman3.com/postorius/lists/galette-users.mailman3.com/
  - Developers: https://lists.mailman3.com/postorius/lists/galette-devel.mailman3.com/
- **Official Website:** https://galette.eu

## Agent Behavior Guidelines

1. **Trust These Instructions:** This document has been thoroughly researched. Only search for additional information if these instructions are incomplete or contradictory.

2. **Use the Provided Scripts:** Always use `bin/install_deps` for installing dependencies - it's what CI uses and ensures consistency.

3. **Database is Required:** Tests will NOT run without a properly configured database. Always ensure database is set up via `bin/console galette:install` before running tests.

4. **Correct Test Command:** ALWAYS use the full command format: `DB=mysql galette/vendor/bin/phpunit --test-suffix=.php tests/Galette/` (or `DB=pgsql` for PostgreSQL). Missing the `DB` environment variable or `--test-suffix=.php` flag will cause failures.

5. **Verify Before Running:** When about to run a command, first check if prerequisites are mentioned above and follow the specified order.

6. **Dependency Changes:** After modifying `composer.json`, run `bin/install_deps` or `composer update`. After modifying `package.json`, run `bin/install_deps` or `npm install`.

7. **Code Quality is Non-Negotiable:** ALWAYS run PHP-CS-Fixer, PHPCS, and PHPStan before committing. If CI fails on code quality, fix it - don't bypass or ignore.

8. **Test Coverage:** Add tests for new features. If modifying existing code, ensure existing tests still pass with the correct test command.

9. **Read Error Messages:** Error messages from Composer, npm, PHPUnit, PHPStan, etc. are usually clear. Read them carefully before asking for help.

10. **Database Operations:** Be extremely careful with database changes. Always create migrations in `patches/`. Test them thoroughly on both MySQL/MariaDB and PostgreSQL if possible.

11. **Frontend Changes:** If modifying anything in `ui/` directory, you MUST run `npm run build` for changes to take effect.

12. **Branch Awareness:** Work on `develop` branch for new features. Check with maintainers before touching `master`.

13. **Performance Matters:** Galette is used by organizations with thousands of members. Consider performance implications of changes, especially in loops and database queries.
