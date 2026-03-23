# Refonte du système d'installation - État d'avancement

**Date:** 2026-03-23  
**Statut:** Phase 1 et Phase 2 partiellement complétées

## ✅ Phase 1 : Infrastructure (TERMINÉE)

### Classes créées

1. **`StepStatus.php`** (Enum)
   - Statuts : SUCCESS, ERROR, WARNING, INFO
   - Méthodes helpers : `isSuccess()`, `isError()`, `getCssClass()`, `getIconName()`
   - Tests : ✅ 5 tests passent

2. **`StepResult.php`** (Classe de résultat)
   - Encapsule le résultat d'une étape
   - Propriétés : status, messages, requiresDisplay, report, data
   - Méthodes factory : `success()`, `error()`, `warning()`
   - Logique d'auto-avancement : `shouldAutoAdvance()`
   - Tests : ✅ 10 tests passent

3. **`StepInterface.php`** (Interface)
   - Définit le contrat pour toutes les étapes
   - Méthodes requises : `execute()`, `validate()`, `requiresUserInput()`, etc.
   - Documentation complète

4. **`AbstractStep.php`** (Classe abstraite)
   - Implémentation par défaut des méthodes communes
   - Réduit le boilerplate dans les steps concrets
   - Accès à l'instance Install via `getInstall()`

5. **`Workflow.php`** (Gestionnaire de workflow)
   - Gestion de la séquence d'étapes
   - Navigation : `advance()`, `goBack()`, `jumpToStep()`
   - Contexte partagé entre étapes
   - Exécution avec validation automatique
   - Tests : ✅ 4 tests passent

### Steps implémentés (stubs)

Tous les steps ont une structure de base et seront implémentés complètement dans les phases suivantes :

1. **`CheckStep`** (ordre: 10) - ⚠️ PARTIELLEMENT IMPLÉMENTÉ
   - Vérifications système (PHP, modules, permissions)
   - Logic métier complète
   - canSkipDisplay: false (toujours afficher)

2. **`TypeStep`** (ordre: 20) - TODO
   - Sélection install/update
   - requiresUserInput: true

3. **`DatabaseStep`** (ordre: 30) - TODO
   - Configuration base de données
   - requiresUserInput: true

4. **`DatabaseCheckStep`** (ordre: 40) - TODO
   - Vérification connexion et permissions
   - **canSkipDisplay: true** (auto-advance si succès)

5. **`VersionSelectionStep`** (ordre: 50) - TODO
   - Sélection version (upgrade uniquement)
   - isApplicable: UPDATE seulement

6. **`DatabaseInstallStep`** (ordre: 60) - TODO
   - Exécution scripts SQL
   - **canSkipDisplay: true** (mais rapport en modal)

7. **`AdminStep`** (ordre: 70) - TODO
   - Configuration super admin
   - isApplicable: INSTALL seulement

8. **`TelemetryStep`** (ordre: 80) - TODO
   - Opt-in télémétrie

9. **`InitializationStep`** (ordre: 90) - TODO
   - Initialisation Galette

10. **`EndStep`** (ordre: 100) - TODO
    - Fin de l'installation

### Tests unitaires

- **StepStatusTest.php** : 5 tests, tous ✅
- **StepResultTest.php** : 10 tests, tous ✅
- **WorkflowTest.php** : 4 tests, tous ✅

**Total : 19 tests, 65 assertions, 100% de réussite**

### Qualité du code

- ✅ PHP-CS-Fixer : Tous les fichiers conformes
- ✅ Structure PSR-4 respectée
- ✅ Documentation PHPDoc complète
- ✅ Types stricts activés (declare(strict_types=1))

## ✅ Phase 2 : Helpers de vue PHP (TERMINÉE)

### Fichiers créés

1. **`galette/install/views/components.php`**
   - `renderValidationList()` - Liste de validations avec icônes
   - `renderMessageBox()` - Boîtes de messages Semantic UI
   - `renderDbReportModal()` - Modal pour rapport SQL
   - `renderStepProgress()` - Indicateur de progression
   - `renderFormNavigation()` - Boutons Next/Back/Retry
   - `renderAutoAdvanceNotification()` - Notification auto-advance

