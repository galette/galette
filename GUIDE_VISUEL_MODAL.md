# 🎨 GUIDE VISUEL - À quoi doit ressembler la modal

## ✅ MODAL FONCTIONNELLE (Ce que vous DEVEZ voir)

### Apparence générale

```
┌─────────────────────────────────────────────────────────────┐
│ FOND GRIS SEMI-TRANSPARENT (occupe tout l'écran)            │
│                                                               │
│        ┌──────────────────────────────────────┐              │
│        │  ✓ Database installation report      │              │
│        │                                       │              │
│        │  Tables created/modified:             │              │
│        │                                       │              │
│        │  • galette_adherents                  │              │
│        │  • galette_cotisations                │              │
│        │  • galette_transactions               │              │
│        │  • galette_database                   │              │
│        │  • galette_preferences                │              │
│        │  • galette_logs                       │              │
│        │  • galette_types_cotisation           │              │
│        │  • galette_statuts                    │              │
│        │  • ... (et d'autres tables)           │              │
│        │                                       │              │
│        │            [Continue →]               │              │
│        │                                       │              │
│        └──────────────────────────────────────┘              │
│                                                               │
└─────────────────────────────────────────────────────────────┘
```

### Caractéristiques visuelles

- **Fond :** Gris semi-transparent (rgba)
- **Modal :** Blanc ou bleu clair, centrée verticalement et horizontalement
- **Icône :** ✓ (check) ou icône de base de données
- **Titre :** "Database installation report" (ou équivalent traduit)
- **Contenu :** Liste des tables avec puces ou numérotation
- **Bouton :** Bleu, avec texte "Continue" ou "Continuer"
- **Animation :** Peut apparaître avec un effet de fondu

### Dans le code HTML (inspecteur F12)

```html
<div id="db-report-modal" class="ui modal active">
  <div class="header">
    <i class="database icon"></i>
    Database installation report
  </div>
  <div class="content">
    <ul>
      <li>galette_adherents</li>
      <li>galette_cotisations</li>
      <!-- ... -->
    </ul>
  </div>
  <div class="actions">
    <form method="post">
      <button class="ui primary button">Continue</button>
    </form>
  </div>
</div>
<div class="ui dimmer modals page active"></div>
```

---

## ❌ PAS DE MODAL (Ce que vous voyez SI LE BUG N'EST PAS CORRIGÉ)

### Apparence

```
┌─────────────────────────────────────────────────────────────┐
│                                                               │
│   [Barre de navigation Galette]                              │
│                                                               │
│   ┌──────────────────────────────────────────┐              │
│   │  Galette Installation                     │              │
│   │                                            │              │
│   │  ✓ Database has been installed :)         │              │
│   │                                            │              │
│   │  [Next step / form appears immediately]   │              │
│   │                                            │              │
│   └──────────────────────────────────────────┘              │
│                                                               │
└─────────────────────────────────────────────────────────────┘
```

**Pas de fond gris**  
**Pas de modal centrée**  
**Juste le message de succès et passage immédiat à l'étape suivante**

---

## 🔍 COMMENT VÉRIFIER

### 1. Visuel direct

**SI la modal s'affiche :**
- Vous ne pouvez PAS cliquer sur le fond
- Le fond est grisé
- La modal est au premier plan
- Il y a UNE SEULE action possible : cliquer sur "Continue"

**SI pas de modal :**
- Vous êtes directement sur la page suivante
- Pas de fond grisé
- Pas d'animation

### 2. Inspecteur navigateur (F12)

**Ouvrir la console :** F12 → onglet "Elements" (Chrome) ou "Inspecteur" (Firefox)

**Chercher :**
```html
<div id="db-report-modal"
```

**SI trouvé :**
- ✅ La modal existe dans le DOM
- Vérifier si elle a la classe `active` → elle devrait être visible

**SI pas trouvé :**
- ❌ Le HTML de la modal n'a pas été généré
- Le bug n'est pas corrigé OU le code PHP n'atteint pas la section de rendu

### 3. Console JavaScript (F12 → Console)

**Logs attendus (si ajoutés) :**
```
Modal initialized
Modal shown
```

**Erreurs possibles :**
```
Uncaught ReferenceError: $ is not defined
→ jQuery n'est pas chargé

Uncaught TypeError: $('.ui.modal').modal is not a function
→ Semantic UI n'est pas chargé
```

### 4. Logs PHP

