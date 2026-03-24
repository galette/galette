# Guide d'activation du mode debug pour l'installateur

## Activation rapide

Pour activer le mode debug, ajoutez cette ligne au début de `installer.php` (après les includes) :

```php
require_once __DIR__ . '/../install/debug_orchestrator.php';
```

**Position exacte :** Après cette ligne :
```php
require_once __DIR__ . '/../install/orchestrator.php';
```

## Emplacement du fichier de log

Le debug écrit dans :
```
galette/data/logs/installer_debug.log
```

## Visualiser les logs en temps réel

```bash
# Suivre les logs
tail -f galette/data/logs/installer_debug.log

# Voir les dernières lignes
tail -n 50 galette/data/logs/installer_debug.log

# Vider les logs avant test
> galette/data/logs/installer_debug.log
```

## Exemple de sortie

Quand tout fonctionne :
```
[2026-03-24 10:30:15] ========== INSTALLER DEBUG START ==========
[2026-03-24 10:30:15] Request URI: /installer.php?raz
[2026-03-24 10:30:15] Request Method: GET
[2026-03-24 10:30:15] POST data: []
[2026-03-24 10:30:15] GET data: {"raz":""}
[2026-03-24 10:30:15] ✓ Orchestrator loaded
[2026-03-24 10:30:15] Current step check:
[2026-03-24 10:30:15]   - isCheckStep: YES
[2026-03-24 10:30:15]   - isDbCheckStep: NO
[2026-03-24 10:30:15]   - isDbinstallStep: NO
[2026-03-24 10:30:15]   - shouldUseNewSystem: YES
[2026-03-24 10:30:15]   - Step class: Galette\Core\Installation\Step\CheckStep
[2026-03-24 10:30:15]   - Testing Step instantiation...
[2026-03-24 10:30:15]   ✓ Step instantiation successful
[2026-03-24 10:30:15]   ✓ execute() method exists
[2026-03-24 10:30:15] StepResult:
[2026-03-24 10:30:15]   - Type: Galette\Core\Installation\StepResult
[2026-03-24 10:30:15]   - requiresDisplay: NO
[2026-03-24 10:30:15]   - isSuccess: YES
[2026-03-24 10:30:15]   - Messages: ["Galette requirements are met :)"]
[2026-03-24 10:30:15] ========== INSTALLER DEBUG END ==========
```

Quand il y a une erreur :
```
[2026-03-24 10:30:15] ✗ ArgumentCountError: Too few arguments to function...
[2026-03-24 10:30:15] → Step constructor requires different arguments
```

## Désactivation

Pour désactiver le debug, commentez ou supprimez la ligne dans `installer.php` :

```php
// require_once __DIR__ . '/../install/debug_orchestrator.php';
```

## Informations capturées

Le debug capture :
- ✅ URI de la requête
- ✅ Méthode HTTP (GET/POST)
- ✅ Données POST
- ✅ Données GET
- ✅ Chargement de l'orchestrateur
- ✅ Étape courante
- ✅ Utilisation du nouveau système
- ✅ Classe Step utilisée
- ✅ Test d'instanciation
- ✅ Disponibilité méthode execute()
- ✅ StepResult (type, requiresDisplay, isSuccess, messages)
- ✅ Exceptions et erreurs

## Utilisation pour diagnostiquer des problèmes

### Problème : Pas d'auto-avancement
Vérifiez dans le log :
```
shouldUseNewSystem: YES  ← doit être YES
Step class: ...CheckStep  ← doit avoir une classe
requiresDisplay: NO       ← doit être NO pour auto-advance
```

### Problème : Erreur 500
Cherchez dans le log :
```
✗ ArgumentCountError
✗ TypeError
✗ Exception
```

### Problème : Mauvaise étape affichée
Vérifiez :
```
isCheckStep: YES/NO
isDbCheckStep: YES/NO
etc.
```

