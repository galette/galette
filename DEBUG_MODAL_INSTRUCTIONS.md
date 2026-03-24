# 🐛 DEBUG Modal - Instructions

**Date :** 2026-03-24  
**Problème :** Modal DatabaseInstallStep ne s'affiche toujours pas

---

## 🔍 Logs de debug ajoutés

Des logs ont été ajoutés dans `installer.php` pour diagnostiquer le problème.

### Où trouver les logs

**Logs PHP (error_log) :**
```bash
# Selon configuration PHP, vérifier :
tail -f /var/log/apache2/error.log
# OU
tail -f /var/log/php-fpm/www-error.log  
# OU
tail -f /var/log/php/error.log
```

**Logs Galette :**
```bash
tail -f galette/data/logs/galette_install.log
```

---

## 🧪 Procédure de test

### 1. Préparer

```bash
# Terminal 1 : Suivre les logs PHP
tail -f /var/log/apache2/error.log | grep "MODAL DEBUG"

# Terminal 2 : Préparer l'installation
rm -f galette/config/config.inc.php
```

### 2. Lancer l'installation

```
http://galette.localhost/installer.php?raz
```

### 3. Avancer jusqu'à DatabaseInstallStep

1. CheckStep → auto-avancement
2. TypeStep → Sélectionner "New installation"
3. DatabaseStep → Entrer config DB
4. DatabaseCheckStep → auto-avancement
5. **DatabaseInstallStep** → Observer les logs

### 4. Observer les logs

**Les logs devraient afficher :**

```
[MODAL DEBUG] Current step: db_install
[MODAL DEBUG] Is DB install step: YES
[MODAL DEBUG] Should use new system: YES
[MODAL DEBUG] StepResult isset: YES
[MODAL DEBUG] StepResult requiresDisplay: false
[MODAL DEBUG] StepData keys: db_installed, show_report_modal
[MODAL DEBUG] Has show_report_modal: YES
[MODAL DEBUG] RENDERING MODAL!
```

---

## 🔍 Diagnostic selon les logs

### Cas 1 : "Should use new system: NO"

**Problème :** L'installation n'utilise pas le nouveau système pour DatabaseInstallStep

**Solution :** Vérifier `shouldUseNewSystem()` dans `orchestrator.php`

### Cas 2 : "StepResult isset: NO"

**Problème :** Le StepResult n'est pas créé/passé correctement

**Solution :** Vérifier l'exécution de `executeStep()` dans `installer.php`

### Cas 3 : "StepResult requiresDisplay: true"

**Problème :** Le Step demande à afficher une page complète

**Solution :** Vérifier `DatabaseInstallStep::execute()` - devrait retourner `requiresDisplay: false`

### Cas 4 : "Has show_report_modal: NO"

**Problème :** Le flag n'est pas présent dans les données du StepResult

**Solution :** Vérifier que `DatabaseInstallStep::execute()` retourne bien le flag dans getData()

### Cas 5 : Tous les logs OK mais pas de modal

**Problème :** Le HTML est rendu mais le JavaScript ne fonctionne pas

**Solution :** Console navigateur (F12) pour voir les erreurs JS

---

## 🔧 Console navigateur (F12)

Ouvrir la console (F12) et chercher :

```javascript
[MODAL DEBUG] ...
```

**Vérifier :**
- jQuery chargé ?
- Semantic UI modal chargé ?
- Élément modal dans le DOM ?
- Erreurs JavaScript ?

---

## 📝 Rapporter les résultats

Copier les logs et envoyer :

```
=== LOGS PHP ===
[Copier ici]

=== CONSOLE NAVIGATEUR ===
[Copier ici]

=== OBSERVATION ===
[Décrire ce qui se passe]
```

---

## 🚀 Test rapide

```bash
# Réinitialiser
rm -f galette/config/config.inc.php

# Lancer avec logs
tail -f /var/log/apache2/error.log | grep "MODAL DEBUG" &

# Ouvrir navigateur
xdg-open http://galette.localhost/installer.php?raz

# Avancer jusqu'à DatabaseInstallStep
# Observer les logs
```

---

## ✅ Résultat attendu

Si tout fonctionne, vous devriez voir :

1. **Logs PHP :**
   ```
   [MODAL DEBUG] RENDERING MODAL!
   ```

2. **Page :**
   - Message de succès
   - **Modal qui s'ouvre automatiquement**
   - Rapport SQL dans la modal

3. **Console (F12) :**
   ```
   Initializing modal...
   Showing modal...
   ```

---

**Procédure créée :** 2026-03-24  
**Fichier de debug :** `installer.php` (logs error_log ajoutés)