**Terminal :**
```bash
tail -f /var/opt/remi/php84/log/php-fpm/www-error.log | grep "MODAL DEBUG"
```

**Log crucial :**
```
[MODAL DEBUG] RENDERING MODAL!
```

**SI ce log apparaît :**
- ✅ Le code PHP génère le HTML de la modal
- Si vous ne voyez pas la modal, c'est un problème JavaScript/CSS

**SI ce log n'apparaît PAS :**
- ❌ Le code PHP n'atteint pas la section de rendu
- Bug non corrigé OU autre problème en amont

---

## 🎯 CHECKLIST VISUELLE COMPLÈTE

Lors du test, cochez chaque élément :

### Avant l'étape DatabaseInstallStep

- [ ] L'installation progresse normalement
- [ ] Les étapes précédentes sont validées (icônes vertes)
- [ ] Pas d'erreurs affichées

### Pendant l'exécution de DatabaseInstallStep

- [ ] Logs apparaissent dans le terminal
- [ ] `[DatabaseInstallStep] execute() CALLED` visible
- [ ] `[DatabaseInstallStep] Scripts executed. Success: YES` visible
- [ ] Pas d'erreur PHP fatale

### Après l'exécution de DatabaseInstallStep

- [ ] `[MODAL DEBUG] RENDERING MODAL!` dans les logs
- [ ] **FOND GRIS SEMI-TRANSPARENT** apparaît dans le navigateur
- [ ] **MODAL CENTRÉE** apparaît au premier plan
- [ ] **TITRE** "Database installation report" visible
- [ ] **LISTE DES TABLES** affichée
- [ ] **BOUTON "Continue"** présent et cliquable

### Après avoir cliqué sur "Continue"

- [ ] La modal se ferme
- [ ] Passage à l'étape suivante (création du compte admin)
- [ ] Pas d'erreur affichée

---

## 📸 CAPTURES D'ÉCRAN (Description)

### ✅ SUCCÈS

**Vue d'ensemble :**
- Fond : gris 50% opacité
- Modal : blanche, 600-800px de large, centrée
- Ombre portée autour de la modal
- Scrollbar si la liste est longue

**Détails de la modal :**
- En-tête : fond bleu clair, icône base de données
- Corps : fond blanc, texte noir, liste avec puces
- Pied : fond gris très clair, bouton bleu

**Position :**
- Verticalement : centrée
- Horizontalement : centrée
- Z-index : au-dessus de tout le reste

### ❌ ÉCHEC

**Pas de fond gris**
**Pas de modal**
**Page classique avec message de succès vert en haut**

---

## 🚨 ERREURS VISUELLES POSSIBLES

### Modal existe mais n'est pas visible

**Symptôme :** HTML présent dans l'inspecteur, mais rien à l'écran

**Causes possibles :**
1. CSS non chargé : vérifier `semantic.min.css`
2. Classe `active` manquante : modal non activée
3. Z-index trop bas : modal derrière le contenu
4. Display:none : modal cachée par CSS

**Solution :** Ajouter manuellement la classe `active` dans l'inspecteur

### Modal coupée ou mal positionnée

**Symptôme :** Modal visible mais mal placée

**Causes possibles :**
1. CSS Semantic UI incomplet
2. Conflit avec CSS custom
3. Viewport trop petit (mobile)

**Solution :** Tester en plein écran, vérifier les CSS

### Modal sans fond grisé

**Symptôme :** Modal visible mais pas de dimmer

**Causes possibles :**
1. Balise `<div class="ui dimmer">` manquante
2. JavaScript Semantic UI non exécuté

**Solution :** Vérifier le code PHP de génération du dimmer

---

## 💡 ASTUCE PRO

**Pour être 100% sûr que la modal s'affiche :**

1. **Ralentir la page :**
   - F12 → Network → Throttling → Slow 3G
   - Cela laisse le temps de voir l'animation

2. **Pause JavaScript :**
   - F12 → Sources → Event Listener Breakpoints → Mouse → click
   - Cliquer sur le bouton Continue pour voir si l'événement se déclenche

3. **Capturer l'état :**
   - F12 → Application → Storage → Local Storage
   - Voir si des données sont stockées

---

**🎯 SI VOUS VOYEZ EXACTEMENT CE QUI EST DÉCRIT DANS LA SECTION "SUCCÈS", LE BUG EST CORRIGÉ ! 🎉**

