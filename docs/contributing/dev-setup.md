# Galette Development Environment

This guide explains how to set up an isolated local development environment to contribute to Galette. The system relies on two complementary tools:

- **[branchlet](https://github.com/raghavpillai/branchlet)** — interactive git worktree management (create, list, delete)
- **`gwt`** (Galette Worktree Tool) — automatic Galette environment configuration (DB, dependencies, Apache/Docker)

Two modes are available:

| Mode | Prerequisites | Ideal for |
|------|---------------|-----------|
| **Docker** (recommended) | Git, Node.js, Docker ≥ 24, Docker Compose v2 | External contributors |
| **Native** | httpd, PHP-FPM (remi), PostgreSQL/MySQL | Maintainers |

---

## Installation

### branchlet

```bash
npm install -g branchlet
```

### gwt

```bash
# From the cloned repository
cp bin/gwt ~/bin/gwt
chmod +x ~/bin/gwt

# Verify that ~/bin is in your PATH
echo $PATH | grep -q "$HOME/bin" || echo 'export PATH="$HOME/bin:$PATH"' >> ~/.bashrc
```

Verify the installation:

```bash
gwt selftest
```

---

## Docker Mode (Contributors)

### Prerequisites

- [Git](https://git-scm.com/)
- [Node.js](https://nodejs.org/) (for branchlet)
- [Docker ≥ 24](https://docs.docker.com/get-docker/)
- Docker Compose v2 (`docker compose version`)

### Configure worktree path (one-time)

By default, branchlet creates worktrees in `../<repo>-worktrees/core/<branch>/` relative to the cloned repository. To customize:

```bash
branchlet settings
# → worktreePathTemplate → enter desired path
# Example: /home/user/dev/galette-worktrees/core/$BRANCH_NAME
```

### Typical workflow

#### 1. Set up an environment for a branch

```bash
# develop branch (development base)
gwt add develop --docker

# Feature branch
# Interactive interface (recommended)
branchlet create
# → select or enter the branch
# → branchlet creates the git worktree and launches bin/gwt-setup
# → gwt-setup asks: PHP version and Docker mode
```

`bin/gwt-setup` automatically performs:
- Configuration generation (`config.inc.php`, `behavior.inc.php`)
- Installation of PHP and JS dependencies
- Container startup (PHP-FPM, Apache, PostgreSQL)
- Database schema installation

You can also call `gwt add` directly without using branchlet:

```bash
gwt add feature/my-fix --docker
gwt add feature/my-fix --docker --php 85
```

#### 2. List active worktrees

```bash
gwt ls
# or via branchlet:
branchlet list
```

```
TYPE     BRANCH                         PHP    MODE     URL
────     ──────────────────────────────  ────   ──────   ───────────────────────────────
core     develop                        84     docker   http://localhost:8080/
core     feature/my-fix                 85     docker   http://localhost:8081/
```

#### 3. Develop

Source files are mounted directly in the containers — any changes are immediately visible without rebuild.

```bash
# View logs in real-time
gwt docker feature/my-fix logs

# Execute a command in the container
gwt docker feature/my-fix exec -- bin/console galette:install

# Open a PHP shell
gwt docker feature/my-fix exec -- bash
```

#### 4. Update from develop

```bash
gwt sync feature/my-fix
```

This performs `git pull --ff-only` and automatically updates dependencies if `composer.lock` or `package-lock.json` have changed.

#### 5. Reset the database

```bash
gwt db feature/my-fix reset
```

#### 6. Clean up after feature completion

```bash
gwt rm feature/my-fix
```

Removes the worktree, Docker containers, and database data.

---

## Using Plugins

Plugins are loaded from `galette/plugins/` in the worktree. To enable one:

```bash
# Example: enable the Paypal plugin in the feature/my-fix worktree
WT=/path/to/worktrees/core/feature/my-fix
ln -s /path/to/galette-paypal "${WT}/galette/plugins/Paypal"
```

Then reload the containers:

```bash
gwt docker feature/my-fix down
gwt docker feature/my-fix up
```

---

## Native Mode (Maintainers)

### Additional Prerequisites

- `httpd` with `mod_proxy_fcgi` and `mod_rewrite`
- PHP-FPM from remi packages (`php84-php-fpm`, etc.)
- PostgreSQL or MySQL locally
- sudo access to reload Apache (see below)

### Configure `~/.gwt.conf`

```bash
# ~/.gwt.conf  (mode 600 — contains DB password)
GWT_PROJECTS_BASE=/var/www/projects/galette
GWT_REPOS=(
    "core:/var/www/html/private/galette.git"
    "doc:/var/www/html/private/galette-doc.git"
    "website:/var/www/html/private/galette-website.git"
)
GWT_HTTPD_CONF_D=/etc/httpd/conf.d/galette-worktrees.d
GWT_VHOST_NAME=galette.localhost
GWT_DEFAULT_PHP=84
GWT_DB_TYPE=pgsql
GWT_DB_USER=galette
GWT_DB_PASS=yourpassword
GWT_DB_HOST=localhost
GWT_DEVELOP_WORKTREE=/var/www/projects/galette/core/develop
```

```bash
chmod 600 ~/.gwt.conf
```

### Configure Apache (one-time)

Add to your vhost before `</VirtualHost>`:

```apache
IncludeOptional /etc/httpd/conf.d/galette-worktrees.d/*.conf
```

Create the directory:

```bash
sudo mkdir -p /etc/httpd/conf.d/galette-worktrees.d
```

Optional — passwordless sudo access for Apache snippet management:

```
# /etc/sudoers.d/gwt
trasher ALL=(root) NOPASSWD: /usr/bin/tee /etc/httpd/conf.d/galette-worktrees.d/*
trasher ALL=(root) NOPASSWD: /usr/bin/rm /etc/httpd/conf.d/galette-worktrees.d/*
trasher ALL=(root) NOPASSWD: /usr/bin/systemctl reload httpd
trasher ALL=(root) NOPASSWD: /usr/bin/mkdir -p /etc/httpd/conf.d/galette-worktrees.d
```

### Native workflow

```bash
# Create a worktree with PHP 8.4
gwt add feature/my-fix --php 84

# URL: http://galette.localhost/feature-my-fix/

# Add only the Apache snippet for an existing worktree
gwt apache feature/my-fix add --php 84

# Remove
gwt rm feature/my-fix
```

---

## Command Reference

```
gwt add <branch>        Create a complete environment
gwt rm  <branch>        Remove an environment
gwt ls                  List active worktrees
gwt sync <branch>       Update (git pull + deps)
gwt db <branch> reset   Reset the database
gwt docker <branch> up|down|logs|exec
gwt apache <branch> add|rm
gwt selftest            Check configuration
gwt help                Full help
```

For complete help: `gwt help`

---

## Troubleshooting

### Containers won't start

```bash
gwt docker feature/my-fix logs
# or
docker compose -f /path/to/worktree/docker-compose.yml logs
```

### Database not initialized

```bash
gwt db feature/my-fix reset
```

### Galette shows installation page

The `galette/config/config.inc.php` file may be missing or incorrect. Check:

```bash
cat /path/to/worktree/galette/config/config.inc.php
```

### Port already in use

Specify a port manually:

```bash
gwt add feature/my-fix --docker --port 8099
```

### Vendor or node_modules error (broken symlink)

```bash
rm /path/to/worktree/galette/vendor
rm /path/to/worktree/node_modules
gwt add feature/my-fix --no-db --no-apache
```

---

## Source Structure

```
galette/                    Main application
├── config/                 Configuration (not versioned except .dist)
├── data/                   Runtime data (logs, uploads, cache)
├── includes/               Routes and system files
├── lib/Galette/            PHP source code (PSR-4)
├── templates/              Twig templates
└── webroot/                Apache DocumentRoot
    ├── index.php           Entry point
    └── themes/             Compiled assets (generated by npm)

ui/                         Frontend sources
├── js/                     JavaScript source
└── semantic/               Fomantic UI theme

tests/                      PHPUnit tests
docker/                     Docker files for development
docs/contributing/          This documentation
```

See [AGENTS.md](../../AGENTS.md) for detailed instructions on development, testing, and code standards.
