# API Galette — État des travaux (feature/api)

Branche : `feature/api` → cible : `develop`

---

## Fait

### Infrastructure de base
- [x] **Bootstrap Slim 4** — `galette/webroot/api/index.php`
  - Chargement Galette, overrides DI (Login stateless), routes, error handler JSON
- [x] **AbstractApiController** — `galette/lib/Galette/Api/Controllers/AbstractApiController.php`
  - Injection `$zdb`, `$preferences`, `$i18n` via `#[Inject]`
  - Helpers : `getLogin()`, `checkPermission()`, `json()`, `forbidden()`, `notFound()`
- [x] **ApiResponseFormatter** — gestionnaire d'erreurs JSON global (Slim error handler)
- [x] **RateLimitMiddleware** — `galette/lib/Galette/Middleware/RateLimitMiddleware.php`

### Authentification et tokens
- [x] **Entité `ApiClient`** — `galette/lib/Galette/Entity/ApiClient.php`
  - Étend `AbstractEntity`, table `api_client`
  - `verifySecret()`, `setClientSecret()` (bcrypt), `isTrusted()`
- [x] **Entité `ApiToken`** — `galette/lib/Galette/Entity/ApiToken.php`
  - Étend `AbstractEntity`, table `api_tokens`
  - `revoke()`, `findValid()` — zéro SQL brut
- [x] **ApiTokenRepository** — `galette/lib/Galette/Api/Repository/ApiTokenRepository.php`
  - `createRefreshToken()` → délègue à `ApiToken::save()`
  - `verifyAndRotate()` → `ApiToken::findValid()` + `->revoke()`
  - `revokeAllForUser()`, `revokeAllForClient()` (bulk, Laminas)
- [x] **JwtMiddleware** — validation Bearer HS256, hydratation `api_login` sur la requête
  - Clé auto-générée dans `galette/config/api_secret.key`
  - Support tokens `user` et `client` (OAuth2)

### Middlewares additionnels
- [x] **RoleMiddleware**, **ScopeMiddleware**, **ValidationMiddleware**

### Single Action Controllers
- [x] **Auth** : `LoginAction`, `TokenAction` (client_credentials), `RefreshAction` (rotation)
- [x] **Members** : `ListMembersAction`, `GetMemberAction`, `CreateMemberAction`,
  `UpdateMemberAction`, `DeleteMemberAction`
- [x] **Contributions** : `ListContributionsAction`, `GetContributionAction`,
  `CreateContributionAction`
- [x] **Groups** : `ListGroupsAction`, `GetGroupAction`

### DTOs
- [x] `MemberDto::fromAdherent()` — 19 champs
- [x] `ContributionDto::fromContribution()` — 9 champs
- [x] `GroupDto::fromGroup()` — 4 champs + membres embarqués dans `GetGroupAction`

### Migration base de données
- [x] `patches/2026-03-31_api_tables.sql` — tables `api_client` et `api_tokens`

### Commandes console
- [x] `api:client:create <id> <name> [--secret] [--trusted]`
- [x] `api:client:list`
- [x] `api:client:revoke <id>`
- [x] Enregistrées dans `GaletteApplication::init()`

### Tests
- [x] `tests/Galette/Api/Dto/MemberDto.php` — mapping, toArray, readonly
- [x] `tests/Galette/Api/Actions/Auth/LoginAction.php` — 400/401/200
- [x] `tests/Galette/Api/Actions/Member/GetMemberAction.php` — staff/own/403/404
- [x] `tests/Galette/Api/Repository/ApiTokenRepository.php` — create/rotate/expire/revoke/client
- [x] `tests/Galette/Api/Middleware/ScopeMiddleware.php`
- [x] `tests/Galette/Console/Command/Api/ApiClientCreate.php`
- [x] `tests/Galette/Console/Command/Api/ApiClientList.php`
- [x] `tests/Galette/Console/Command/Api/ApiClientRevoke.php`

### Documentation
- [x] `docs/api/openapi.yaml` — spécification OpenAPI 3.1 complète (13 routes)
- [x] `docs/api/SETUP.md` — guide d'installation et de vérification pour les développeurs
- [x] `docs/api/TASKS.md` — ce fichier

---

## À faire

### Tests manquants (13 actions/DTOs sans couverture)
- [ ] `tests/Galette/Api/Actions/Auth/TokenAction.php` — 400/401/200 client_credentials
- [ ] `tests/Galette/Api/Actions/Auth/RefreshAction.php` — rotation, token expiré
- [ ] `tests/Galette/Api/Actions/Member/ListMembersAction.php` — pagination, droits
- [ ] `tests/Galette/Api/Actions/Member/CreateMemberAction.php` — 201/422/403
- [ ] `tests/Galette/Api/Actions/Member/UpdateMemberAction.php` — 200/404/422/403
- [ ] `tests/Galette/Api/Actions/Member/DeleteMemberAction.php` — 204/404/403
- [ ] `tests/Galette/Api/Actions/Contribution/ListContributionsAction.php`
- [ ] `tests/Galette/Api/Actions/Contribution/GetContributionAction.php`
- [ ] `tests/Galette/Api/Actions/Contribution/CreateContributionAction.php`
- [ ] `tests/Galette/Api/Actions/Group/ListGroupsAction.php`
- [ ] `tests/Galette/Api/Actions/Group/GetGroupAction.php`
- [ ] `tests/Galette/Api/Dto/ContributionDto.php`
- [ ] `tests/Galette/Api/Dto/GroupDto.php`

### Intégration ACLs
- [ ] Connecter les middlewares `RoleMiddleware` / `ScopeMiddleware` aux routes
  quand la branche `feature/acls` sera mergée dans `develop`
- [ ] Remplacer les vérifications manuelles `checkPermission()` par le système ACL unifié

### Gestion clients API (interface admin)
- [ ] Page d'administration Galette pour créer/lister/révoquer les clients OAuth2
  (actuellement : commandes console uniquement)

### Merge
- [ ] Passer la suite qualité complète (`phpcs`, `phpstan`, `php-cs-fixer`) sur l'ensemble
  des fichiers modifiés avant la PR
- [ ] Ouvrir la PR `feature/api` → `develop`

---

## Commits de la branche

| Hash | Message |
|------|---------|
| `49fc3bf` | ApiToken entity + console commands api:client:* |
| `5bc088a` | doc |
| `42a5d53` | Add tests, missing db schema |
| `7cddd1d` | Claude rework — Refactor API : Single Action Controllers, DTOs, qualité |
| `509fb80` | API client entity |
| `b12d562` | RateLimitMiddleware |
| `c3b00b3` | Galette API |
