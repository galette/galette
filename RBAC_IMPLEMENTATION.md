# RBAC Implementation Documentation for Galette

This document summarizes the transition from legacy SQL-based rights (e.g., `admin_adh`) to a granular **Role-Based Access Control (RBAC)** system.

## 1. Vision & Architecture
The system uses `domain:action` nomenclature (e.g., `member:read`, `group:manage`).

### SQL Schema
Four new tables are introduced:
- `permissions`: Atomic actions (ID, Name, Description).
- `roles`: Groups of permissions (e.g., Secretary, Treasurer).
- `role_permissions`: Link between roles and permissions.
- `adherent_roles`: Link between members and roles, with optional `id_group` for contextual rights.

**Migration Script:** `patches/2026-03-22_rbac_migration.sql`

## 2. Core Logic (`AccessControl.php`)
The [AccessControl](galette/lib/Galette/Core/AccessControl.php) class is the central authority.
Hierarchy:
1. **Super-Admin**: Total bypass (if `is_superadmin` is true).
2. **Account Status**: Deny all if `is_active` is false.
3. **RBAC Check**: Static permission lookup in roles (supports wildcards like `domain:*`).
4. **Dynamic Voters**: Advanced contextual checks.

### Voters
- `SubscriptionVoter`: Restricts access if contribution is not up to date.
- `GroupVoter`: Handles group manager logic (contextual access to group members).

## 3. API & JWT Integration
- **Middleware**: `ApiRbacMiddleware` checks permissions via `rbac` attribute.
- **Payload**: JWTs are expected to contain `uid` and `scopes` (permissions).
- **AuthController**: Added `apiLogin()` method and `/api/login` route to generate JWT payloads.

## 4. Administration UI
- **Matrix**: A dedicated UI at `/rbac` to map permissions to roles.
- **Grouping**: Permissions are grouped by domain (member, group, etc.) for readability.
- **Controller**: `RbacController.php` manages the matrix logic.

## 5. Key Files
- `galette/lib/Galette/Core/AccessControl.php`: Main entry point.
- `galette/lib/Galette/Core/Voters/`: Dynamic logic directory.
- `galette/lib/Galette/Controllers/RbacController.php`: Admin UI logic.
- `galette/templates/default/pages/rbac_matrix.html.twig`: Admin UI template.
- `galette/includes/dependencies.php`: DI container registration.
- `galette/includes/routes/management.routes.php`: Admin routes.
- `galette/includes/routes/authentication.routes.php`: API login route.

## 6. How to verify
Run the integration tests:
```bash
DB=mysql galette/vendor/bin/phpunit --test-suffix=.php tests/Galette/Core/AccessControlTest.php
```

## 7. Current Status
- [x] SQL Schema & Initial Data
- [x] Core AccessControl logic
- [x] Wildcard support (`domain:*`)
- [x] Group manager logic (refined)
- [x] API Middleware & JWT groundwork
- [x] Admin Matrix UI (grouped)
- [ ] Refactoring existing core logic to use `AccessControl::can()`

## Review comments

- Chaque role devrait pouvoir hériter d'un autre rôle. Un Adhérent et un Adhérent à jour. Un Admin, un Trésorier ou un Secrétaire sont des Adhérents.
- Le rôle superadmin est hardcodé. Pourquoi pas, mais il est certaines choses qui ne doivent pas être accessibles (par exemple afficher sa propre fiche adhérent qui n'existe pas)
