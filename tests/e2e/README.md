# Galette E2E Tests Documentation

This document describes the structure and organization of Playwright E2E tests for Galette.

## Directory Structure

```
tests/e2e/
├── README.md                  # This file
├── TEST_CREDENTIALS.md        # Test user credentials and fixture data
├── fixtures/                  # Playwright fixtures (auth, a11y)
│   ├── auth.fixture.ts       # Authentication fixture (auto-login)
│   └── a11y.fixture.ts       # Accessibility testing utilities (axe-core)
├── pages/                     # Page Object Models
│   ├── MemberListPage.ts     # Members list page interactions
│   ├── MemberFormPage.ts     # Member form page interactions
│   ├── GroupListPage.ts      # Groups list page interactions
│   └── GroupFormPage.ts      # Group form page interactions
├── helpers/                   # Reusable helper functions
│   └── dropdown.ts           # Fomantic UI dropdown interactions
└── specs/                     # Test specifications
│   ├── auth.spec.ts          # Authentication tests (login/logout)
│   ├── members.spec.ts       # Member CRUD operations
│   ├── members.fixture.spec.ts    # Member tests using fixture data
│   ├── contributions.spec.ts      # Contribution CRUD operations
│   ├── contributions.fixture.spec.ts  # Contribution tests using fixture data
│   ├── groups.spec.ts        # Group CRUD operations
│   ├── groups.fixture.spec.ts     # Group tests using fixture data
│   ├── forms.fixture.spec.ts      # Form validation tests
│   └── a11y.spec.ts          # Accessibility compliance tests (WCAG 2.1 A/AA + RGAA)
```

## Test File Organization

### Naming Convention

Test files follow two patterns:

1. **`<feature>.spec.ts`** - Tests that create their own test data
   - Example: `members.spec.ts` creates a unique test member for CRUD operations
   - These tests are **isolated** and can run in any order
   - Use `TEST_MEMBER` constant with unique login to avoid conflicts

2. **`<feature>.fixture.spec.ts`** - Tests that use pre-seeded fixture data
   - Example: `members.fixture.spec.ts` searches for "Luke Skywalker" from fixtures
   - These tests **depend on** `bin/console galette:seed-fixtures` being run first
   - See `TEST_CREDENTIALS.md` for available fixture users

### Test Structure Philosophy

Tests are organized with **flat hierarchy** to improve readability in Playwright UI:

```typescript
// ✅ GOOD: Flat structure with descriptive test names
test.describe('Members', () => {
  test('Members - Display list with data', async ({ loggedInPage }) => { ... });
  test('Members - Filter by name', async ({ loggedInPage }) => { ... });
  test('Members - Create new member', async ({ loggedInPage }) => { ... });
});

// ❌ AVOID: Deep nesting (hard to navigate in --ui mode)
test.describe('Members', () => {
  test.describe('List', () => {
    test.describe('Display', () => {
      test('should display the members list', async () => { ... });
    });
  });
});
```

**Rationale**: When running tests with `npx playwright test --ui`, a flat structure makes it easier to:
- Find specific tests quickly
- See test status at a glance
- Access error messages directly (no empty nested levels)

### Test Categories

#### Authentication Tests (`auth.spec.ts`)
- Login page display and validation
- Invalid credentials handling
- Successful login and redirect to dashboard
- Logout functionality

#### Member Tests
- **`members.spec.ts`**: CRUD operations with isolated test data
  - Create member with unique `e2e.test.user` login
  - Edit member
  - Delete member
  - List display and filtering
  
- **`members.fixture.spec.ts`**: Tests with pre-seeded fixture data
  - Search for fixture members (Luke Skywalker, Hermione Granger, etc.)
  - Verify fixture data integrity
  - Test member details pages with known data

#### Contribution Tests
- **`contributions.spec.ts`**: CRUD operations for contributions
  - Add membership fee
  - Add donation
  - List display
  
