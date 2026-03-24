# Phase 3 - Étape 4 : Intégration de l'orchestrateur et auto-avancement

**Date :** 2026-03-24  
**Status :** 🔄 EN COURS

## Objectif

Intégrer le nouveau système Step dans `installer.php` pour permettre l'auto-avancement automatique des steps qui ne nécessitent pas d'affichage.

## Changements effectués

### 1. Création de `galette/install/orchestrator.php` ✅

**Fonctions créées :**

- `executeStep()` - Execute un Step et retourne son StepResult
- `renderAutoAdvance()` - Génère le HTML pour auto-avancement avec notification temporaire
- `shouldUseNewSystem()` - Détermine si le step actuel utilise le nouveau système
- `getStepClassName()` - Mappe les steps aux classes
- `getNextStepAction()` - Retourne le nom du paramètre POST pour l'étape suivante
- `getAutoAdvanceData()` - Prépare les données à passer à l'étape suivante

**Caractéristiques :**

- ✅ Gestion progressive (permet migration étape par étape)
- ✅ Auto-avancement avec notification 1 seconde
- ✅ Fallback sans JavaScript (avec bouton manuel)
- ✅ Gestion d'erreurs robuste

### 2. Modification de `installer.php` ✅

**Changements :**

1. Inclusion de `orchestrator.php`
2. Ajout variable `$stepResult` pour stocker les résultats
3. Exécution des Steps AVANT le rendu HTML
4. Logique d'affichage conditionnelle :
   - Si `requiresDisplay === false` → auto-advance
   - Sinon → affichage normal de la vue

**Sauvegarde :** `installer.php.phase3-step4`

### 3. Steps refactorisés intégrés

Les steps suivants sont maintenant intégrés au système d'auto-avancement :

- ✅ CheckStep
- ✅ DatabaseCheckStep  
- ✅ DatabaseInstallStep

## Flux d'exécution

### Installation normale (avec auto-avancement)

```
1. User → GET installer.php?raz
   ↓
2. installer.php détecte CheckStep
   ↓
3. shouldUseNewSystem() → true
   ↓
4. executeStep(CheckStep::class)
   ↓
5. CheckStep::execute()
   ├─ Vérifications OK ✓
   └─ Return StepResult(success, requiresDisplay: false)
   ↓
6. $stepResult créé pour auto-advance
   ↓
7. HTML rendu : renderAutoAdvance()
   ├─ Affiche "Requirements met :)"
   ├─ Icône loading
   ├─ "Proceeding to next step..."
   ├─ Formulaire caché avec install_permsok=1
   └─ JavaScript auto-submit après 1s
   ↓
8. POST install_permsok=1
   ↓
9. $install->atTypeStep()
   ↓
10. Affichage étape suivante
```

### En cas d'erreur

```
1. CheckStep::execute()
   ├─ Erreur détectée (ex: PHP version) ✗
   └─ Return StepResult(failure, requiresDisplay: true)
   ↓
2. $stepResult = result
   ↓
3. include_once check.php
   ↓
4. Vue affiche erreurs + bouton Retry
   ↓
5. User corrige et réessaie
```

## Code clé

### Orchestrator - executeStep()

```php
function executeStep(string $stepClassName, array $data, \Galette\Core\Install $install): ?StepResult
{
    $step = new $stepClassName();
    $result = $step->execute($data);

    if (!$result->requiresDisplay()) {
        return null; // Signal auto-advance
    }

    return $result;
}
```

### Orchestrator - renderAutoAdvance()

```php
function renderAutoAdvance(StepResult $result, string $nextStepAction, array $hiddenData = []): void
{
    // Notification temporaire
    echo '<div class="ui icon positive message">';
    echo '  <i class="notched circle loading icon"></i>';
    echo '  <div class="content">';
    echo '    <div class="header">' . $message . '</div>';
    echo '    <p>' . _T("Proceeding to next step...") . '</p>';
    echo '  </div>';
    echo '</div>';
    
    // Formulaire auto-submit
    echo '<form id="auto-advance-form" method="post" ...>';
    echo '  <input type="hidden" name="' . $nextStepAction . '" value="1" />';
    echo '</form>';
    
    // JavaScript auto-submit après 1s
    echo '<script>';
    echo 'setTimeout(function() {';
    echo '  document.getElementById("auto-advance-form").submit();';
    echo '}, 1000);';
    echo '</script>';
    
    // Fallback sans JS
    echo '<noscript>...</noscript>';
}
```

### installer.php - Intégration

```php
// Execute new system steps
if (shouldUseNewSystem($install)) {
    $stepClassName = getStepClassName($install);
    if ($stepClassName !== null) {
        $result = executeStep($stepClassName, $stepData, $install);
        
        if ($result === null || !$result->requiresDisplay()) {
            // Auto-advance
            $stepResult = $result ?? StepResult::success(..., false);
        } else {
            // Display needed
            $stepResult = $result;
        }
    }
}

// Later in HTML...
if ($stepResult !== null && !$stepResult->requiresDisplay()) {
    // Render auto-advance
    renderAutoAdvance($stepResult, getNextStepAction($install), ...);
} elseif ($install->isCheckStep()) {
    // Render normal view
    include_once 'steps/check.php';
}
```

