# TODO: Implémenter la modal de rapport d'installation DB

## Problème identifié
La modal fonctionne techniquement mais perturbe le flux d'auto-avance de l'installateur.

## État actuel
- Les modifications pour la modal ont été annulées (2026-03-24)
- L'auto-avance fonctionne à nouveau normalement
- La modal `renderDbReportModal()` existe dans `galette/install/views/components.php`

## Ce qui doit être fait plus tard

### 1. Repenser le comportement de la modal
Actuellement, la logique était:
- DatabaseInstallStep retourne un StepResult avec `show_report_modal = true`
- installer.php détecte ce flag et affiche la modal au lieu de l'auto-avance
- Cela casse le flux normal

**Problème:** La modal s'affiche mais empêche la progression automatique.

### 2. Solutions possibles à explorer

#### Option A: Modal non-bloquante
- Afficher la modal PENDANT l'auto-avance
- La modal s'ouvre automatiquement avec le rapport
- L'auto-avance continue après un délai (par exemple 3-5 secondes)
- L'utilisateur peut fermer la modal ou laisser l'auto-avance se poursuivre

#### Option B: Bouton optionnel "Voir le rapport"
- Ne pas montrer la modal par défaut
- Auto-avance normale
- Ajouter un bouton "Voir le rapport détaillé" sur la page suivante
- Le rapport est stocké en session et peut être consulté plus tard

#### Option C: Intégration dans la page d'auto-avance
- Au lieu d'une modal, intégrer le rapport dans `renderAutoAdvance()`
- Afficher un résumé sur la page d'auto-avance elle-même
- L'auto-avance se poursuit normalement après le délai

### 3. Fichiers concernés
- `galette/webroot/installer.php` - Logique d'affichage conditionnelle
- `galette/install/steps/DatabaseInstallStep.php` - Retour du flag `show_report_modal`
- `galette/install/views/components.php` - Fonction `renderDbReportModal()`
- `galette/install/views/helpers.php` - Fonction `renderAutoAdvance()`

### 4. Tests à effectuer
Quand cette fonctionnalité sera réimplémentée:
1. Tester l'installation fresh (nouveau)
2. Tester l'upgrade
3. Vérifier que l'auto-avance fonctionne correctement
4. Vérifier que la modal s'affiche au bon moment
5. Vérifier que l'utilisateur peut voir le rapport complet
6. Tester avec/sans JavaScript

### 5. Décision à prendre
Quelle approche choisir ? Option A, B, C, ou une autre ?

---
**Date de création:** 2026-03-24
**Auteur:** GitHub Copilot
**Priorité:** Moyenne (fonctionnalité nice-to-have, pas critique)