- **`contributions.fixture.spec.ts`**: Tests with fixture contributions
  - Verify contribution states (up-to-date, expired)
  - Test contribution details
  - Member-contribution relationships

#### Group Tests
- **`groups.spec.ts`**: CRUD operations for groups
  - Create group with unique name
  - Edit group
  - Add/remove members
  - Delete group
  
- **`groups.fixture.spec.ts`**: Tests with fixture groups
  - Verify fixture groups (families, staff roles)
  - Navigate to group details
  - Test group-member relationships
  - Verify group hierarchy

#### Form Tests (`forms.fixture.spec.ts`)
- Member form validation (add/edit)
- Contribution form validation
- Required field checks
- Email format validation
- Form navigation (cancel/back buttons)

#### Public Pages Tests
- **`public-pages.spec.ts`**: Public pages accessible without authentication
  - Public members list
  - Public members gallery (trombinoscope)
  - Public staff list and gallery
  - Public documents list
  - Deprecated routes redirections
  - No authentication requirement validation

#### Accessibility Tests (`a11y.spec.ts`)
- WCAG 2.1 A/AA compliance testing using axe-core
- Tests all major pages: login, dashboard, members, contributions, groups, preferences, configuration, import/export, public pages, mailings, saved searches
- Keyboard navigation testing
- Color contrast verification
- Form label accessibility
- **Note**: All tests prefixed with "A11y -" for easy filtering

## Fixtures

### Authentication Fixture (`fixtures/auth.fixture.ts`)

Provides `loggedInPage` fixture that automatically logs in before each test:

```typescript
test('My test', async ({ loggedInPage: page }) => {
  // Already logged in as admin
  await page.goto('/members');
});
```

**Default credentials**: `admin` / `admin` (superadmin account)

#### Multi-Role Authentication

The fixture also provides `loggedInAs()` for testing with different user roles:

```typescript
import { test } from '../fixtures/auth.fixture';

test('Admin test', async ({ loggedInAs }) => {
  const page = await loggedInAs('admin');
  // Test with admin role (leia.organa)
  await page.goto('/preferences');
  // ...
  await page.close();
});

test('Member test', async ({ loggedInAs }) => {
  const page = await loggedInAs('member');
  // Test with standard member (luke.skywalker)
  await page.goto('/members');
  // ...
  await page.close();
});
```

**Available roles:**
- `'superadmin'` - admin / admin (full access)
- `'admin'` - leia.organa / G@l3tte-E2E! (administration rights)
- `'treasurer'` - morpheus / G@l3tte-E2E! (financial management)
- `'secretary'` - turanga.leela / G@l3tte-E2E! (administrative tasks)
- `'member'` - luke.skywalker / G@l3tte-E2E! (standard member)
- `'groupManager'` - anakin.skywalker / G@l3tte-E2E! (group manager)

See `TEST_CREDENTIALS.md` for complete fixture user list.

### Accessibility Fixture (`fixtures/a11y.fixture.ts`)

Provides `axeBuilder` for accessibility testing:

```typescript
import { axeBuilder, formatViolations } from '../fixtures/a11y.fixture';

test('My a11y test', async ({ page }) => {
  await page.goto('/members');
  const results = await axeBuilder(page).analyze();
  expect(results.violations, formatViolations(results.violations)).toEqual([]);
});
```

## Page Objects

### MemberListPage (`pages/MemberListPage.ts`)

Encapsulates member list page interactions:

```typescript
const listPage = new MemberListPage(page);
await listPage.goto();
await listPage.filterByName('SKYWALKER');
const row = listPage.getMemberRowByName('SKYWALKER');
```

### MemberFormPage (`pages/MemberFormPage.ts`)

Encapsulates member form interactions:

```typescript
const formPage = new MemberFormPage(page);
await formPage.goto();
await formPage.fill({
  lastName: 'Doe',
  firstName: 'John',
  email: 'john.doe@example.com'
});
await formPage.submit();
```

## Helpers

### DropdownHelper (`helpers/dropdown.ts`)

