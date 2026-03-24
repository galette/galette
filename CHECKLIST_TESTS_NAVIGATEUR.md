# 🧪 Checklist Tests Navigateur - Installation Galette

**Date :** 2026-03-24  
**Version :** Phase 3 - Auto-avancement

---

## 🎯 Objectif

Valider le système d'auto-avancement et les nouvelles fonctionnalités dans un navigateur réel.

---

## ⚙️ Préparation

### 1. Prérequis

- [ ] Base de données MySQL/MariaDB ou PostgreSQL installée
- [ ] Serveur web (Apache/Nginx) configuré
- [ ] PHP 8.1+ avec extensions requises
- [ ] Galette accessible via navigateur (ex: `http://galette.localhost`)

### 2. Réinitialisation

```bash
# Supprimer config existante
rm -f galette/config/config.inc.php

# Réinitialiser la base de données (optionnel)
# mysql -u root -p -e "DROP DATABASE IF EXISTS galette_test; CREATE DATABASE galette_test;"

# Ouvrir l'installateur
http://galette.localhost/installer.php?raz
```

---

## 📋 Tests à effectuer

### ✅ Test 1 : CheckStep - Auto-avancement

**URL :** `http://galette.localhost/installer.php?raz`

**Actions :**
1. [ ] Ouvrir l'URL dans le navigateur
2. [ ] Observer la page "Galette requirements"

**Résultats attendus :**

**Si tous les checks passent :**
- [ ] ✓ Message vert : "Galette requirements are met :)"
- [ ] ✓ Liste des checks avec icônes vertes
- [ ] ✓ Notification apparaît : "Proceeding to next step..."
- [ ] ✓ Icône loading visible
- [ ] ✓ Redirect automatique après ~1 seconde
- [ ] ✓ Arrivée sur l'étape "Installation mode" (TypeStep)

**Si un check échoue :**
- [ ] ❌ Message d'erreur visible
- [ ] ❌ Liste des checks avec icônes rouges pour ceux qui échouent
- [ ] ❌ Détails de l'erreur affichés
- [ ] ❌ Bouton "Retry" visible
- [ ] ❌ PAS de redirect automatique

**Sans JavaScript :**
- [ ] 🔄 Désactiver JavaScript
- [ ] 🔄 Recharger la page
- [ ] ✓ Formulaire avec bouton "Next step" visible
- [ ] ✓ Clic manuel fonctionne

**Notes :**
```
[Espace pour vos observations]
```

---

### ✅ Test 2 : TypeStep - Sélection type

**Prérequis :** Avoir passé CheckStep

**Actions :**
1. [ ] Observer la page "Installation mode"
2. [ ] Sélectionner "New installation"
3. [ ] Cliquer "Next step"

**Résultats attendus :**
- [ ] ✓ 2 cartes visibles : "New installation" et "Update"
- [ ] ✓ Radio button "New installation" pré-sélectionné
- [ ] ✓ Description visible pour chaque option
- [ ] ✓ Bouton "Next step" actif
- [ ] ✓ Bouton "Back" visible
- [ ] ✓ Clic "Next step" → redirect vers DatabaseStep

**Notes :**
```
[Espace pour vos observations]
```

---

### ✅ Test 3 : DatabaseStep - Configuration DB

**Prérequis :** Avoir sélectionné le type d'installation

**Actions :**
1. [ ] Observer la page "Database"
2. [ ] Remplir les champs :
   - Type: MySQL ou PostgreSQL
   - Host: localhost
   - Port: 3306 (MySQL) ou 5432 (PostgreSQL)
   - User: [votre user]
   - Password: [votre password]
   - Database: galette_test
   - Prefix: galette_
3. [ ] Cliquer "Next step"

**Résultats attendus :**
- [ ] ✓ Formulaire visible avec tous les champs
- [ ] ✓ Message d'info sur les permissions requises
- [ ] ✓ Sélection du type de DB fonctionne
- [ ] ✓ Tous les champs sont requis (HTML5 validation)
- [ ] ✓ Bouton "Next step" actif
- [ ] ✓ Bouton "Back" fonctionne
- [ ] ✓ Clic "Next step" → redirect vers DatabaseCheckStep

**Notes :**
```
[Espace pour vos observations]
```

---

### ✅ Test 4 : DatabaseCheckStep - Auto-avancement

**Prérequis :** Avoir configuré la DB

**Actions :**
1. [ ] Observer la page "Database access and permissions"
2. [ ] **NE RIEN FAIRE** - laisser le système travailler

**Résultats attendus :**

**Si droits OK :**
- [ ] ✓ Message vert : "Connection to database successful"
- [ ] ✓ Message : "Permissions to database are OK."
- [ ] ✓ Liste des permissions avec icônes vertes (CREATE, INSERT, SELECT, UPDATE, DELETE, DROP)
- [ ] ✓ Notification apparaît : "All database checks passed. Proceeding..."
- [ ] ✓ Redirect automatique après ~1 seconde
- [ ] ✓ Arrivée sur DatabaseInstallStep (ou VersionSelectionStep si upgrade)

