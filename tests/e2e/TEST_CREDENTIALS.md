# E2E Test Credentials

All fixture members share the same password. The superadmin account is created
during installation, not by the fixtures seeder.

| Role            | Login                | Password       |
|-----------------|----------------------|----------------|
| Superadmin      | admin                | admin          |
| Admin           | leia.organa          | G@l3tte-E2E!   |
| Treasurer       | morpheus             | G@l3tte-E2E!   |
| Secretary       | turanga.leela        | G@l3tte-E2E!   |
| Standard member | luke.skywalker       | G@l3tte-E2E!   |
| All others      | *(see login column)* | G@l3tte-E2E!   |

## Seeding / Cleaning

```bash
# Seed fixtures (idempotent — safe to re-run)
bin/console galette:seed-fixtures

# Remove all fixture data
bin/console galette:seed-fixtures --clean
```

## Notable members

- **Leia Organa** — admin flag, president status
- **Morpheus** — treasurer status
- **Turanga Leela** — secretary status
- **Obi-Wan Kenobi** — founder status
- **Bender Rodriguez** — veteran status, donation only (no fee)
- Children (attached to parents): Ben Skywalker, Jacen Solo, Rickon Stark,
  Yancy Jr Fry, Tao Doré, Junior Anderson, Télémaque Ulysse, Zéro Skellington
