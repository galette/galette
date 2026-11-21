# E2E Test Credentials

All fixture members share the same password. The superadmin account is created
during installation, not by the fixtures seeder.

Profiles of `loggedInAs()`, in `fixtures/auth.fixture.ts`:

| Profile             | Login                | Password       | Access                                        |
|---------------------|----------------------|----------------|-----------------------------------------------|
| `superadmin`        | admin                | admin          | everything, no member card                    |
| `admin`             | leia.organa          | G@l3tte-E2E!   | Administrator role                            |
| `staff`             | morpheus             | G@l3tte-E2E!   | Staff member role (treasurer status)          |
| `member`            | luke.skywalker       | G@l3tte-E2E!   | Member role; parent of Ben Skywalker          |
| `groupManager`      | anakin.skywalker     | G@l3tte-E2E!   | Group manager of "Skywalker Family"           |
| `otherGroupManager` | esteban.dore         | G@l3tte-E2E!   | Group manager of "Les Explorateurs Solaires"  |
| `treasury`          | hermione.granger     | G@l3tte-E2E!   | "Trésorerie" role, for all groups             |
| `groupSecretary`    | sally.ragdoll        | G@l3tte-E2E!   | "Secrétariat de groupe" role, for "Les Âmes Errantes" |
| All others          | *(see login column)* | G@l3tte-E2E!   |                                               |

Specs changing permissions of the roles, `specs/roles-changes.spec.ts`, run in
the `roles` project, on their own: `npx playwright test --project roles`.
Likewise, `specs/maps.spec.ts` enables maps for the whole instance and runs in
the `maps` project: `npx playwright test --project maps`.

## Seeding / Cleaning

```bash
# Seed fixtures (idempotent — safe to re-run)
bin/console galette:seed-fixtures

# Remove all fixture data
bin/console galette:seed-fixtures --clean

# List fixtures sets, with the ones they require
bin/console galette:seed-fixtures --list

# Seed again some sets only (and the ones depending on them), keeping others
bin/console galette:seed-fixtures plugin-events mailings

# Remove some sets only: here transactions, and contributions depending on them
bin/console galette:seed-fixtures --clean transactions
```

Seeding a set removes it first, with the sets depending on it: `members` plays
everything again, a plugin id only that plugin. A required set missing from the
database is seeded as well, so `groups` on an empty database also creates
members. History of the `superadmin` account is only removed when all fixtures
are.

## Notable members

- **Leia Organa** — admin flag, president status
- **Morpheus** — treasurer status
- **Turanga Leela** — secretary status
- **Anakin Skywalker** — group manager of "Skywalker Family"
- **Obi-Wan Kenobi** — founder status
- **Bender Rodriguez** — veteran status, donation only (no fee)
- Children (attached to parents): Ben Skywalker, Jacen Solo, Rickon Stark,
  Yancy Jr Fry, Tao Doré, Junior Anderson, Télémaque Ulysse, Zéro Skellington