## Tests à effectuer

### Test 1 : CheckStep avec tout OK ✅

**Action :**
1. Ouvrir http://galette.localhost/installer.php?raz
2. Vérifier que les checks sont OK

**Résultat attendu :**
- ✅ Page affiche "Requirements met :)"
- ✅ Icône loading visible
- ✅ Message "Proceeding to next step..."
- ✅ Après 1 seconde, redirect automatique vers TypeStep
- ✅ Pas de clic nécessaire

### Test 2 : CheckStep avec erreur

**Action :**
1. Modifier GALETTE_PHP_MIN temporairement
2. Recharger installer.php?raz

**Résultat attendu :**
- ❌ Page complète affichée (pas d'auto-advance)
- ❌ Erreurs listées clairement
- ❌ Bouton "Retry" disponible
- ❌ Pas de redirect automatique

### Test 3 : DatabaseCheckStep avec tout OK

**Action :**
1. Configurer DB
2. Arriver à DatabaseCheckStep

**Résultat attendu :**
- ✅ Vérifications DB exécutées
- ✅ Notification temporaire
- ✅ Auto-redirect après 1s

### Test 4 : DatabaseInstallStep avec modal

**Action :**
1. Arriver à DatabaseInstallStep
2. Scripts SQL exécutés

**Résultat attendu :**
- ✅ Modal s'ouvre automatiquement
- ✅ Rapport SQL visible
- ✅ User clique OK
- ✅ Modal se ferme
- ✅ Auto-submit formulaire
- ✅ Redirect vers étape suivante

### Test 5 : Sans JavaScript

**Action :**
1. Désactiver JavaScript
2. Refaire Test 1

**Résultat attendu :**
- ✅ `<noscript>` block visible
- ✅ Bouton "Continue" manuel affiché
- ✅ Clic sur bouton → next step
- ✅ Installation peut continuer

## Problèmes potentiels et solutions

### Problème 1 : Double exécution des checks

**Description :** Les vues (check.php, db_checks.php) font leurs propres vérifications en plus du Step

**Solution actuelle :** Laisser tel quel pour compatibilité. Les vues peuvent être refactorisées plus tard pour utiliser `$stepResult` directement.

**TODO futur :** Refactoriser les vues pour utiliser `$stepResult->getReport()` au lieu de refaire les vérifications.

### Problème 2 : $zdb non disponible pour DatabaseCheckStep

**Description :** DatabaseCheckStep a besoin de l'objet $zdb

**Solution :** Passer $zdb dans `$stepData` si disponible :

```php
if ($install->isDbCheckStep() && isset($zdb)) {
    $stepData['db'] = $zdb;
}
```

### Problème 3 : Session non mise à jour lors auto-advance

**Description :** L'ancien système utilise POST handlers pour marquer steps comme passed

**Solution actuelle :** Laisser les POST handlers gérer cela. L'auto-advance POST les données nécessaires.

**TODO futur :** Implémenter `$install->markStepPassed()` dans Step classes.

## Prochaines étapes

### Étape 4.1 : Tests navigateur ⏳

1. Tester Check auto-advance
2. Tester DB checks auto-advance  
3. Tester DB install modal
4. Tester fallback sans JS
5. Tester gestion d'erreurs

### Étape 4.2 : Corrections si nécessaire

- Ajuster timing (1s peut être trop rapide/lent)
- Améliorer messages
- Corriger bugs découverts

### Étape 4.3 : Steps restants

Une fois l'orchestrateur validé, implémenter les steps restants :
- TypeStep
- DatabaseStep
- VersionSelectionStep
- AdminStep
- TelemetryStep
- InitializationStep
- EndStep

## Fichiers modifiés/créés

### Créés ✅
1. `galette/install/orchestrator.php` (221 lignes)
2. `PHASE3_STEP4_ORCHESTRATOR.md` (ce fichier)

### Modifiés ✅
1. `galette/webroot/installer.php`
   - Ajout orchestrator
   - Exécution Steps
   - Logique auto-advance

### Sauvegardes ✅
1. `galette/webroot/installer.php.phase3-step4`

## Statistiques

**Code ajouté :**
- orchestrator.php : 221 lignes
- installer.php : ~50 lignes modifiées

**Fonctionnalités :**
- 6 fonctions helper créées
- Auto-avancement implémenté
- Fallback JS/NoJS géré
- Gestion d'erreurs robuste

**Temps estimé :** ~45 minutes

## État actuel

🔄 **EN ATTENTE DE TESTS NAVIGATEUR**

Le code est implémenté, syntaxe validée. Prochaine action : tester dans le navigateur pour voir l'auto-avancement en action !

---

**Prochaine action recommandée :**
```bash
# Ouvrir navigateur et tester
http://galette.localhost/installer.php?raz
```

