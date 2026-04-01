# Environnement de développement Galette

Ce guide explique comment mettre en place un environnement de développement local isolé pour contribuer à Galette. Le système repose sur deux outils complémentaires :

- **[branchlet](https://github.com/raghavpillai/branchlet)** — gestion interactive des git worktrees (création, liste, suppression)
- **`gwt`** (Galette Worktree Tool) — configuration automatique de l'environnement Galette (DB, dépendances, Apache/Docker)

Deux modes sont disponibles :

| Mode | Prérequis | Idéal pour |
|------|-----------|------------|
| **Docker** (recommandé) | Git, Node.js, Docker ≥ 24, Docker Compose v2 | Contributeurs externes |
| **Natif** | httpd, PHP-FPM (remi), PostgreSQL/MySQL | Mainteneurs |

---

## Installation

### branchlet

```bash
npm install -g branchlet
```

### gwt

```bash
# Depuis le dépôt cloné
cp bin/gwt ~/bin/gwt
chmod +x ~/bin/gwt

# Vérifier que ~/bin est dans votre PATH
echo $PATH | grep -q "$HOME/bin" || echo 'export PATH="$HOME/bin:$PATH"' >> ~/.bashrc
```

Vérifier l'installation :

```bash
gwt selftest
```

---

## Mode Docker (contributeurs)

### Prérequis

- [Git](https://git-scm.com/)
- [Node.js](https://nodejs.org/) (pour branchlet)
- [Docker ≥ 24](https://docs.docker.com/get-docker/)
- Docker Compose v2 (`docker compose version`)

### Configuration du chemin des worktrees (une seule fois)

Par défaut, branchlet crée les worktrees dans `../<repo>-worktrees/core/<branche>/` par rapport au dépôt cloné. Pour personnaliser :

```bash
branchlet settings
# → worktreePathTemplate → saisir le chemin souhaité
# Exemple : /home/utilisateur/dev/galette-worktrees/core/$BRANCH_NAME
```

### Workflow typique

#### 1. Préparer un environnement pour une branche

```bash
# Branche develop (base du développement)
gwt add develop --docker

# Branche de feature
# Interface interactive (recommandé)
branchlet create
# → sélectionner ou saisir la branche
# → branchlet crée le worktree git et lance bin/gwt-setup
# → gwt-setup demande : version PHP et mode Docker
```

`bin/gwt-setup` effectue automatiquement :
- Génération de la configuration (`config.inc.php`, `behavior.inc.php`)
- Installation des dépendances PHP et JS
- Démarrage des conteneurs (PHP-FPM, Apache, PostgreSQL)
- Installation du schéma de base de données

Vous pouvez aussi appeler `gwt add` directement sans passer par branchlet :

```bash
gwt add feature/mon-correctif --docker
gwt add feature/mon-correctif --docker --php 85
```

#### 2. Lister les worktrees actifs

```bash
gwt ls
# ou via branchlet :
branchlet list
```

```
TYPE     BRANCH                         PHP    MODE     URL
────     ──────────────────────────────  ────   ──────   ───────────────────────────────
core     develop                        84     docker   http://localhost:8080/
core     feature/mon-correctif          85     docker   http://localhost:8081/
```

#### 3. Développer

Les fichiers sources sont montés directement dans les conteneurs — toute modification est immédiatement visible sans rebuild.

```bash
# Voir les logs en temps réel
gwt docker feature/mon-correctif logs

# Exécuter une commande dans le conteneur
gwt docker feature/mon-correctif exec -- bin/console galette:install

# Ouvrir un shell PHP
gwt docker feature/mon-correctif exec -- bash
```

#### 4. Mettre à jour depuis develop

```bash
gwt sync feature/mon-correctif
```

Cela effectue `git pull --ff-only` et met à jour automatiquement les dépendances si `composer.lock` ou `package-lock.json` ont changé.

#### 5. Réinitialiser la base de données

```bash
gwt db feature/mon-correctif reset
```

#### 6. Nettoyer en fin de feature

```bash
gwt rm feature/mon-correctif
```

Supprime le worktree, les conteneurs Docker et les données de la DB.

---

## Utiliser des plugins

Les plugins se chargent depuis `galette/plugins/` dans le worktree. Pour en activer un :

```bash
# Exemple : activer le plugin Paypal dans le worktree feature/mon-correctif
WT=/chemin/vers/worktrees/core/feature/mon-correctif
ln -s /chemin/vers/galette-paypal "${WT}/galette/plugins/Paypal"
```

Puis recharger les conteneurs :

```bash
gwt docker feature/mon-correctif down
gwt docker feature/mon-correctif up
```

---

## Mode natif (mainteneurs)

### Prérequis supplémentaires

- `httpd` avec `mod_proxy_fcgi` et `mod_rewrite`
- PHP-FPM via les paquets remi (`php84-php-fpm`, etc.)
- PostgreSQL ou MySQL en local
- Droits sudo pour recharger Apache (voir ci-dessous)

### Configuration `~/.gwt.conf`

```bash
# ~/.gwt.conf  (mode 600 — contient le mot de passe DB)
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
GWT_DB_PASS=motdepasse
GWT_DB_HOST=localhost
GWT_DEVELOP_WORKTREE=/var/www/projects/galette/core/develop
```

```bash
chmod 600 ~/.gwt.conf
```

### Configuration Apache (une seule fois)

Ajouter dans votre vhost avant `</VirtualHost>` :

```apache
IncludeOptional /etc/httpd/conf.d/galette-worktrees.d/*.conf
```

Créer le répertoire :

```bash
sudo mkdir -p /etc/httpd/conf.d/galette-worktrees.d
```

Optionnel — accès sudo sans mot de passe pour la gestion des snippets Apache :

```
# /etc/sudoers.d/gwt
trasher ALL=(root) NOPASSWD: /usr/bin/tee /etc/httpd/conf.d/galette-worktrees.d/*
trasher ALL=(root) NOPASSWD: /usr/bin/rm /etc/httpd/conf.d/galette-worktrees.d/*
trasher ALL=(root) NOPASSWD: /usr/bin/systemctl reload httpd
trasher ALL=(root) NOPASSWD: /usr/bin/mkdir -p /etc/httpd/conf.d/galette-worktrees.d
```

### Workflow natif

```bash
# Créer un worktree avec PHP 8.4
gwt add feature/mon-correctif --php 84

# URL : http://galette.localhost/feature-mon-correctif/

# Ajouter uniquement le snippet Apache pour un worktree existant
gwt apache feature/mon-correctif add --php 84

# Supprimer
gwt rm feature/mon-correctif
```

---

## Référence des commandes

```
gwt add <branch>        Créer un environnement complet
gwt rm  <branch>        Supprimer un environnement
gwt ls                  Lister les worktrees actifs
gwt sync <branch>       Mettre à jour (git pull + deps)
gwt db <branch> reset   Réinitialiser la base de données
gwt docker <branch> up|down|logs|exec
gwt apache <branch> add|rm
gwt selftest            Vérifier la configuration
gwt help                Aide complète
```

Pour l'aide complète : `gwt help`

---

## Dépannage

### Les conteneurs ne démarrent pas

```bash
gwt docker feature/mon-correctif logs
# ou
docker compose -f /chemin/worktree/docker-compose.yml logs
```

### La base de données n'est pas initialisée

```bash
gwt db feature/mon-correctif reset
```

### Galette affiche la page d'installation

Le fichier `galette/config/config.inc.php` est peut-être absent ou incorrect. Vérifier :

```bash
cat /chemin/worktree/galette/config/config.inc.php
```

### Port déjà utilisé

Spécifier un port manuellement :

```bash
gwt add feature/mon-correctif --docker --port 8099
```

### Vendor ou node_modules en erreur (symlink cassé)

```bash
rm /chemin/worktree/galette/vendor
rm /chemin/worktree/node_modules
gwt add feature/mon-correctif --no-db --no-apache
```

---

## Structure des sources

```
galette/                    Application principale
├── config/                 Configuration (non versionné sauf .dist)
├── data/                   Données runtime (logs, uploads, cache)
├── includes/               Routes et fichiers système
├── lib/Galette/            Code source PHP (PSR-4)
├── templates/              Templates Twig
└── webroot/                DocumentRoot Apache
    ├── index.php           Point d'entrée
    └── themes/             Assets compilés (généré par npm)

ui/                         Sources frontend
├── js/                     JavaScript source
└── semantic/               Thème Fomantic UI

tests/                      Tests PHPUnit
docker/                     Fichiers Docker pour le développement
docs/contributing/          Cette documentation
```

Voir [AGENTS.md](../../AGENTS.md) pour les instructions détaillées sur le développement, les tests et les standards de code.
