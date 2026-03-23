# Correction - Utilisation de CheckModules

**Date:** 2026-03-23  
**Issue:** `check_refactored.php` et `CheckStep.php` utilisaient une méthode inexistante `getModules()`

## Problème identifié

Les fichiers suivants appelaient `$cm->getModules()` qui n'existe pas dans `CheckModules` :
- `galette/install/steps/check_refactored.php`
- `galette/lib/Galette/Core/Installation/Step/CheckStep.php`

## Méthodes disponibles dans CheckModules

D'après `galette/lib/Galette/Core/CheckModules.php` :

```php
class CheckModules
{
    // Méthodes disponibles :
    public function getGoods(): array;      // Modules présents
    public function getMissings(): array;   // Modules manquants (requis)
    public function getShoulds(): array;    // Modules recommandés
    public function isValid(): bool;        // Validation globale
    public function toHtml(): string;       // Rendu HTML
}
```

Référence : Commande console `galette:checks` dans `galette/lib/Galette/Console/Command/Checks.php`

## Corrections appliquées

### 1. check_refactored.php

**Avant :**
```php
$modules_list = [];
foreach ($cm->getModules() as $module => $loaded) {
    $modules_list[] = [
        'message' => $module,
        'res' => $loaded
    ];
}
```

**Après :**
```php
$modules_list = [];

// Add missing modules (errors)
foreach ($cm->getMissings() as $module) {
    $modules_list[] = [
        'message' => $module,
        'res' => false
    ];
}

// Add good modules (success)
foreach ($cm->getGoods() as $module) {
    $modules_list[] = [
        'message' => $module,
        'res' => true
    ];
}

// Add optional/recommended modules (warnings)
foreach ($cm->getShoulds() as $module) {
    $modules_list[] = [
        'message' => $module . ' ' . _T("(recommended)"),
        'res' => true
    ];
}
```

### 2. CheckStep.php

**Avant :**
```php
private function checkModules(): array
{
    $cm = new CheckModules();
    $passed = $cm->isValid();

    return [
        'passed' => $passed,
        'message' => _T("PHP Modules"),
        'details' => $cm->getModules()  // ❌ N'existe pas
    ];
}
```

**Après :**
```php
private function checkModules(): array
{
    $cm = new CheckModules();
    $passed = $cm->isValid();

    return [
        'passed' => $passed,
        'message' => _T("PHP Modules"),
        'details' => [
            'good' => $cm->getGoods(),
            'missing' => $cm->getMissings(),
            'should' => $cm->getShoulds()
        ]
    ];
}
```

## Validation

- ✅ Syntaxe PHP correcte
- ✅ Tests unitaires passent (19/19)
- ✅ Code style conforme PSR-12
- ✅ Cohérent avec l'utilisation dans `galette:checks`

## Bénéfices de la nouvelle approche

1. **Plus d'informations** : On distingue maintenant les modules manquants, présents et recommandés
2. **Meilleur affichage** : Les modules recommandés sont marqués comme tels
3. **Cohérence** : Même logique que la commande console

## Fichiers modifiés

1. `galette/install/steps/check_refactored.php`
2. `galette/lib/Galette/Core/Installation/Step/CheckStep.php`

## Notes

La classe `CheckModules` fait la distinction entre :
- **Modules requis** (required) : doivent être présents pour que Galette fonctionne
- **Modules recommandés** (should) : optionnels mais recommandés

La méthode `isValid()` retourne `true` uniquement si tous les modules requis sont présents.