**Si droits insuffisants :**
- [ ] ❌ Message rouge : "GALETTE hasn't got enough permissions..."
- [ ] ❌ Liste des permissions avec icônes rouges pour celles manquantes
- [ ] ❌ Détails de l'erreur pour chaque permission
- [ ] ❌ Bouton "Retry" visible
- [ ] ❌ PAS de redirect automatique

**Si connexion impossible :**
- [ ] ❌ Message rouge : "Unable to connect to the database"
- [ ] ❌ Message d'erreur de connexion
- [ ] ❌ Lien/bouton pour retourner à la config DB
- [ ] ❌ PAS de redirect automatique

**Sans JavaScript :**
- [ ] 🔄 Désactiver JavaScript
- [ ] ✓ Bouton "Next step" manuel visible
- [ ] ✓ Clic manuel fonctionne

**Notes :**
```
[Espace pour vos observations]
```

---

### ✅ Test 5 : DatabaseInstallStep - Modal avec rapport

**Prérequis :** Droits DB validés

**Actions :**
1. [ ] Observer la page "Database installation"
2. [ ] **NE RIEN FAIRE** - laisser les scripts SQL s'exécuter
3. [ ] Observer l'ouverture de la modal
4. [ ] Lire le rapport dans la modal
5. [ ] Cliquer "OK" dans la modal

**Résultats attendus :**

**Si installation réussie :**
- [ ] ✓ Message vert : "Database has been installed :)"
- [ ] ✓ **Modal s'ouvre automatiquement**
- [ ] ✓ Titre modal : "Installation Report"
- [ ] ✓ Message de succès dans la modal
- [ ] ✓ Liste détaillée des requêtes SQL exécutées
- [ ] ✓ Icônes vertes pour toutes les requêtes
- [ ] ✓ Bouton "OK" visible dans la modal
- [ ] ✓ Clic "OK" → modal se ferme
- [ ] ✓ **Auto-submit du formulaire**
- [ ] ✓ Redirect vers AdminStep (ou étape suivante)

**Si erreur SQL :**
- [ ] ❌ Message rouge : "Database has not been installed!"
- [ ] ❌ Page complète affichée (pas de modal)
- [ ] ❌ Liste des requêtes avec icônes rouges pour celles qui échouent
- [ ] ❌ Messages d'erreur SQL détaillés
- [ ] ❌ Bouton "Retry" visible
- [ ] ❌ PAS de redirect

**Sans JavaScript :**
- [ ] 🔄 Désactiver JavaScript
- [ ] ✓ Pas de modal (fallback)
- [ ] ✓ Rapport SQL visible directement sur la page
- [ ] ✓ Bouton "Continue" manuel visible
- [ ] ✓ Clic manuel fonctionne

**Notes :**
```
[Espace pour vos observations]
```

---

### ✅ Test 6 : AdminStep - Configuration admin

**Prérequis :** DB installée (nouveau install uniquement)

**Actions :**
1. [ ] Observer la page "Admin parameters"
2. [ ] Remplir les champs :
   - Username: admin
   - Password: admin123
   - Retype password: admin123
3. [ ] Cliquer "Next step"

**Résultats attendus :**
- [ ] ✓ Formulaire visible avec 3 champs
- [ ] ✓ Tous les champs sont requis
- [ ] ✓ Validation JavaScript du match des passwords
- [ ] ✓ Si passwords différents → Alert "Password mismatch!"
- [ ] ✓ Si passwords identiques → Redirect vers TelemetryStep
- [ ] ✓ Pas de bouton "Back" (impossible de revenir en arrière)

**Notes :**
```
[Espace pour vos observations]
```

---

### ✅ Test 7 : TelemetryStep - Télémétrie

**Prérequis :** Admin configuré

**Actions :**
1. [ ] Observer la page "Telemetry"
2. [ ] Observer l'état de la checkbox (cochée par défaut)
3. [ ] (Optionnel) Décocher la checkbox
4. [ ] Cliquer "Next step"

**Résultats attendus :**
- [ ] ✓ Checkbox "Send telemetry information" visible
- [ ] ✓ Checkbox cochée par défaut
- [ ] ✓ Message d'information sur l'anonymat des données
- [ ] ✓ Bouton "Register" visible (si non enregistré)
- [ ] ✓ Bouton "Next step" actif
- [ ] ✓ Clic "Next step" → redirect vers GaletteInitStep

**Notes :**
```
[Espace pour vos observations]
```

---

### ⚠️ Test 8 : GaletteInitStep - CASSÉ

**Prérequis :** Télémétrie validée

