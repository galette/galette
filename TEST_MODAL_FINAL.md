# 🧪 TEST RAPIDE - Vérification de la modal

## 🚀 LANCEMENT DU TEST

### Terminal 1 : Surveillance des logs

```bash
tail -f /var/opt/remi/php84/log/php-fpm/www-error.log | grep -E "MODAL DEBUG|DatabaseInstallStep"
```

### Terminal 2 : Réinitialisation

```bash
cd /home/trasher/PhpstormProjects/galette.git
rm -f galette/config/config.inc.php
```

### Navigateur

```
http://galette.localhost/installer.php?raz
```

---

## ✅ LOGS ATTENDUS (Si le fix fonctionne)

Lors de l'étape DatabaseInstallStep, vous devriez voir :

```
[DatabaseInstallStep] execute() CALLED
[DatabaseInstallStep] Using provided Db instance
[DatabaseInstallStep] Executing scripts...
[DatabaseInstallStep] Scripts executed. Success: YES
[DatabaseInstallStep] Creating SUCCESS StepResult with show_report_modal flag
[DatabaseInstallStep] StepResult created. getData() = {"db_installed":true,"show_report_modal":true}
[MODAL DEBUG] Current step: db_install
[MODAL DEBUG] Is DB install step: YES
[MODAL DEBUG] Should use new system: YES
[MODAL DEBUG] StepResult isset: YES
[MODAL DEBUG] StepResult requiresDisplay: false
[MODAL DEBUG] StepData keys: db_installed, show_report_modal  ← DEVRAIT APPARAÎTRE !
[MODAL DEBUG] Has show_report_modal: YES
[MODAL DEBUG] RENDERING MODAL!  ← DEVRAIT APPARAÎTRE !
```

---

## 🎯 RÉSULTATS POSSIBLES

### ✅ SUCCÈS : Modal apparaît

**Logs :**
```
[MODAL DEBUG] StepData keys: db_installed, show_report_modal
[MODAL DEBUG] Has show_report_modal: YES
[MODAL DEBUG] RENDERING MODAL!
```

**Dans le navigateur :**
- Modal bleue avec titre "Database installation report"
- Liste détaillée des tables créées/modifiées
- Bouton "Continue" qui fonctionne

→ **LE BUG EST CORRIGÉ ! 🎉**

---

### ❌ ÉCHEC : Modal ne s'affiche toujours pas

**Logs :**
```
[MODAL DEBUG] StepData is NULL or not an array
```

→ Le fix n'a pas fonctionné. Il y a un autre problème.

**Actions :**
1. Vérifier que les modifications dans `installer.php` sont bien présentes
2. Vérifier qu'il n'y a pas de cache PHP-FPM : `sudo systemctl reload php84-php-fpm`
3. Consulter la console navigateur (F12) pour erreurs JavaScript

---

### ⚠️ AUTRE : Erreur avant l'étape DB

Si l'installation échoue avant d'arriver à DatabaseInstallStep :
1. Vérifier les logs d'erreur PHP
2. Vérifier la connexion à la base de données
3. Vérifier les permissions sur les fichiers

---

## 📸 CE QUE VOUS DEVRIEZ VOIR

**Dans le navigateur :**

```
┌────────────────────────────────────────────┐
│    ✓ Database installation report          │
│                                             │
│    Tables created/modified:                 │
│    • galette_adherents                      │
│    • galette_cotisations                    │
│    • galette_transactions                   │
│    • ... (liste complète)                   │
│                                             │
│              [Continue →]                   │
└────────────────────────────────────────────┘
```

**Fond semi-transparent gris**  
**Modal centrée, bleue, avec animation**

---

## 🔧 DÉPANNAGE RAPIDE

### La modal ne s'affiche pas mais les logs sont OK

**Problème :** JavaScript ou CSS

**Solution :**
1. Ouvrir la console navigateur (F12)
2. Chercher les erreurs JavaScript
3. Vérifier que Semantic UI est chargé

### Les logs s'arrêtent avant "RENDERING MODAL"

**Problème :** Le test `if (isset($stepData['show_report_modal']))` échoue

**Solution :**
1. Vérifier la ligne 446 de `installer.php`
2. Ajouter un log juste avant : `error_log("stepData = " . print_r($stepData, true));`

### Le navigateur affiche une erreur 500

**Problème :** Erreur PHP

**Solution :**
1. Voir `/var/opt/remi/php84/log/php-fpm/www-error.log`
2. Chercher "PHP Fatal error"
3. Corriger l'erreur de syntaxe

---

## ✅ SI TOUT FONCTIONNE

1. **Nettoyer les logs de debug** (optionnel, mais recommandé)
2. Tester une **mise à jour** (pas juste une installation)
3. Marquer le ticket comme résolu
4. Célébrer ! 🎉

---

**🚀 LANCEZ LE TEST MAINTENANT ET COPIEZ LES RÉSULTATS !**