Provides utilities for interacting with Fomantic UI dropdowns:

```typescript
import { DropdownHelper } from '../helpers/dropdown';

// Select first item
await DropdownHelper.selectFirst(page, 'id_type_cotis');

// Select by text
await DropdownHelper.selectByText(page, 'id_type_cotis', 'Annual fee');

// Select by index
await DropdownHelper.selectByIndex(page, 'id_type_cotis', 2);

// Clear selection
await DropdownHelper.clear(page, 'id_type_cotis');

// Get selected text
const selected = await DropdownHelper.getSelectedText(page, 'id_type_cotis');

// Get all options
const options = await DropdownHelper.getOptions(page, 'id_type_cotis');
```

### NavigationHelper (`helpers/navigation.ts`)

Simplifies navigation and URL assertions:

```typescript
import { NavigationHelper } from '../helpers/navigation';

// Navigate and assert URL
await NavigationHelper.goTo(page, '/members');

// Assert URL contains segment
await NavigationHelper.expectUrlContains(page, 'member');

// Wait for navigation
await NavigationHelper.waitForNavigation(page);
```

### ModalHelper (`helpers/modal.ts`)

Handles Fomantic UI modal interactions:

```typescript
import { ModalHelper } from '../helpers/modal';

// Wait for modal to open
const modal = await ModalHelper.waitForOpen(page);

// Click confirm button
await ModalHelper.clickConfirm(page);

// Click cancel button
await ModalHelper.clickCancel(page);

// Assert modal contains text
await ModalHelper.expectModalText(page, 'Confirm delete?');

// Check if modal is visible
const isVisible = await ModalHelper.isVisible(page);
```

### FlashMessageHelper (`helpers/flash.ts`)

Verifies flash messages (success, error, warning):

```typescript
import { FlashMessageHelper } from '../helpers/flash';

// Assert success message
await FlashMessageHelper.expectSuccess(page);
await FlashMessageHelper.expectSuccess(page, 'Member created');

// Assert error message
await FlashMessageHelper.expectError(page);
await FlashMessageHelper.expectError(page, 'Invalid email');

// Assert warning message
await FlashMessageHelper.expectWarning(page);

// Dismiss message
await FlashMessageHelper.dismiss(page);
```

### TableHelper (`helpers/table.ts`)

Utilities for table/listing interactions:

```typescript
import { TableHelper } from '../helpers/table';

// Get row count
const count = await TableHelper.getRowCount(page);

// Get row by text
const row = TableHelper.getRowByText(page, 'SKYWALKER');

// Click action in row
await TableHelper.clickAction(page, 'SKYWALKER', '.edit');

// Check if table is empty
const isEmpty = await TableHelper.isEmpty(page);

// Get cell value
const value = await TableHelper.getCellValue(page, 'SKYWALKER', 2);
```

### DateHelper (`helpers/date.ts`)

Handles date input fields with Fomantic UI calendar:

```typescript
import { DateHelper } from '../helpers/date';

// Fill date field
await DateHelper.fillDate(page, 'date_echeance', '2026-12-31');

// Fill date by selector
await DateHelper.fillDateBySelector(page, '#birth_date', '1990-01-01');

// Get date value
const date = await DateHelper.getDate(page, 'date_echeance');

// Clear date
await DateHelper.clearDate(page, 'date_echeance');
```

### FormHelper (`helpers/form.ts`)

Common form actions:

```typescript
import { FormHelper } from '../helpers/form';

// Submit form
await FormHelper.submitForm(page);

// Fill text field
await FormHelper.fillTextField(page, 'nom_adh', 'Skywalker');

// Check checkbox
await FormHelper.checkCheckbox(page, 'bool_admin_adh');

// Select radio
await FormHelper.selectRadio(page, 'sexe_adh', '0');

// Assert validation error
await FormHelper.expectValidationError(page, 'email_adh');

// Check if form has errors
const hasErrors = await FormHelper.hasErrors(page);
```

### Importing Helpers

All helpers can be imported from the index:

