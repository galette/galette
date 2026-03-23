# Phase 3 - Checklist de tests visuels

**Date :** 2026-03-23  
**Modifications effectuées :** CheckStep implémenté + check.php refactorisé

## ✅ Modifications effectuées

1. **Sauvegarde**
   - ✅ check.php.old créé
   - ✅ installer.php.old créé

2. **Code**
   - ✅ CheckStep::execute() implémenté complètement
   - ✅ check.php remplacé par la version refactorisée
   - ✅ Composants de vue intégrés

3. **Tests unitaires**
   - ✅ 19/19 tests passent
   - ✅ Pas de régression

## 📋 Checklist de tests visuels

### Prérequis
- [ ] Serveur web local configuré
- [ ] Base de données accessible
- [ ] Navigateur récent (Chrome, Firefox, Safari, Edge)

### Tests à effectuer

#### 1. Affichage normal (tous les checks passent)

**URL:** `http://localhost/galette/webroot/installer.php`

- [ ] La page se charge sans erreur PHP
- [ ] Le titre "Welcome to the Galette Install!" s'affiche
- [ ] Le message vert "Galette requirements are met :)" apparaît
- [ ] Les 3 sections sont présentes :
  - [ ] System Requirements (PHP version, Date settings)
  - [ ] PHP Modules (avec icônes vertes)
  - [ ] Files permissions (avec icônes vertes)
- [ ] Toutes les lignes ont une icône (✓ verte ou ✗ rouge)
- [ ] Le bouton "Next step" est actif (bleu)
- [ ] Le bouton "Back" n'est PAS visible (première étape)

#### 2. Affichage avec PHP version insuffisante

**Comment tester:** Temporairement modifier `GALETTE_PHP_MIN` dans `versions.inc.php`

- [ ] Message d'erreur rouge affiché
- [ ] Message "Please upgrade your PHP installation" présent
- [ ] Icône rouge (✗) sur la ligne PHP version
- [ ] Bouton "Retry" affiché au lieu de "Next step"
- [ ] Le bouton "Next step" est désactivé (grisé)

#### 3. Affichage avec module manquant

**Comment tester:** Simuler un module manquant (difficile en pratique)

- [ ] Message d'erreur orange affiché
- [ ] "Some PHP modules are missing..." affiché
- [ ] Liste des modules manquants avec icônes rouges
- [ ] Liste des modules présents avec icônes vertes
- [ ] Modules recommandés marqués "(recommended)"

#### 4. Affichage avec permissions incorrectes

**Comment tester:** `chmod 555 galette/data/cache/` (puis restore avec `chmod 755`)

- [ ] Message orange "Files permissions are not OK!"
- [ ] Explication selon le mode (install/update)
- [ ] Commandes UNIX affichées
- [ ] Note pour Windows affichée
- [ ] Bouton "Retry" disponible

#### 5. Responsive / Mobile

- [ ] Sur mobile (< 768px), l'affichage s'adapte
- [ ] Les listes de validation sont lisibles
- [ ] Les boutons sont cliquables
- [ ] Pas de débordement horizontal

#### 6. Accessibilité

- [ ] Navigation au clavier possible (Tab)
- [ ] Le bouton "Next step" est focusable
- [ ] Les icônes ont des spans avec class "visually-hidden"
- [ ] Le contraste des textes est suffisant

#### 7. Multilingue

**Comment tester:** Changer la langue en haut à droite

- [ ] Changer en anglais : textes traduits
- [ ] Changer en français : textes traduits
- [ ] Les icônes restent cohérentes
- [ ] Les boutons sont traduits

#### 8. Comparaison ancien/nouveau

**Rollback vers l'ancien :**
```bash
cp galette/install/steps/check.php.old galette/install/steps/check.php
```

- [ ] L'ancien affichage fonctionne toujours
- [ ] Comparer visuellement : nouveau vs ancien
- [ ] Vérifier que le nouveau est plus clair

**Restaurer le nouveau :**
```bash
cp galette/install/steps/check_refactored.php galette/install/steps/check.php
```

## 🔍 Points d'attention

### Erreurs potentielles

1. **Modules manquants**
   - Vérifier que `getMissings()`, `getGoods()`, `getShoulds()` fonctionnent
   - Chaque catégorie doit être affichée correctement

2. **Permissions**
   - Vérifier que tous les répertoires sont listés
   - Icônes vertes pour writable, rouges pour non-writable

3. **Affichage Semantic UI**
   - Les messages doivent avoir les bonnes couleurs (vert, rouge, orange)
   - Les icônes doivent s'afficher correctement

### Console navigateur

Ouvrir la console (F12) et vérifier :
- [ ] Pas d'erreur JavaScript
- [ ] Pas d'erreur 404 sur les assets (CSS, JS, images)
- [ ] Semantic UI chargé correctement

## 📸 Captures d'écran recommandées

À faire pour documentation :
1. Vue normale (tout OK)
2. Vue avec erreurs
3. Vue mobile
4. Menu de changement de langue

## ✅ Validation finale

Après tous les tests :

- [ ] Tous les tests ci-dessus sont ✅
- [ ] Aucune régression par rapport à l'ancien
- [ ] Le nouveau est plus clair et plus lisible
- [ ] Prêt à passer à DatabaseCheckStep

## 🚀 Si tout est OK

Passer à la prochaine étape :
1. Implémenter DatabaseCheckStep::execute()
2. Intégrer db_checks.php avec les composants
3. Tester l'auto-avancement

## ⚠️ Si des problèmes

1. Noter les problèmes rencontrés
2. Créer des issues GitHub
3. Rollback si critique :
   ```bash
   cp galette/install/steps/check.php.old galette/install/steps/check.php
   ```

---

**Note :** Ces tests doivent être effectués dans un environnement de développement, jamais en production !

