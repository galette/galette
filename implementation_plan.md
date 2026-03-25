# Plan d'action RBAC — Étapes suivantes

## Contexte

Le système RBAC est en place : [AccessControl](file:///home/trasher/.gemini/antigravity/repositories/galette/galette/lib/Galette/Core/AccessControl.php#34-216), [VoterInterface](file:///home/trasher/.gemini/antigravity/repositories/galette/galette/lib/Galette/Interfaces/VoterInterface.php#33-50), [GroupVoter](file:///home/trasher/.gemini/antigravity/repositories/galette/galette/lib/Galette/Core/Voters/GroupVoter.php#37-94), [SubscriptionVoter](file:///home/trasher/.gemini/antigravity/repositories/galette/galette/lib/Galette/Core/Voters/SubscriptionVoter.php#34-54), [ApiRbacMiddleware](file:///home/trasher/.gemini/antigravity/repositories/galette/galette/lib/Galette/Middleware/ApiRbacMiddleware.php#41-128), matrice d'admin, migration SQL, et tests de base.  
L'objectif est maintenant d'intégrer ce système dans le code existant de Galette, étape par étape.

---

## Étape 1 — Qualité de code sur les fichiers RBAC existants

Exécuter les outils de qualité (PHPStan, PHP-CS-Fixer, PHPCS) sur tous les fichiers RBAC et corriger les problèmes.

**Fichiers concernés :**
- [galette/lib/Galette/Core/AccessControl.php](file:///home/trasher/.gemini/antigravity/repositories/galette/galette/lib/Galette/Core/AccessControl.php)
- [galette/lib/Galette/Core/Voters/GroupVoter.php](file:///home/trasher/.gemini/antigravity/repositories/galette/galette/lib/Galette/Core/Voters/GroupVoter.php)
- [galette/lib/Galette/Core/Voters/SubscriptionVoter.php](file:///home/trasher/.gemini/antigravity/repositories/galette/galette/lib/Galette/Core/Voters/SubscriptionVoter.php)
- [galette/lib/Galette/Interfaces/VoterInterface.php](file:///home/trasher/.gemini/antigravity/repositories/galette/galette/lib/Galette/Interfaces/VoterInterface.php)
- [galette/lib/Galette/Middleware/ApiRbacMiddleware.php](file:///home/trasher/.gemini/antigravity/repositories/galette/galette/lib/Galette/Middleware/ApiRbacMiddleware.php)
- [galette/lib/Galette/Controllers/RbacController.php](file:///home/trasher/.gemini/antigravity/repositories/galette/galette/lib/Galette/Controllers/RbacController.php)
- [tests/Galette/Core/AccessControlTest.php](file:///home/trasher/.gemini/antigravity/repositories/galette/tests/Galette/Core/AccessControlTest.php)

**Commit :** `Fix code quality issues on RBAC components`

---

## Étape 2 — Migration SQL propre (MySQL + PostgreSQL)

Réécrire la migration [patches/2026-03-22_rbac_migration.sql](file:///home/trasher/.gemini/antigravity/repositories/galette/patches/2026-03-22_rbac_migration.sql) pour supporter **MySQL et PostgreSQL**.  
Actuellement le fichier est MySQL-only (`ENGINE=InnoDB`, `auto_increment`, `INSERT IGNORE`).

**Fichiers concernés :**
- [MODIFY] [patches/2026-03-22_rbac_migration.sql](file:///home/trasher/.gemini/antigravity/repositories/galette/patches/2026-03-22_rbac_migration.sql) → version MySQL
- [NEW] `patches/2026-03-22_rbac_migration_pgsql.sql` → version PostgreSQL

**Commit :** `Add PostgreSQL-compatible RBAC migration script`

---

## Étape 3 — Intégration de la migration dans le système d'install/update

Enregistrer la migration RBAC dans le mécanisme d'installation/mise à jour de Galette (`bin/console galette:install`) pour qu'elle soit exécutée automatiquement.

**Fichiers concernés :**
- Classes d'installation/mise à jour (à identifier via [galette/lib/Galette/Core/Install.php](file:///home/trasher/.gemini/antigravity/repositories/galette/galette/lib/Galette/Core/Install.php) ou équivalent)
- Le fichier de version ou de patches

**Commit :** `Register RBAC migration in install/update pipeline`

---

## Étape 4 — Script de migration des données existantes

Créer un script qui attribue automatiquement les rôles RBAC aux membres existants, basé sur leur statut actuel :
- `admin_adh = true` → rôle « Administrateur »
- `statut_id ≤ X` (staff) → rôle « Secrétaire » ou équivalent
- Responsables de groupe → rôle « Responsable de groupe »

**Fichiers concernés :**
- [NEW] Script de migration de données (dans `patches/` ou via une commande Console)

**Commit :** `Add data migration script for existing member roles to RBAC`

---

## Étape 5 — Bridge Authenticate middleware → RBAC

Modifier le middleware [Authenticate](file:///home/trasher/.gemini/antigravity/repositories/galette/galette/lib/Galette/Middleware/Authenticate.php#44-194) pour utiliser `AccessControl::can()` **en complément** du système d'ACL actuel, sans casser l'existant.  
Approche : ajouter un check RBAC optionnel. Si une route a un attribut `rbac_permission`, utiliser `AccessControl::can()` au lieu du switch legacy.

**Fichiers concernés :**
- [MODIFY] [galette/lib/Galette/Middleware/Authenticate.php](file:///home/trasher/.gemini/antigravity/repositories/galette/galette/lib/Galette/Middleware/Authenticate.php)

**Commit :** `Add RBAC permission check to Authenticate middleware`

---

## Étape 6 — Refactoring progressif des contrôleurs (Membres)

Remplacer les vérifications legacy (`$this->login->isAdmin()`, `$this->login->isStaff()`) dans `MembersController` par des appels à `$this->accessControl->can('member:...')`.

**Fichiers concernés :**
- [MODIFY] [galette/lib/Galette/Controllers/Crud/MembersController.php](file:///home/trasher/.gemini/antigravity/repositories/galette/galette/lib/Galette/Controllers/Crud/MembersController.php)
- [MODIFY] Routes associées (ajout d'attributs `rbac_permission`)

**Commit :** `Refactor MembersController to use RBAC permissions`

---

## Étape 7 — Refactoring progressif des contrôleurs (Contributions)

Idem pour les cotisations/transactions.

**Fichiers concernés :**
- [MODIFY] [galette/lib/Galette/Controllers/Crud/ContributionsController.php](file:///home/trasher/.gemini/antigravity/repositories/galette/galette/lib/Galette/Controllers/Crud/ContributionsController.php)
- [MODIFY] [galette/lib/Galette/Controllers/Crud/TransactionsController.php](file:///home/trasher/.gemini/antigravity/repositories/galette/galette/lib/Galette/Controllers/Crud/TransactionsController.php)

**Commit :** `Refactor ContributionsController and TransactionsController to use RBAC permissions`

---

## Étape 8 — Refactoring progressif des contrôleurs (Groupes)

Idem pour les groupes.

**Fichiers concernés :**
- [MODIFY] [galette/lib/Galette/Controllers/Crud/GroupsController.php](file:///home/trasher/.gemini/antigravity/repositories/galette/galette/lib/Galette/Controllers/Crud/GroupsController.php)

**Commit :** `Refactor GroupsController to use RBAC permissions`

---

## Étape 9 — Refactoring progressif des contrôleurs (Configuration)

Idem pour les pages de configuration et préférences.

**Fichiers concernés :**
- [MODIFY] [galette/lib/Galette/Controllers/GaletteController.php](file:///home/trasher/.gemini/antigravity/repositories/galette/galette/lib/Galette/Controllers/GaletteController.php)
- [MODIFY] Contrôleurs de configuration (à identifier)

**Commit :** `Refactor configuration controllers to use RBAC permissions`

---

## Étape 10 — UI de gestion des rôles et assignation

Créer l'interface pour :
- Créer/modifier/supprimer des rôles
- Assigner des rôles aux membres depuis la fiche adhérent

**Fichiers concernés :**
- [NEW] `galette/lib/Galette/Controllers/RolesController.php`
- [NEW] `galette/templates/default/pages/roles_list.html.twig`
- [MODIFY] `galette/templates/default/pages/member.html.twig` (section rôles)

**Commit :** `Add role management UI and member role assignment`

---

## Étape 11 — Intégration JWT complète

Ajouter `firebase/php-jwt` et implémenter le vrai encodage/décodage JWT dans `AuthController::apiLogin()` et `ApiRbacMiddleware::validateToken()`.

**Fichiers concernés :**
- [MODIFY] [composer.json](file:///home/trasher/.gemini/antigravity/repositories/galette/composer.json) (ajout de `firebase/php-jwt`)
- [MODIFY] [galette/lib/Galette/Controllers/AuthController.php](file:///home/trasher/.gemini/antigravity/repositories/galette/galette/lib/Galette/Controllers/AuthController.php)
- [MODIFY] [galette/lib/Galette/Middleware/ApiRbacMiddleware.php](file:///home/trasher/.gemini/antigravity/repositories/galette/galette/lib/Galette/Middleware/ApiRbacMiddleware.php)

**Commit :** `Implement JWT encoding/decoding with firebase/php-jwt`

---

## Étape 12 — Tests complets et suppression du système legacy

Ajouter des tests pour chaque contrôleur refactorisé, valider la non-régression, puis supprimer le système legacy ([core_acls.php](file:///home/trasher/.gemini/antigravity/repositories/galette/galette/includes/core_acls.php), switch dans [Authenticate](file:///home/trasher/.gemini/antigravity/repositories/galette/galette/lib/Galette/Middleware/Authenticate.php#44-194)).

**Fichiers concernés :**
- [NEW/MODIFY] Tests dans `tests/Galette/`
- [DELETE] `galette/includes/core_acls.php` (quand tout est migré)
- [MODIFY] `galette/lib/Galette/Middleware/Authenticate.php` (suppression du switch)

**Commit :** `Remove legacy ACL system, fully migrate to RBAC`

---

## Plan de vérification

### Tests automatisés
```bash
# Tests RBAC existants
DB=mysql galette/vendor/bin/phpunit --test-suffix=.php tests/Galette/Core/AccessControlTest.php

# Suite complète (non-régression)
DB=mysql galette/vendor/bin/phpunit --test-suffix=.php tests/Galette/

# Qualité de code
vendor/bin/php-cs-fixer fix --dry-run --diff
vendor/bin/phpcs
vendor/bin/phpstan analyse
```

### Vérification manuelle
- Après chaque étape de refactoring (6-9), vérifier dans le navigateur que les accès sont identiques à avant pour chaque profil (admin, staff, group manager, membre simple).
