# Running the mailing queue E2E test locally

`tests/e2e/specs/mailings-queue.spec.ts` is the only E2E test that **actually
sends** a mass mailing. It never delivers real email: Galette is pointed at a
local SMTP catcher ([Mailpit](https://mailpit.axllent.org/)) which captures
everything and exposes an HTTP API the test asserts on.

The test **skips itself automatically** when Mailpit is not reachable, so a
normal local E2E run without Mailpit stays green. Follow the steps below to run
it for real.

## 1. Base E2E setup

First get a working E2E environment as described in
[`README.tests.md`](../../README.tests.md) and [`README.md`](README.md):

```bash
# dependencies + assets (first time)
bin/install_deps
npx playwright install --with-deps chromium

# test data dir
php tests/init_test_data.php

# install the test database (MySQL example) — DROPS AND RECREATES galette_tests
GALETTE_TESTS=1 DB=mysql bin/console galette:install \
  --dbtype=mysql --dbhost=localhost --dbname=galette_tests \
  --dbuser=galette_tests --dbpass=g@l3tte \
  --admin=admin --password=admin --no-interaction

# seed fixture members (needed: the test selects members to mail)
GALETTE_TESTS=1 DB=mysql bin/console galette:seed-fixtures
```

Then start the E2E PHP server in a dedicated terminal:

```bash
DB=mysql php -S 0.0.0.0:8090 -t galette/webroot tests/router_e2e.php
```

> Use `DB=pgsql` and the matching `galette:install` command if you test against
> PostgreSQL.
>
> If your local database credentials differ from the ones above, override them
> in `tests/config/<db>/local_config.inc.php`, e.g.:
> ```php
> <?php
> define("USER_DB", "galette");
> define("PWD_DB", "your-password");
> ```

## 2. Start Mailpit

Mailpit is a single self-contained binary — no system service to install. Pick
one option.

### Option A — Docker (simplest)

```bash
docker run -d --name mailpit \
  -e MP_SMTP_BIND_ADDR=0.0.0.0:1025 -e MP_UI_BIND_ADDR=0.0.0.0:8025 \
  -p 1025:1025 -p 8025:8025 axllent/mailpit
```

> **IPv4 matters.** Galette connects to `127.0.0.1:1025` (IPv4). Mailpit binds
> its SMTP port on `[::]` (IPv6) by default; the `MP_SMTP_BIND_ADDR=0.0.0.0:1025`
> above forces IPv4 so the connection is not refused. With `--network host`
> (instead of `-p`) this env var is required; with published ports (`-p`) it is a
> harmless safety net.

### Option B — standalone binary

Download the binary for your platform from
<https://github.com/axllent/mailpit/releases> (or `brew install mailpit` on
macOS), then run it, binding SMTP on IPv4:

```bash
MP_SMTP_BIND_ADDR=0.0.0.0:1025 ./mailpit   # SMTP on :1025, web UI + API on :8025
```

Check it is up: open <http://127.0.0.1:8025> (the Mailpit inbox) or

```bash
curl -sf http://127.0.0.1:8025/readyz && echo OK
```

Ports matter: the PHP server must reach SMTP on `127.0.0.1:1025` and Playwright
must reach the API on `127.0.0.1:8025`. The commands above already match.

## 3. Run the test

```bash
E2E_BASE_URL=http://127.0.0.1:8090 \
E2E_MAILPIT_URL=http://127.0.0.1:8025 \
E2E_ADMIN_USER=admin E2E_ADMIN_PASS=admin \
  npx playwright test tests/e2e/specs/mailings-queue.spec.ts --project chromium
```

All four variables have sensible defaults, so in the common case you can just
run:

```bash
npx playwright test tests/e2e/specs/mailings-queue.spec.ts --project chromium
```

Set them only when they differ from the defaults:

- `E2E_BASE_URL` — the PHP server URL (default `http://127.0.0.1:8090`). Set it if
  you started the server on another port.
- `E2E_MAILPIT_URL` — the Mailpit API URL (default `http://127.0.0.1:8025`).
- `E2E_ADMIN_USER` / `E2E_ADMIN_PASS` — superadmin credentials (default
  `admin` / `admin`, matching the install command above).

What the test does:

1. Calls the test-only endpoint `/test/preferences` (action `configure_mail`) to
   point Galette at Mailpit (`127.0.0.1:1025`), enable a batch size of 2 and a
   daily quota — so the mailing goes through the **persistent queue**.
2. Selects a few fixture members and starts a mailing (subject + body), then
   confirms it.
3. Lands on the queue progress page and waits for the AJAX drainer to finish.
4. Asserts Mailpit captured exactly one message per BCC batch
   (`ceil(recipients / batch_size)`), filtered by a unique subject.
5. Resets the mail configuration (action `reset_mail`) afterwards.

## 4. Inspect / clean up

- Browse captured mail in the Mailpit UI: <http://127.0.0.1:8025>.
- Empty the mailbox:
  ```bash
  curl -X DELETE http://127.0.0.1:8025/api/v1/messages
  ```
- Stop Mailpit:
  ```bash
  docker rm -f mailpit        # Option A
  # or Ctrl-C the binary      # Option B
  ```

## Notes & troubleshooting

- **Test is skipped** ("Mailpit is not reachable"): Mailpit is not running or the
  API port is not `8025`. Start it (step 2) or set `E2E_MAILPIT_URL`.
- **0 messages captured**: make sure fixtures were seeded (step 1) — the test
  needs members with email addresses to enqueue.
- **Nothing arrives in Mailpit** but the test reaches the progress page: the PHP
  server cannot reach SMTP `127.0.0.1:1025` (the queue rows end up `failed` with
  `SMTP connect() failed ... Connection refused` in `tests/tests-data/logs/`).
  Usually Mailpit is bound on IPv6 only — start it with
  `MP_SMTP_BIND_ADDR=0.0.0.0:1025` as shown in step 2.
- This test mutates the global mail preferences while it runs and resets them at
  the end. Run it on the disposable `galette_tests` database only.
- In CI this is all automated: the E2E workflow starts a Mailpit service
  container and sets `E2E_MAILPIT_URL` (see `.github/workflows/e2e.yml`).
```
