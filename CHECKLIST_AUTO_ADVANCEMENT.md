# ✅ Checklist : Test de l'auto-avancement

**Date :** 2026-03-24  
**Objectif :** Valider que l'auto-avancement fonctionne correctement

---

## Préparation

- [x] Erreur ArgumentCountError corrigée
- [x] Tests automatisés passent
- [x] Syntaxe PHP validée
- [x] Aucune erreur dans l'IDE

---

## Test 1 : CheckStep avec tout OK

### Actions
1. [ ] Ouvrir : `http://galette.localhost/installer.php?raz`
2. [ ] Observer l'affichage

### Résultat attendu
- [ ] ✓ Message : "Galette requirements are met :)"
- [ ] ✓ Icône loading visible (cercle qui tourne)
- [ ] ✓ Texte : "Proceeding to next step..."
- [ ] ✓ Après ~1 seconde : redirect automatique
- [ ] ✓ Page TypeStep s'affiche

### Si ça ne marche PAS
- [ ] Activer le debug (voir DEBUG_INSTALLER_GUIDE.md)
- [ ] Vérifier les logs : `tail -f galette/data/logs/installer_debug.log`
- [ ] Vérifier les erreurs PHP : `tail -f galette/data/logs/galette_install.log`
- [ ] Vérifier la console navigateur (F12)

---

## Test 2 : CheckStep avec erreur

### Actions
1. [ ] Modifier temporairement un check pour le faire échouer
2. [ ] Recharger `installer.php?raz`

### Résultat attendu
- [ ] ❌ Page complète affichée (PAS d'auto-advance)
- [ ] ❌ Erreurs listées
- [ ] ❌ Bouton "Retry" visible
- [ ] ❌ PAS de redirect automatique

### Rétablir
- [ ] Remettre le check en état fonctionnel

---

## Test 3 : DatabaseCheckStep

### Actions
1. [ ] Passer CheckStep
2. [ ] Choisir "New installation"
3. [ ] Configurer la base de données
4. [ ] Arriver à DatabaseCheckStep

### Résultat attendu
- [ ] ✓ Vérifications DB exécutées
- [ ] ✓ Si OK : auto-advance (notification + redirect)
- [ ] ✓ Si erreur : page complète avec détails

---

## Test 4 : Sans JavaScript

### Actions
1. [ ] Désactiver JavaScript dans le navigateur
2. [ ] Refaire Test 1

### Résultat attendu
- [ ] ✓ Block `<noscript>` visible
- [ ] ✓ Message : "JavaScript is disabled..."
- [ ] ✓ Bouton "Continue" manuel affiché
- [ ] ✓ Clic bouton → next step

### Rétablir
- [ ] Réactiver JavaScript

---

## Test 5 : DatabaseInstallStep avec modal

### Actions
1. [ ] Arriver à DatabaseInstallStep
2. [ ] Observer l'exécution

### Résultat attendu
- [ ] ✓ Modal s'ouvre automatiquement
- [ ] ✓ Rapport SQL visible
- [ ] ✓ Requêtes exécutées
- [ ] ✓ Bouton "OK" pour fermer
- [ ] ✓ Après fermeture : auto-submit form
- [ ] ✓ Redirect vers next step

---

## Test 6 : Galette Initialization (fix Texts.php)

### Actions
1. [ ] Continuer l'installation jusqu'à l'étape "Galette initialization"
2. [ ] Observer l'initialisation des objets

### Résultat attendu
- [ ] ✓ Pas d'erreur "Call to member function get() on null"
- [ ] ✓ Message : "Configuration file created!"
- [ ] ✓ Message : "Data initialized."
- [ ] ✓ Liste des objets initialisés affichée
- [ ] ✓ Bouton "Next step" disponible

### Si erreur
- [ ] Vérifier logs : `tail -f galette/data/logs/galette_install.log`
- [ ] Vérifier que GALETTE_INSTALLER est défini
- [ ] Consulter : PHASE3_STEP4_FIX_TEXTS_CONTAINER.md

---

## Résultats

### Tout fonctionne ✅
- Félicitations ! L'auto-avancement est opérationnel
- Prochaine étape : Implémenter les Steps restants
- Voir : PHASE3_COMPLETE_SUMMARY.md pour la suite

### Problèmes rencontrés ❌

#### Erreur 500
- [ ] Vérifier les logs PHP
- [ ] Activer le debug
- [ ] Vérifier que l'orchestrateur est bien chargé

#### Pas d'auto-avancement
- [ ] Vérifier dans le debug : `shouldUseNewSystem: YES`
- [ ] Vérifier : `requiresDisplay: NO`
- [ ] Vérifier : `isSuccess: YES`

#### Redirect trop rapide
- [ ] Modifier le timeout dans `renderAutoAdvance()` (ligne ~110)
- [ ] Changer `1000` (1s) en `2000` (2s) ou plus

#### Redirect trop lent
- [ ] Idem, réduire le timeout

---

## Debug rapide

### Activer les logs
```bash
# Dans installer.php, ajouter après orchestrator :
require_once __DIR__ . '/../install/debug_orchestrator.php';

# Suivre les logs :
tail -f galette/data/logs/installer_debug.log
```

### Voir les erreurs PHP
```bash
tail -f galette/data/logs/galette_install.log
```

### Console navigateur
```
F12 → Console
Vérifier erreurs JavaScript
```

---

## Notes

**Durée estimée :** 10-15 minutes

**Prérequis :**
- Serveur web fonctionnel
- PHP 8.1+
- Base de données accessible

**En cas de problème :**
- Consulter : DEBUG_INSTALLER_GUIDE.md
- Consulter : PHASE3_STEP4_FIX_ARGUMENTCOUNT.md
- Exécuter : `php galette/install/test_steps.php`

---

## Validation finale

Une fois TOUS les tests passés :
- [ ] Désactiver le debug (commenter la ligne)
- [ ] Mettre à jour PHASE3_COMPLETE_SUMMARY.md
- [ ] Commit des changements

**Bravo ! 🎉**

