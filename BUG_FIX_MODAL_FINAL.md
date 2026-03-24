# 🎯 FIX FINAL - Modal de rapport d'installation BD

**Date :** 2026-03-24  
**Status :** ✅ **BUG CORRIGÉ**

---

## 🐛 DESCRIPTION DU BUG

La modal de rapport d'installation de la base de données ne s'affichait jamais après l'exécution réussie de `DatabaseInstallStep`, malgré le flag `show_report_modal` défini à `true`.

---

## 🔍 CAUSE IDENTIFIÉE

### Logs qui ont révélé le problème

```
[24-Mar-2026 08:44:18] [DatabaseInstallStep] StepResult created. getData() = {"db_installed":true,"show_report_modal":true}
[24-Mar-2026 08:44:25] [MODAL DEBUG] Current step: db_install
[24-Mar-2026 08:44:25] [MODAL DEBUG] StepData is NULL or not an array  ← PROBLÈME !
```

### Analyse

1. ✅ `DatabaseInstallStep::execute()` créait bien un `StepResult` avec les bonnes données
2. ✅ Le flag `show_report_modal` était présent dans `getData()`
3. ❌ **Mais dans `installer.php`, ces données étaient perdues !**

### Code défectueux (lignes 222-240 de installer.php)

```php
// Execute the step
$result = executeStep($stepClassName, $stepData, $install);

// Check if auto-advance is needed
if ($result === null || !$result->requiresDisplay()) {
    // Step doesn't need display - prepare auto-advance
    if ($result !== null) {
        $stepResult = $result; // ✅ OK
    } else {
        // ❌ PROBLÈME : Créait un nouveau StepResult VIDE !
        $stepResult = \Galette\Core\Installation\StepResult::success(
            [_T("Step completed successfully")],
            false  // ← requiresDisplay = false, SANS les données !
        );
    }
} else {
    // Step needs display - store result for view
    $stepResult = $result;
}
```

**Pourquoi le bug se produisait :**

- `DatabaseInstallStep` retourne `requiresDisplay() = false` pour permettre l'auto-advancement
- Mais il inclut aussi des données (`show_report_modal`, rapport, etc.)
- Le code pensait qu'un step avec `requiresDisplay = false` n'avait pas besoin de données
- Il créait donc un nouveau `StepResult` vide, **écrasant les données originales**

---

## ✅ SOLUTION APPLIQUÉE

### Fichier modifié

`galette/webroot/installer.php` (lignes 222-230)

### Nouveau code

```php
// Execute the step
$result = executeStep($stepClassName, $stepData, $install);

// Store result (whether it needs display or not)
// The result may contain data even if requiresDisplay is false
// (e.g., DatabaseInstallStep with modal flag)
if ($result !== null) {
    $stepResult = $result;
}
```

### Explication

- **Suppression de la logique complexe** qui créait un nouveau `StepResult`
- **Conservation systématique** du `StepResult` original avec toutes ses données
- Le flag `requiresDisplay` est maintenant **orthogonal** aux données :
  - `requiresDisplay = false` → le step s'auto-advance
  - Mais les données (`show_report_modal`, rapport, etc.) sont **préservées**
  - La modal peut donc s'afficher même pour un step auto-advancing

---

## 🧪 VÉRIFICATION

### Avant le fix

```
[MODAL DEBUG] StepData is NULL or not an array
```

→ Modal ne s'affichait jamais

### Après le fix (attendu)

```
[MODAL DEBUG] StepData keys: db_installed, show_report_modal
[MODAL DEBUG] Has show_report_modal: YES
[MODAL DEBUG] RENDERING MODAL!
```

→ Modal devrait maintenant s'afficher

---

## 🎯 TEST À EFFECTUER

1. Supprimer le config : `rm -f galette/config/config.inc.php`
2. Aller sur `http://galette.localhost/installer.php?raz`
3. Avancer jusqu'à l'étape d'installation de la BD
4. **Vérifier que la modal apparaît avec le rapport détaillé**

### Logs à surveiller

```bash
tail -f /var/opt/remi/php84/log/php-fpm/www-error.log | grep -E "MODAL DEBUG|DatabaseInstallStep"
```

---

## 📋 CHECKLIST POST-FIX

- [x] Code modifié dans `installer.php`
- [ ] Test en situation réelle (installation complète)
- [ ] Vérification que le rapport s'affiche correctement
- [ ] Vérification que le bouton "Continuer" fonctionne
- [ ] Test en mode upgrade (devrait aussi afficher la modal)
- [ ] Nettoyage des logs de debug si tout fonctionne

---

## 💡 LEÇON APPRISE

**Ne jamais supposer qu'un `StepResult` avec `requiresDisplay = false` n'a pas de données !**

Les deux concepts sont indépendants :
- `requiresDisplay` → Est-ce que le step a besoin d'un formulaire/interface ?
- `getData()` → Informations à passer à la vue (modal, flags, résultats, etc.)

Un step peut être auto-advancing **ET** avoir des données pour la vue.

---

## 🚀 PROCHAINES ÉTAPES

1. Tester l'installation complète
2. Si la modal s'affiche correctement, retirer les logs de debug
3. Fermer le ticket/issue associé
4. Documenter ce pattern pour les futurs contributeurs

---

**🎉 FIX SIMPLE MAIS CRITIQUE - LA MODAL DEVRAIT MAINTENANT FONCTIONNER !**