**⚠️ ATTENTION :** Cette étape est actuellement cassée (bugs #2 et #3 annulés)

**Actions :**
1. [ ] Observer la page "Galette initialization"
2. [ ] **Attendre l'erreur**

**Résultats attendus (ERREUR) :**
- [ ] ❌ Erreur 500 ou page blanche
- [ ] ❌ Erreur dans logs : "Call to a member function get() on null"
- [ ] ❌ OU "TypeError: Cannot assign null to property routeparser"

**Notes :**
```
Cette étape est CASSÉE. C'est NORMAL et ATTENDU.
Les bugs #2 et #3 ont été annulés car les solutions étaient incorrectes.
Cette étape sera corrigée dans une phase ultérieure.

Détails : Voir ANNULATION_BUGS_2_3.md
```

---

### ✅ Test 9 : EndStep - Fin (si accessible)

**Prérequis :** Galette initialisé (actuellement inaccessible)

**Actions :**
1. [ ] Observer la page "End!"
2. [ ] Lire le message de succès
3. [ ] Cliquer le lien vers Galette

**Résultats attendus :**
- [ ] ✓ Message : "Galette has been successfully installed!"
- [ ] ✓ Lien vers l'application Galette
- [ ] ✓ Instructions post-installation
- [ ] ✓ Clic lien → redirect vers page de login Galette

**Notes :**
```
[Actuellement inaccessible à cause de GaletteInitStep cassé]
```

---

## 🔍 Tests spéciaux

### Test 10 : Navigation "Back"

**Actions :**
1. [ ] Avancer jusqu'à DatabaseStep
2. [ ] Cliquer "Back"
3. [ ] Observer le retour à TypeStep
4. [ ] Cliquer "Next" pour revenir à DatabaseStep

**Résultats attendus :**
- [ ] ✓ Bouton "Back" présent sur toutes les pages (sauf Admin)
- [ ] ✓ Retour à l'étape précédente fonctionne
- [ ] ✓ Données saisies sont conservées
- [ ] ✓ Peut avancer à nouveau

---

### Test 11 : Refresh page

**Actions :**
1. [ ] Arriver sur DatabaseStep
2. [ ] Rafraîchir la page (F5)
3. [ ] Observer que la page se recharge correctement

**Résultats attendus :**
- [ ] ✓ Page se recharge sans erreur
- [ ] ✓ Reste sur la même étape
- [ ] ✓ Données saisies sont conservées (si dans session)

---

### Test 12 : URL directe

**Actions :**
1. [ ] Copier l'URL de DatabaseInstallStep
2. [ ] Ouvrir dans un nouvel onglet
3. [ ] Observer le comportement

**Résultats attendus :**
- [ ] ✓ Redirect vers l'étape courante appropriée
- [ ] ✓ OU message d'erreur si étapes précédentes non complétées
- [ ] ✓ Pas de crash

---

## 📊 Checklist résumée

### Auto-avancement ✅

| Step | Auto-avance | Testé | Fonctionne |
|------|-------------|-------|------------|
| CheckStep | ✅ | [ ] | [ ] |
| DatabaseCheckStep | ✅ | [ ] | [ ] |
| DatabaseInstallStep | ✅ + Modal | [ ] | [ ] |

### Formulaires 📝

| Step | Testé | Fonctionne |
|------|-------|------------|
| TypeStep | [ ] | [ ] |
| DatabaseStep | [ ] | [ ] |
| AdminStep | [ ] | [ ] |
| TelemetryStep | [ ] | [ ] |

### Problèmes connus ⚠️

| Step | Status | Note |
|------|--------|------|
| GaletteInitStep | ⚠️ CASSÉ | Bugs #2 #3 - Normal |

---

## 🐛 Rapporter un bug

Si vous trouvez un problème :

1. **Noter les détails :**
   - URL exacte
   - Navigateur et version
   - Message d'erreur exact
   - Console JavaScript (F12)
   - Logs PHP

2. **Capturer :**
   - Screenshot de la page
   - Console browser (F12)
   - Logs serveur PHP

3. **Documenter dans :**
   ```
   BUGS_FOUND_[DATE].md
   ```

---

## 📝 Template de rapport

```markdown
# Bug trouvé : [Description courte]

**Date :** [Date]
**Étape :** [Nom du Step]
**URL :** [URL complète]

## Comportement observé

[Description détaillée]

## Comportement attendu

[Ce qui devrait se passer]

## Reproduction

1. [Étape 1]
2. [Étape 2]
3. [...]

## Environnement

- OS: [Linux/Windows/Mac]
- Navigateur: [Chrome/Firefox/Safari] version [X]
- PHP: [version]
- DB: [MySQL/PostgreSQL] version [X]

## Logs

### Console JavaScript
```
[Copier ici]
```

### Logs PHP
```
[Copier ici]
```

## Screenshots

[Joindre]
```

---

## ✅ Validation finale

Après tous les tests :

- [ ] CheckStep auto-avancement : ✅ / ❌
- [ ] DatabaseCheckStep auto-avancement : ✅ / ❌
- [ ] DatabaseInstallStep modal : ✅ / ❌
- [ ] Tous les formulaires : ✅ / ❌
- [ ] Navigation Back : ✅ / ❌
- [ ] Fallback sans JS : ✅ / ❌

**Taux de succès :** __ / 6 (__ %)

---

## 🚀 Prochaines actions

Si tests OK :
- [ ] Documenter les succès
- [ ] Passer à Phase 5

Si tests KO :
- [ ] Identifier les bugs
- [ ] Créer tickets
- [ ] Corriger

---

**Date de test :** ___________  
**Testeur :** ___________  
**Version Galette :** ___________