```typescript
// Individual imports
import { NavigationHelper, ModalHelper, FlashMessageHelper } from '../helpers';

// Or specific import
import { DropdownHelper } from '../helpers/dropdown';
```

## Test Data Management

### Fixture Data (Seeded)

Run `bin/console galette:seed-fixtures` to populate the database with rich test data:

```bash
# Seed fixtures (idempotent — safe to re-run)
bin/console galette:seed-fixtures

# Remove all fixture data
bin/console galette:seed-fixtures --clean
```

Fixture data includes:
- **100+ members** from various fictional universes (Star Wars, Harry Potter, Kaamelott, etc.)
- **Multiple roles**: admin, treasurer, secretary, standard members
- **Contributions**: various states (up-to-date, expired)
- **Groups**: family relationships, staff roles

See `TEST_CREDENTIALS.md` for complete list of fixture users.

### Isolated Test Data

Tests in `*.spec.ts` files create their own data with **unique identifiers** to avoid conflicts:

```typescript
const TEST_MEMBER = {
  lastName:  'E2ETest',
  firstName: 'User',
  login:     'e2e.test.user',  // Unique login
  password:  'E2E-T3st!',
  email:     'e2e.test.user@example.test',
};
```

## Running Tests

### Prerequisites

1. **Install dependencies**:
   ```bash
   bin/install_deps
   ```

2. **Install Playwright browsers** (first time only):
   ```bash
   npx playwright install --with-deps
   ```

3. **Initialize test database**:
   ```bash
   GALETTE_TESTS=1 DB=mysql bin/console galette:install \
     --dbtype=mysql --dbhost=localhost --dbname=galette_tests \
     --dbuser=galette_tests --dbpass=g@l3tte \
     --admin=admin --password=admin --no-interaction
   ```

4. **Seed fixture data** (for `*.fixture.spec.ts` tests):
   ```bash
   GALETTE_TESTS=1 DB=mysql bin/console galette:seed-fixtures
   ```

5. **Start PHP server** (in separate terminal):
   ```bash
   DB=mysql php -S 0.0.0.0:8090 -t galette/webroot tests/router_e2e.php
   ```

### Run Tests

```bash
# Run all tests on Chromium
npm run test:chromium

# Run all tests on Firefox
npm run test:firefox

# Run all tests on all configured browsers
npm run test:full

# Run with UI mode (best for development)
npx playwright test --ui

# Run specific test file
npx playwright test specs/members.spec.ts

# Run tests matching pattern
npx playwright test --grep "Members - Filter"

# Run in headed mode (see browser)
npm run test:headed

# Debug mode (step through tests)
npm run test:debug
```

### View Test Reports

```bash
# Open HTML report
npm run report
```

## Writing New Tests

### 1. Choose Test Type

**Use `<feature>.spec.ts` if:**
- Testing CRUD operations that modify data
- Need isolated test environment
- Tests should not depend on external data

**Use `<feature>.fixture.spec.ts` if:**
- Testing with known, complex data scenarios
- Verifying data relationships
- Testing search/filter with varied data

### 2. Follow Naming Convention

```typescript
test('Feature - Action - Expected behavior', async ({ loggedInPage: page }) => {
  // Test implementation
});
```

Examples:
- `Members - Display list with data`
- `Members - Filter by name`
- `Contributions - Add membership fee`
- `A11y - Login page`

### 3. Use Page Objects

Encapsulate page interactions in Page Object Models instead of scattering selectors throughout tests:

```typescript
// ✅ GOOD: Use Page Object
const listPage = new MemberListPage(page);
await listPage.filterByName('SKYWALKER');

// ❌ AVOID: Direct selectors in tests
await page.locator('input[name="search"]').fill('SKYWALKER');
await page.locator('button[type="submit"]').click();
```

### 4. Keep Tests Focused

Each test should verify **one specific behavior**:

