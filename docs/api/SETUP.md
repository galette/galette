# API Galette — Mise en place et vérification

Guide destiné aux développeurs qui rejoignent la branche `feature/api`.

---

## Prérequis

| Outil | Version minimale |
|-------|-----------------|
| PHP | 8.2 |
| Composer | 2.x |
| MySQL | 5.7 / 8.x |
| PostgreSQL | 14+ (alternatif) |

---

## 1. Installation des dépendances

```bash
cd galette/
composer install
```

Si la résolution de `firebase/php-jwt` échoue à cause de conflits de dépendances
(fréquent avec PHP 8.4) :

```bash
composer update firebase/php-jwt --with-all-dependencies --ignore-platform-reqs
```

---

## 2. Migration de la base de données

L'API requiert deux tables supplémentaires (`api_client`, `api_tokens`) absentes du
schéma Galette standard.

Le script se trouve dans `patches/2026-03-31_api_tables.sql`.
Remplacez `{PREFIX}` par le préfixe de votre installation (par défaut `galette_`) :

```bash
sed 's/{PREFIX}/galette_/g' patches/2026-03-31_api_tables.sql | \
  mysql -u <user> -p <database>
```

Pour PostgreSQL, adaptez les types (`INT UNSIGNED` → `INTEGER`, `DATETIME` → `TIMESTAMP`,
`BOOLEAN DEFAULT FALSE` reste identique) avant d'exécuter le script.

### Tables créées

**`galette_api_client`** — clients OAuth2 (flux `client_credentials`)

| Colonne | Type | Description |
|---------|------|-------------|
| `client_id` | VARCHAR(80) PK | Identifiant du client |
| `client_secret_hash` | VARCHAR(255) | Hash bcrypt du secret |
| `client_name` | VARCHAR(128) | Nom affiché |
| `redirect_uri` | TEXT | URI de redirection (optionnel) |
| `is_trusted` | BOOLEAN | Accès admin-level si vrai |
| `created_at` | DATETIME | Date de création |

**`galette_api_tokens`** — refresh tokens

| Colonne | Type | Description |
|---------|------|-------------|
| `id` | INT PK | Auto-increment |
| `id_adh` | INT | Adhérent propriétaire (null pour client) |
| `client_id` | VARCHAR(80) | Client OAuth2 (null pour user) |
| `token_hash` | VARCHAR(64) UNIQUE | SHA-256 du token brut |
| `allowed_scope` | TEXT | Scopes accordés (espace-séparé) |
| `created_at` | DATETIME | Date de création |
| `expires_at` | DATETIME | Date d'expiration |
| `is_revoked` | BOOLEAN | Révoqué après rotation |

---

## 3. Clé secrète JWT

Le middleware JWT génère automatiquement une clé HMAC HS256 au premier appel
et la stocke dans `galette/config/api_secret.key` (chmod 600).

**Aucune action manuelle requise.** En environnement de développement partagé,
vous pouvez copier le fichier entre machines pour partager la même clé :

```bash
# Exporter depuis une machine
cat galette/config/api_secret.key

# Importer sur une autre (même valeur, même chemin)
echo -n '<valeur>' > galette/config/api_secret.key
chmod 600 galette/config/api_secret.key
```

> **Important** : ne versionnez jamais ce fichier. Il est ignoré par `.gitignore`.

---

## 4. Point d'entrée de l'API

L'API est servie par `galette/webroot/api/index.php`.

Avec le serveur intégré PHP (développement) :

```bash
php -S localhost:8080 -t galette/webroot/api/
```

Vérification rapide :

```bash
curl -s http://localhost:8080/api/v1/auth/login \
  -H 'Content-Type: application/json' \
  -d '{}' | python3 -m json.tool
# Attendu : {"error":"login and password are required"}  HTTP 400
```

---

## 5. Smoke tests manuels

### 5.1 Connexion utilisateur

```bash
# Remplacez login/password par un compte Galette existant
curl -s -X POST http://localhost:8080/api/v1/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"login":"admin","password":"admin"}' | python3 -m json.tool
```

Réponse attendue :

```json
{
  "access_token": "eyJ...",
  "token_type": "Bearer",
  "expires_in": 900,
  "refresh_token": "a3f1b2..."
}
```

Stockez le token dans une variable :

```bash
TOKEN=$(curl -s -X POST http://localhost:8080/api/v1/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"login":"admin","password":"admin"}' | python3 -c "import sys,json; print(json.load(sys.stdin)['access_token'])")
```

### 5.2 Liste des adhérents

```bash
curl -s http://localhost:8080/api/v1/members \
  -H "Authorization: Bearer $TOKEN" | python3 -m json.tool
```

### 5.3 Rotation de refresh token

```bash
REFRESH=$(curl -s -X POST http://localhost:8080/api/v1/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"login":"admin","password":"admin"}' | python3 -c "import sys,json; print(json.load(sys.stdin)['refresh_token'])")

curl -s -X POST http://localhost:8080/api/v1/auth/refresh \
  -H 'Content-Type: application/json' \
  -d "{\"refresh_token\":\"$REFRESH\"}" | python3 -m json.tool
# Attendu : nouveau couple access_token + refresh_token
```

