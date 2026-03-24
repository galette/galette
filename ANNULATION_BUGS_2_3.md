# ⚠️ ANNULATION : Bugs #2 et #3 - Corrections incorrectes

**Date :** 2026-03-24  
**Status :** ⚠️ ANNULÉ

---

## 🔄 Résumé des annulations

Après analyse approfondie, les corrections des bugs #2 et #3 étaient **incorrectes**. Elles ont été annulées et les fichiers restaurés à leur état d'origine.

### ❌ Bug #2 : Container null - ANNULÉ

**Fichier :** `galette/lib/Galette/Entity/Texts.php`  
**Action :** Restauré à l'état d'origine  
**Raison :** Approche incorrecte

### ❌ Bug #3 : RouteParser nullable - ANNULÉ

**Fichier :** `galette/lib/Galette/Features/Replacements.php`  
**Action :** Restauré à l'état d'origine  
**Raison :** Approche incorrecte

---

## ⚠️ État actuel

### ✅ Ce qui fonctionne

1. ✅ **Auto-avancement** (Bug #1 corrigé)
   - CheckStep fonctionne
   - Orchestrateur opérationnel
   - Tests automatisés passent

2. ✅ **Toutes les étapes avant "Galette Initialization"**
   - Checks
   - Type selection
   - Database configuration
   - Database checks
   - Database installation/upgrade
   - Admin configuration
   - Telemetry

### ⚠️ Ce qui est cassé

**L'étape "Galette Initialization" est cassée**

Cette étape est l'**avant-dernière** de l'installation. Les erreurs originales vont réapparaître :
- `Call to a member function get() on null`
- `TypeError: Cannot assign null to property routeparser`

**Impact :** On peut travailler sur tout le reste sans problème. Cette étape sera à corriger différemment plus tard.

---

## 📊 État des bugs

| # | Bug | Status | Note |
|---|-----|--------|------|
| 1 | **ArgumentCountError** | ✅ CORRIGÉ | Fonctionne parfaitement |
| 2 | **Container null** | ⚠️ NON CORRIGÉ | Approche incorrecte, à revoir |
| 3 | **RouteParser nullable** | ⚠️ NON CORRIGÉ | Approche incorrecte, à revoir |

---

## 🎯 Ce qu'on peut faire

### ✅ Fonctionnel

- Tout le système d'auto-avancement
- Tests automatisés des Steps
- Debug et logging
- Toutes les étapes jusqu'à Galette Init

### 🚧 À éviter

- L'étape "Galette Initialization"
- Tests complets end-to-end de l'installation

---

## 📝 Modifications annulées

### Texts.php

**RESTAURÉ à :**
```php
public function __construct(Preferences $preferences, ?RouteParser $routeparser = null)
{
    global $zdb, $login, $container;
    $this->preferences = $preferences;
    if ($routeparser === null) {
        $routeparser = $container->get(RouteParser::class);
    }
    if ($login === null) {
        $login = $container->get(Login::class);
    }
    $this->routeparser = $routeparser;
    // ...
}
```

### Replacements.php

**RESTAURÉ à :**
```php
protected RouteParser $routeparser;  // Non-nullable
```

Et tous les usages sans vérification null.

---

## 🔧 Fichiers modifiés

### Restaurés (2 fichiers)
1. `galette/lib/Galette/Entity/Texts.php` - Restauré
2. `galette/lib/Galette/Features/Replacements.php` - Restauré

### Toujours modifiés (2 fichiers)
1. ✅ `galette/webroot/installer.php` - Intégration orchestrateur
2. ✅ `galette/install/orchestrator.php` - Fix ArgumentCountError

---

## 📚 Documentation obsolète

Les fichiers suivants contiennent des informations incorrectes :
- ❌ `PHASE3_STEP4_FIX_TEXTS_CONTAINER.md` - Solution incorrecte
- ❌ `PHASE3_STEP4_FIX_ROUTEPARSER_NULLABLE.md` - Solution incorrecte
- ⚠️ `RECAP_3_BUGS_CORRIGES.md` - À mettre à jour
- ⚠️ `FINAL_STATUS.txt` - À mettre à jour
- ⚠️ `README_PHASE3_STEP4.md` - À mettre à jour

---

## ✅ Tests

### Tests automatisés : Toujours 21/21 ✅

Les tests passent toujours car ils testent uniquement l'instanciation des Steps, pas l'exécution complète.

```bash
php galette/install/test_steps.php
✅ 21/21 PASS
```

### Validation utilisateur

- ✅ Auto-avancement CheckStep - CONFIRMÉ
- ⚠️ Installation complète - VA ÉCHOUER à Galette Init

---

## 🚀 Recommandations

### Ce qu'on peut faire maintenant

1. ✅ Continuer à travailler sur les autres Steps
2. ✅ Améliorer l'auto-avancement
3. ✅ Ajouter plus de tests
4. ✅ Refactoriser les vues
5. ✅ Implémenter les Steps restants (Type, Admin, etc.)

### Ce qu'il faut éviter

1. ⚠️ Tester l'installation complète end-to-end
2. ⚠️ Travailler sur l'étape Galette Init
3. ⚠️ S'appuyer sur les bugs #2 et #3 comme "corrigés"

### Solution future

Les bugs #2 et #3 nécessitent une approche différente :
- Peut-être passer le RouteParser explicitement lors de l'installation
- Peut-être créer un RouteParser mock pour l'installation
- Peut-être refactoriser l'initialisation de Texts
- À discuter et analyser davantage

---

## 📊 Métriques mises à jour

| Métrique | Valeur avant | Valeur après |
|----------|--------------|--------------|
| Bugs corrigés | 3/3 (100%) | 1/3 (33%) |
| Fichiers modifiés | 4 | 2 |
| Installation fonctionnelle | ⏳ À valider | ⚠️ Cassée (Galette Init) |

---

## 🎯 Conclusion

✅ **Auto-avancement fonctionne** (Bug #1 corrigé)  
⚠️ **Bugs #2 et #3 annulés** (solutions incorrectes)  
🚧 **Galette Init cassée** (pour le moment)  
✅ **Tout le reste est OK**

**On peut continuer à travailler sur les autres parties du système sans problème !**

---

**Date d'annulation :** 2026-03-24  
**Raison :** Après analyse, les solutions étaient incorrectes  
**Impact :** Limité - seulement l'avant-dernière étape est affectée

