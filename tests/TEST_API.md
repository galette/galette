# Test API for E2E Tests

This document describes the test-only API endpoints available during E2E testing.

## Overview

The test API provides HTTP endpoints to modify Galette's configuration dynamically during Playwright tests, without requiring UI interactions or direct database access.

## Security

⚠️ **IMPORTANT**: These endpoints are **only accessible** via the test router (`tests/router_e2e.php`).

**Security mechanism:**
- The API is not directly exposed - it's only loaded when accessed via `/test/preferences` route
- The test router (`tests/router_e2e.php`) is only used during E2E testing with PHP's built-in server
- The router explicitly checks if the URI starts with `/test/` before loading test API handlers
- In production, Apache/Nginx serve from `galette/webroot/` and don't expose `tests/` directory

**No production risk:** The test API files are located in the `tests/` directory which should never be accessible in a properly configured production environment.

## Endpoints

### `/test/preferences`

Manages Galette preferences (public pages visibility, etc.)

#### Enable Public Pages

**Request:**
```http
POST /test/preferences
Content-Type: application/json

{
  "action": "enable_public_pages",
  "visibility": 1
}
```

**Parameters:**
- `action`: `"enable_public_pages"` (required)
- `visibility`: Integer visibility level (optional, default: 1)
  - `0` = PUBLIC (accessible to all, no authentication)
  - `1` = RESTRICTED (accessible to up-to-date members only)
  - `2` = PRIVATE (accessible to admin/staff only)
  - `3` = HIDDEN (not accessible to anyone)

**Response:**
```json
{
  "success": true,
  "message": "Public pages enabled"
}
```

#### Disable Public Pages

**Request:**
```http
POST /test/preferences
Content-Type: application/json

{
  "action": "disable_public_pages"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Public pages disabled"
}
```

#### Set Specific Page Visibility

**Request:**
```http
POST /test/preferences
Content-Type: application/json

{
  "action": "set_public_page_visibility",
  "page_name": "pref_publicpages_visibility_memberslist",
  "visibility": 0
}
```

**Parameters:**
- `action`: `"set_public_page_visibility"` (required)
- `page_name`: Preference name (required)
  - `pref_publicpages_visibility_generic` - Default/fallback visibility
  - `pref_publicpages_visibility_memberslist` - Members list page
  - `pref_publicpages_visibility_membersgallery` - Members gallery page
  - `pref_publicpages_visibility_stafflist` - Staff list page
  - `pref_publicpages_visibility_staffgallery` - Staff gallery page
  - `pref_publicpages_visibility_documents` - Documents list page
- `visibility`: Integer visibility level (required, see above)

**Response:**
```json
{
  "success": true,
  "message": "Visibility set for pref_publicpages_visibility_memberslist"
}
```

#### Restore Default Configuration

**Request:**
```http
POST /test/preferences
Content-Type: application/json

{
  "action": "restore_default_public_pages"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Default public pages configuration restored"
}
```

**Default Configuration:**
- `pref_bool_publicpages` = `true`
- All visibility settings = `1` (RESTRICTED)

#### Get Current Configuration

**Request:**
```http
GET /test/preferences?action=get_public_pages_config
```

**Response:**
```json
{
  "enabled": true,
  "generic": 1,
  "memberslist": 1,
  "membersgallery": 1,
  "stafflist": 1,
  "staffgallery": 1,
  "documents": 1
}
```

## Usage in Playwright Tests

Use the `PreferencesHelper` class instead of making direct HTTP calls:

```typescript
import { PreferencesHelper, PUBLIC_PAGES_VISIBILITY } from '../helpers/preferences';

// Enable public pages
await PreferencesHelper.enablePublicPages(page, PUBLIC_PAGES_VISIBILITY.PUBLIC);

// Set specific page visibility
await PreferencesHelper.setPublicPageVisibility(
  page,
  'pref_publicpages_visibility_memberslist',
  PUBLIC_PAGES_VISIBILITY.PRIVATE
);

// Restore defaults
await PreferencesHelper.restoreDefaultPublicPages(page);

// Get current config
const config = await PreferencesHelper.getPublicPagesConfig(page);
```

## Implementation Details

The test API is implemented in:
- **Entry point**: `tests/router_e2e.php` - Routes `/test/*` requests to test API handlers
- **Handler**: `tests/test_preferences_api.php` - Implements preference management logic

The API uses Galette's bootstrap (`galette.inc.php`) to properly initialize the application and access global objects like `$zdb` and `$preferences`. It then uses the `Preferences` object to modify settings and persist them to the database using `$preferences->store()`.

## Error Handling

All endpoints return appropriate HTTP status codes:
- `200 OK` - Success
- `403 Forbidden` - Test API not enabled (GALETTE_TESTS != 1)
- `500 Internal Server Error` - Error during operation

Error responses include details:
```json
{
  "error": "Error message",
  "trace": "Stack trace (in test mode only)"
}
```

## Adding New Test Endpoints

To add new test API functionality:

1. Add a new action case in `tests/test_preferences_api.php`
2. Implement the logic using global `$zdb`, `$preferences`, etc.
3. Return appropriate JSON response
4. Add helper methods to TypeScript helper classes
5. Document the new endpoint here