2. **`galette/install/views/helpers.php`**
   - `escapeHtml()` - Échappement sécurisé
   - `isPost()`, `getPost()`, `hasPost()` - Helpers POST
   - `redirectToInstaller()` - Redirection
   - `formatFileSize()` - Formatage taille fichier
   - `getPhpConfig()` - Config PHP
   - `renderLoadingSpinner()` - Spinner de chargement
   - `getFieldErrorClass()` - Classe d'erreur pour champs
   - `renderDebugInfo()` - Info de debug

### Avantages de cette approche

- ✅ **Pas de dépendance à Twig** - Fonctionne même si vendors manquants
- ✅ **Code réutilisable** - Fonctions partagées entre toutes les étapes
- ✅ **Maintenabilité** - Logique de rendu centralisée
- ✅ **Performance** - Pas d'overhead de template engine
- ✅ **Simplicité** - PHP pur, facile à débugger

## 🔄 Phase 3 : Intégration (EN COURS)

### Prochaines étapes

1. **Refactoriser `check.php` pour utiliser les composants**
   - Remplacer le HTML inline par les fonctions helpers
   - Tester le rendu visuel

2. **Implémenter complètement `CheckStep::execute()`**
   - Utiliser les résultats dans la vue
   - Gérer le cas d'auto-advance (si implémenté)

3. **Intégrer le Workflow dans `installer.php`**
   - Mode hybride : ancien système + nouveau système
   - Feature flag optionnel pour tester

### Fichiers à modifier

- `galette/install/steps/check.php` - Refactoriser avec composants
- `galette/webroot/installer.php` - Intégrer Workflow (optionnel)

## 📋 Phases restantes

### Phase 4 : Rapports modaux (TODO)
- Implémenter `DatabaseInstallStep::execute()`
- Tester modal avec rapport SQL
- JavaScript pour affichage automatique

### Phase 5 : Migration complète (TODO)
- Implémenter tous les steps restants
- Migrer toutes les vues vers composants
- Supprimer ancien code

### Phase 6 : Plugins et finalisation (TODO)
- Adapter `PluginInstall`
- Tests d'intégration complets
- Documentation

## 🎯 Critères de succès (à vérifier)

- [ ] Installation web complète fonctionne
- [ ] Installation CLI fonctionne
- [ ] Plugins s'installent correctement
- [ ] Auto-avancement fonctionne
- [ ] Rapports SQL en modal
- [ ] Code coverage >= 80%
- [ ] Tous les tests CI passent
- [ ] Code style conforme

## 📝 Notes importantes

### Décisions prises

1. **Pas de Twig dans l'installateur**
   - Raison : Dépendance Composer non garantie
   - Solution : PHP pur avec fonctions helpers
   - Statut : Validé et implémenté

2. **Migration progressive**
   - Approche : Un step à la fois
   - Compatibilité : Ancien système reste fonctionnel
   - Rollback : Possible à tout moment

3. **Auto-avancement**
   - Logique : `isSuccess() && !requiresDisplay()`
   - Implémentation : Redirection HTTP ou JavaScript
   - UX : Notification avec progression

### Points d'attention

- ⚠️ **Tests nécessitent une base de données** - Garder ça en tête
- ⚠️ **Compatibilité plugins** - Tester avec vrais plugins
- ⚠️ **I18n** - Tous les nouveaux messages doivent être dans `_T()`
- ⚠️ **Accessibilité** - Vérifier navigation clavier et screen readers

## 🛠️ Commandes utiles

```bash
# Tests unitaires
DB=mysql galette/vendor/bin/phpunit --test-suffix=.php tests/Galette/Core/Installation/

# Code style
galette/vendor/bin/php-cs-fixer fix galette/lib/Galette/Core/Installation/

# Analyse statique (quand prêt)
galette/vendor/bin/phpstan analyse galette/lib/Galette/Core/Installation/
```

## 📚 Ressources

- Plan complet : `plan-refonteSystemeInstallation.prompt.md`
- Code existant : `galette/lib/Galette/Core/Install.php`
- Vues existantes : `galette/install/steps/*.php`
- Tests : `tests/Galette/Core/Installation/`

---

**Prochaine action recommandée :** Refactoriser `check.php` pour utiliser les nouveaux composants et tester le rendu.