### 5.4 Flux OAuth2 client_credentials

Insérez d'abord un client de test :

```sql
INSERT INTO galette_api_client
  (client_id, client_secret_hash, client_name, is_trusted, created_at)
VALUES
  ('test_client',
   '$2y$10$...', -- password_hash('mysecret', PASSWORD_BCRYPT)
   'Client de test',
   TRUE,
   NOW());
```

Ou en PHP :

```php
php -r "echo password_hash('mysecret', PASSWORD_BCRYPT) . PHP_EOL;"
# Copiez le hash dans la requête INSERT ci-dessus
```

Puis :

```bash
curl -s -X POST http://localhost:8080/api/v1/auth/token \
  -H 'Content-Type: application/json' \
  -d '{"grant_type":"client_credentials","client_id":"test_client","client_secret":"mysecret"}' \
  | python3 -m json.tool
```

---

## 6. Tests automatisés

### 6.1 Configuration de la base de tests

Créez `tests/config/mysql/local_config.inc.php` (ignoré par git) :

```php
<?php
define('HOST_DB', '127.0.0.1');
define('PORT_DB', '3306');
define('USER_DB', 'galette_tests');
define('PWD_DB', 'votre_mot_de_passe');
define('NAME_DB', 'galette_tests');
define('PREFIX_DB', 'galette_');
```

La base `galette_tests` doit exister et contenir le schéma Galette complet.
Pour les tests API (`Repository/ApiTokenRepository`), les tables `galette_api_client`
et `galette_api_tokens` doivent également être présentes (voir §2).
Les suites concernées appellent `markTestSkipped()` automatiquement si la table
est absente — elles ne font pas échouer le pipeline.

### 6.2 Exécution des tests API

```bash
# Suite complète API (MySQL)
GALETTE_TESTS=1 DB=mysql \
  galette/vendor/bin/phpunit \
  --test-suffix=.php \
  tests/Galette/Api/

# Fichier unique
GALETTE_TESTS=1 DB=mysql \
  galette/vendor/bin/phpunit \
  --test-suffix=.php \
  tests/Galette/Api/Actions/Auth/LoginAction.php
```

> **Note** : le préfixe `GALETTE_TESTS=1` est obligatoire — sans lui,
> PHPUnit tente de se connecter à la base de travail et peut écraser des données.

### 6.3 Contrôle qualité

Ces commandes doivent passer sans erreur avant tout commit sur `feature/api`.

```bash
# Style de code
galette/vendor/bin/php-cs-fixer fix galette/lib/Galette/Api/ --dry-run --diff
galette/vendor/bin/phpcs galette/lib/Galette/Api/ tests/Galette/Api/

# Analyse statique (niveau 6)
php -d memory_limit=512M \
  galette/vendor/bin/phpstan analyse \
  galette/lib/Galette/Api/ \
  galette/lib/Galette/Entity/ApiClient.php \
  tests/Galette/Api/
```

---

## 7. Architecture rapide

```
galette/lib/Galette/Api/
├── Actions/                    Single Action Controllers (un fichier = une route)
│   ├── Auth/                   Login, Token (OAuth2), Refresh
│   ├── Member/                 List, Get, Create, Update, Delete
│   ├── Contribution/           List, Get, Create
│   └── Group/                  List, Get
├── Controllers/
│   └── AbstractApiController   Classe de base (zdb, preferences, i18n, helpers)
├── Dto/                        Sérialisation JSON (MemberDto, ContributionDto, GroupDto)
├── Middleware/
│   └── JwtMiddleware           Valide le Bearer token, hydrate `api_login` sur la requête
├── Repository/
│   └── ApiTokenRepository      CRUD des refresh tokens
└── ApiResponseFormatter        Gestionnaire d'erreurs JSON global (Slim error handler)

galette/Entity/ApiClient.php    Entité OAuth2 client (table api_client)
galette/webroot/api/index.php   Bootstrap Slim + déclaration des routes
patches/2026-03-31_api_tables.sql  DDL des tables API
docs/api/openapi.yaml           Spécification OpenAPI 3.1
```

### Ajouter une route

1. Créer `galette/lib/Galette/Api/Actions/<Resource>/<Verb>Action.php`
   qui étend `AbstractApiController` et implémente `__invoke()`.
2. Ajouter le DTO correspondant dans `galette/lib/Galette/Api/Dto/` si besoin.
3. Déclarer la route dans `galette/webroot/api/index.php`.
4. Écrire le test dans `tests/Galette/Api/Actions/<Resource>/<Verb>Action.php`.
5. Mettre à jour `docs/api/openapi.yaml`.

---

## 8. Comportement des tokens

| Durée | Valeur |
|-------|--------|
| Access token (JWT) | 15 minutes (`exp = iat + 900`) |
| Refresh token | 30 jours |

La rotation est stricte : chaque appel à `/auth/refresh` **révoque** le token utilisé
et en émet un nouveau. Rejouer un ancien refresh token retourne HTTP 401.

Les tokens clients OAuth2 (`/auth/token`) n'ont pas de refresh token — le client
doit se ré-authentifier après expiration.