```typescript
// ✅ GOOD: Focused test
test('Members - Display list with data', async ({ loggedInPage: page }) => {
  const listPage = new MemberListPage(page);
  await listPage.goto();
  
  await expect(page).toHaveURL(/\/members/);
  await expect(listPage.memberTable).toBeVisible();
  await expect(listPage.getDataRows()).not.toHaveCount(0);
});

// ❌ AVOID: Testing multiple behaviors
test('Members - Everything', async ({ loggedInPage: page }) => {
  // Display list
  // Filter members
  // Add member
  // Edit member
  // Delete member
  // ... too much in one test
});
```

### 5. Handle Async Properly

Always await page interactions and use Playwright's auto-waiting:

```typescript
// ✅ GOOD: Playwright auto-waits for element
await page.locator('button').click();

// ❌ AVOID: Manual timeouts (brittle)
await page.waitForTimeout(1000);
await page.locator('button').click();
```

## Debugging Tests

### Using Playwright UI Mode

The best way to debug tests:

```bash
npx playwright test --ui
```

Features:
- Visual test runner
- Step through tests
- See DOM snapshots
- Inspect locators
- View console logs

### Using Playwright Inspector

```bash
npx playwright test --debug
```

Opens browser with inspector to:
- Set breakpoints
- Step through actions
- Try locators in console
- Record new actions

### Viewing Failed Test Artifacts

When tests fail, Playwright captures:
- **Screenshots** (on failure)
- **Videos** (on retry)
- **Traces** (detailed timeline)

Artifacts are in `test-results/` directory.

View trace files:
```bash
npx playwright show-trace test-results/.../trace.zip
```

## CI/CD Integration

E2E tests are run in CI, as well as PHPUnit tests, no further configuration should be needed.

## Best Practices

1. **Use fixture data for read-only tests** - Don't modify fixture members/contributions
2. **Create unique data for write tests** - Use unique logins/emails to avoid conflicts
3. **Keep describe() hierarchy flat** - Maximum 1-2 levels for better UI navigation
4. **Prefix test names with feature** - Makes filtering and identification easier
5. **Use Page Objects** - Encapsulate page interactions and selectors
6. **Test one thing per test** - Focused tests are easier to debug
7. **Avoid waitForTimeout()** - Use Playwright's auto-waiting instead
8. **Clean up created data** - Delete test members after CRUD tests (see `members.spec.ts`)
9. **Document fixture dependencies** - Add comments if test requires specific fixture data
10. **Use meaningful assertions** - Include helpful error messages

## Accessibility Testing

All pages should pass WCAG 2.1 Level A/AA compliance. Add accessibility tests for new pages:

```typescript
test('A11y - My new page', async ({ loggedInPage: page }) => {
  await page.goto('/my-new-page');
  await page.locator('h1').waitFor({ state: 'visible' });
  
  const results = await axeBuilder(page).analyze();
  expect(results.violations, formatViolations(results.violations)).toEqual([]);
});
```

## Troubleshooting

### Tests fail with "Connection refused"

Ensure PHP server is running:
```bash
DB=mysql php -S 0.0.0.0:8090 -t galette/webroot tests/router_e2e.php
```

### Tests fail with "Member not found"

Ensure fixture data is seeded:
```bash
GALETTE_TESTS=1 DB=mysql bin/console galette:seed-fixtures
```

### Tests are slow

- Use `--project=chromium` to run only one browser
- Run specific test files instead of all tests
- Check if waitForTimeout() is used (replace with proper waits)

### Flaky tests

- Increase timeout for slow operations: `{ timeout: 10000 }`
- Ensure server is not overloaded
- Check for race conditions in test code
- Use Playwright's auto-waiting instead of fixed timeouts

## Additional Resources

- [Playwright Documentation](https://playwright.dev/)
- [Playwright Best Practices](https://playwright.dev/docs/best-practices)
- [axe-core Rules](https://github.com/dequelabs/axe-core/blob/develop/doc/rule-descriptions.md)
- [WCAG 2.1 Guidelines](https://www.w3.org/WAI/WCAG21/quickref/)

