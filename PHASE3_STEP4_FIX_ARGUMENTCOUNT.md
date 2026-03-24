# Correction : ArgumentCountError dans orchestrator.php

**Date :** 2026-03-24  
**Status :** ✅ CORRIGÉ

## Problème détecté

```
PHP Fatal error:  Uncaught ArgumentCountError: Too few arguments to function 
Galette\Core\Installation\AbstractStep::__construct(), 0 passed in 
/var/www/html/private/galette.git/galette/install/orchestrator.php on line 57 
and exactly 1 expected in 
/var/www/html/private/galette.git/galette/lib/Galette/Core/Installation/AbstractStep.php:43
```

## Cause

Dans `orchestrator.php`, ligne 57, les Steps étaient instanciés sans argument :

```php
$step = new $stepClassName();  // ❌ ERREUR : 0 arguments
```

Mais le constructeur `AbstractStep::__construct()` attend **exactement 1 argument** de type `Install` :

```php
public function __construct(protected Install $install)
```

## Correction appliquée

**Fichier :** `galette/install/orchestrator.php` ligne 57

**Avant :**
```php
$step = new $stepClassName();
$result = $step->execute($data);
```

**Après :**
```php
$step = new $stepClassName($install);
$result = $step->execute($data);
```

## Tests de validation

### 1. Test d'instanciation automatisé ✅

Créé `galette/install/test_steps.php` pour tester l'instanciation de tous les Steps.

**Résultats :**
```
Testing CheckStep (Galette\Core\Installation\Step\CheckStep)...
  ✓ Class exists
  ✓ Can instantiate with Install parameter
  ✓ execute() method exists
  ✓ execute() is callable
  ✓ execute() signature is correct
  ✅ ALL TESTS PASSED for CheckStep

Testing DatabaseCheckStep...
  ✅ ALL TESTS PASSED for DatabaseCheckStep

Testing DatabaseInstallStep...
  ✅ ALL TESTS PASSED for DatabaseInstallStep

========================================
✅ ALL TESTS PASSED
========================================
```

### 2. Vérification syntaxe PHP ✅

```bash
$ php -l galette/install/orchestrator.php
No syntax errors detected
```

### 3. Script de debug amélioré ✅

Ajouté dans `debug_orchestrator.php` :

```php
// Test Step instantiation
if ($className !== null && class_exists($className)) {
    try {
        debug_log("  - Testing Step instantiation...");
        $testStep = new $className($install);
        debug_log("  ✓ Step instantiation successful");
        
        if (method_exists($testStep, 'execute')) {
            debug_log("  ✓ execute() method exists");
        } else {
            debug_log("  ✗ execute() method NOT found");
        }
    } catch (\ArgumentCountError $e) {
        debug_log("  ✗ ArgumentCountError: " . $e->getMessage());
        debug_log("  → Step constructor requires different arguments");
    } catch (\TypeError $e) {
        debug_log("  ✗ TypeError: " . $e->getMessage());
    } catch (\Throwable $e) {
        debug_log("  ✗ Exception: " . get_class($e) . ": " . $e->getMessage());
    }
}
```

## Fichiers modifiés

### Corrigés
1. ✅ `galette/install/orchestrator.php` - Ajout du paramètre `$install`

### Améliorés
2. ✅ `galette/install/debug_orchestrator.php` - Détection ArgumentCountError

### Créés
3. ✅ `galette/install/test_steps.php` - Test automatisé d'instanciation

## Prochaines actions

1. **Tester dans le navigateur** ✅ PRÊT
   ```
   http://galette.localhost/installer.php?raz
   ```

2. **Vérifier l'auto-avancement** - Devrait maintenant fonctionner sans erreur 500

3. **Vérifier les logs** si nécessaire :
   ```bash
   tail -f galette/data/logs/installer_debug.log
   ```

## Checklist de vérification ajoutée

Pour prévenir ce genre d'erreurs à l'avenir, le système de debug détecte maintenant :

- ✅ **ArgumentCountError** - Mauvais nombre d'arguments au constructeur
- ✅ **TypeError** - Mauvais type d'arguments
- ✅ **Class existence** - Classe non trouvée
- ✅ **Method existence** - Méthode execute() manquante
- ✅ **Method callable** - Méthode non appelable
- ✅ **Signature validation** - Vérification des paramètres

## Statut

✅ **PROBLÈME RÉSOLU**

L'erreur ArgumentCountError est corrigée. Le test automatisé valide que tous les Steps peuvent être correctement instanciés avec le paramètre `$install`.

---

**Prochaine étape :** Test navigateur pour voir l'auto-avancement en action ! 🚀

