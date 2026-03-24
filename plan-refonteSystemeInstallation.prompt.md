# Plan : Refonte du système d'installation de Galette

Le système actuel repose sur des scripts PHP séparés par étape avec affichage systématique de pages. L'objectif est de moderniser l'architecture pour améliorer la maintenabilité et l'UX, en permettant certaines étapes de s'exécuter sans affichage dédié (transition automatique en cas de succès, affichage uniquement en cas d'erreur).

## Contexte

La cible, ce sont des utilisateurs lambdas, sans compétence technique particulière.

Un ensemble de scripts assurent l'installation, basé sur différentes étapes :
- Vérification de la compatibilité
- Sélection du type installation/mise à jour
- Sélection de la version
- Exécution des scripts d'installation/mise à jour
- Initialisation de valeurs diverses
- Configuration du mot de passe de l'administrateur

Tout cela fonctionne, mais c'est difficile à maintenir en l'état.

### Ce qui ne doit PAS changer

- Le système de mise à jour des bases de données reste fonctionnel et ne sera pas modifié pour le moment
- Une partie du code est commune (class `Galette\Core\Install`)
- Des commandes console Symfony assurent l'installation en ligne de commande
- Divers scripts assurent l'installation web
- Le système de mise à jour des plugins est copié sur ce modèle, mais n'utilise pas toutes les étapes

### Problèmes actuels

En installation web, toutes les étapes affichent actuellement une page spécifique. Pour certaines, ce n'est pas forcément nécessaire :

- **Exemple 1 : Vérification des droits en base** — La réussite de cette étape devrait mener directement à l'étape suivante, avec un simple message informatif ; la page pourrait être affichée uniquement en cas d'erreur.

- **Exemple 2 : Exécution des requêtes pour l'installation ou la mise à jour de la base** — À la différence que le rapport, même en cas de succès, doit être rendu disponible à l'utilisateur (dans une popup modale par exemple).

## Steps

### 1. Créer une architecture à base de `StepHandler`

**Objectif :** Définir une interface `InstallationStepInterface` et des classes handler pour chaque étape, séparant la logique métier (validation, exécution) de la présentation.

**Fichiers concernés :**
- `galette/lib/Galette/Core/Install.php` (classe existante)
- Nouveau : `galette/lib/Galette/Core/Installation/StepInterface.php`
- Nouveau : `galette/lib/Galette/Core/Installation/Step/` (namespace pour les handlers)

**Actions :**
- Créer l'interface `StepInterface` avec les méthodes :
  - `execute(): StepResult` — Exécution de la logique métier
  - `validate(): ValidationResult` — Validation des prérequis
  - `requiresUserInput(): bool` — Indique si l'étape nécessite une saisie utilisateur
  - `getStepName(): string` — Nom de l'étape
  - `canSkipDisplay(): bool` — Peut-on sauter l'affichage en cas de succès ?

- Créer des handlers concrets pour chaque étape :
  - `CheckStep` — Vérifications système (PHP, modules, permissions)
  - `TypeStep` — Sélection install/update
  - `DatabaseStep` — Configuration base de données
  - `DatabaseCheckStep` — Vérification connexion et droits
  - `VersionSelectionStep` — Sélection version (upgrade uniquement)
  - `DatabaseInstallStep` — Exécution scripts SQL
  - `AdminStep` — Configuration super admin
  - `TelemetryStep` — Opt-in télémétrie
  - `InitializationStep` — Initialisation Galette
  - `EndStep` — Fin

### 2. Implémenter un système de résultats typés

**Objectif :** Créer des objets de résultat avec statuts, messages et indicateur de nécessité d'affichage, permettant aux handlers de décider si une page doit être affichée.

**Fichiers à créer :**
- `galette/lib/Galette/Core/Installation/StepResult.php`
- `galette/lib/Galette/Core/Installation/ValidationResult.php`

**Structure `StepResult` :**
```php
class StepResult
{
    public function __construct(
        private StepStatus $status,      // SUCCESS, ERROR, WARNING
        private array $messages = [],     // Messages à afficher
        private bool $requiresDisplay = true,  // Faut-il afficher une page ?
        private ?array $report = null,    // Rapport détaillé (pour DB install)
        private ?string $nextStep = null  // Prochaine étape (auto-navigation)
    ) {}
    
    public function isSuccess(): bool;
    public function requiresDisplay(): bool;
    public function getMessages(): array;
    public function getReport(): ?array;
    public function shouldAutoAdvance(): bool;
}
```

**Logique :**
- `DatabaseCheckStep` : 
  - Si succès → `requiresDisplay = false`, `shouldAutoAdvance = true`
  - Si erreur → `requiresDisplay = true`, liste des permissions manquantes
  
- `DatabaseInstallStep` :
  - Si succès → `requiresDisplay = false` MAIS `report` disponible pour modal
  - Si erreur → `requiresDisplay = true` avec détails

### 3. Refactoriser le contrôleur web principal

**Objectif :** Modifier `installer.php` pour utiliser un pattern Command/Handler, exécutant les étapes séquentiellement et rendant une vue uniquement si `StepResult->requiresDisplay()` retourne `true`.

**Fichiers concernés :**
- `galette/webroot/installer.php` (refactorisation majeure)
- Nouveau : `galette/lib/Galette/Core/Installation/Workflow.php`

**Architecture proposée :**

```php
// Workflow.php
class Workflow
{
    private array $steps = [];
    
    public function addStep(StepInterface $step): self;
    public function getCurrentStep(): StepInterface;
    public function executeCurrentStep(): StepResult;
    public function advance(): void;
    public function goBack(): void;
}

// installer.php (simplifié)
$workflow = new Workflow($install);
$workflow->buildSteps(); // Construit la chaîne d'étapes

// Traitement POST
if ($_POST) {
    $result = $workflow->executeCurrentStep();
    
    if ($result->isSuccess() && !$result->requiresDisplay()) {
        // Auto-avance vers l'étape suivante
        $workflow->advance();
        header('Location: installer.php');
        exit;
    }
    
    // Affichage nécessaire (erreur ou rapport)
    $step_result = $result;
}

// Affichage conditionnel
$current_step = $workflow->getCurrentStep();
if ($current_step->requiresUserInput() || (isset($step_result) && $step_result->requiresDisplay())) {
    // Render template Twig
}
```

**Bénéfices :**
- Flux d'exécution clair et testable
- Séparation logique métier / présentation
- Auto-navigation transparente pour l'utilisateur

### 4. Adapter les commandes Symfony Console

**Objectif :** Mettre à jour `Galette\Console\Command\Install` pour utiliser les mêmes handlers, garantissant la cohérence entre installation web et CLI.

**Fichiers concernés :**
- `galette/lib/Galette/Console/Command/Install.php`

**Modifications :**
```php
protected function execute(InputInterface $input, OutputInterface $output): int
{
    $workflow = new Workflow($install);
    $workflow->buildSteps();
    
    while (!$workflow->isComplete()) {
        $step = $workflow->getCurrentStep();
        
        // Pour CLI, toujours demander l'input si nécessaire
        if ($step->requiresUserInput()) {
            $this->collectInputForStep($step, $io);
        }
        
        $io->section($step->getStepName());
        $result = $workflow->executeCurrentStep();
        
        if ($result->isSuccess()) {
            $io->success($result->getMessages());
            $workflow->advance();
        } else {
            $io->error($result->getMessages());
            return Command::FAILURE;
        }
    }
    
    return Command::SUCCESS;
}
```

**Avantages :**
- Même logique métier pour web et CLI
- Maintenance simplifiée
- Tests unitaires partagés

### 5. Améliorer les templates PHP existants (pas de Twig)

**Objectif :** Refactoriser les scripts PHP actuels pour mieux séparer logique et présentation, en conservant PHP pur pour éviter toute dépendance qui pourrait ne pas être initialisée.

**⚠️ DÉCISION IMPORTANTE : PAS DE TWIG DANS L'INSTALLATEUR**

**Raisons :**
1. **Dépendances non garanties** — Twig est une dépendance Composer. Si l'installation échoue avant l'installation des vendors, Twig n'est pas disponible.
2. **Bootstrap minimal** — L'installateur doit fonctionner avec un minimum absolu de dépendances pour maximiser la robustesse.
3. **Complexité inutile** — Ajouter Twig introduit un point de défaillance potentiel dans un processus critique.
4. **Système actuel stable** — Le système actuel avec PHP pur fonctionne depuis des années sans problème d'initialisation.

**Fichiers concernés :**
- Refactorisation : `galette/install/steps/*.php` (amélioration, pas remplacement)
- Nouveau : `galette/install/views/` (fonctions helpers PHP pour composants réutilisables)

**Structure proposée :**

```
galette/install/
├── steps/                      # Scripts d'étapes (gardés en PHP)
│   ├── check.php
│   ├── type.php
│   ├── database.php
│   ├── database-check.php
│   ├── version-select.php
│   ├── admin.php
│   ├── telemetry.php
│   ├── initialization.php
│   └── end.php
└── views/                      # Nouveaux helpers de rendu
    ├── layout.php              # Layout wrapper commun
    ├── components.php          # Fonctions pour composants réutilisables
    └── helpers.php             # Utilitaires d'affichage
```

**Nouveau fichier `galette/install/views/components.php` :**
```php
<?php
/**
 * Reusable view components for installer
 */

/**
 * Render validation list
 *
 * @param array<array{message: string, res: bool, debug?: string}> $validations
 * @param \Galette\Core\Install $install
 */
function renderValidationList(array $validations, \Galette\Core\Install $install): void
{
    echo '<ul class="leaders">';
    foreach ($validations as $item) {
        echo '<li>';
        echo '<span>' . htmlspecialchars($item['message']) . '</span>';
        echo '<span>' . $install->getValidationImage($item['res']) . '</span>';
        echo '</li>';
        if (isset($item['debug']) && !$item['res']) {
            echo '<li class="debug-info"><small>' . htmlspecialchars($item['debug']) . '</small></li>';
        }
    }
    echo '</ul>';
}

/**
 * Render modal for database installation report
 *
 * @param array<array{message: string, res: bool}> $report
 * @param \Galette\Core\Install $install
 * @param \Galette\Core\I18n $i18n
 */
function renderDbReportModal(array $report, \Galette\Core\Install $install, \Galette\Core\I18n $i18n): void
{
    ?>
    <div class="ui modal" id="db-install-report">
        <div class="header">
            <?php echo _T("Database installation report"); ?>
        </div>
        <div class="content">
            <?php renderValidationList($report, $install); ?>
        </div>
        <div class="actions">
            <div class="ui positive button"><?php echo _T("OK"); ?></div>
        </div>
    </div>
    <script>
        $(document).ready(function() {
            $('#db-install-report').modal('show');
        });
    </script>
    <?php
}

/**
 * Render step progress sidebar
 *
 * @param \Galette\Core\Install $install
 */
function renderStepProgress(\Galette\Core\Install $install): void
{
    // Existing code from installer.php lines 275-363
    // Moved to a reusable function
    // ... (code moved from installer.php)
}

/**
 * Render message box
 *
 * @param string $type Type: success, error, warning, info
 * @param string|array<string> $messages Message(s) to display
 */
function renderMessageBox(string $type, string|array $messages): void
{
    $class = match($type) {
        'success' => 'green',
        'error' => 'red',
        'warning' => 'orange',
        default => 'blue'
    };
    
    echo '<div class="ui ' . $class . ' message">';
    if (is_array($messages)) {
        echo '<ul class="list">';
        foreach ($messages as $msg) {
            echo '<li>' . htmlspecialchars($msg) . '</li>';
        }
        echo '</ul>';
    } else {
        echo htmlspecialchars($messages);
    }
    echo '</div>';
}
```

**Refactorisation exemple `database-check.php` :**
```php
<?php
// ... existing logic code ...

// Include components helpers
require_once __DIR__ . '/../views/components.php';

if (!isset($install_plugin)) {
    ?>
    <h2><?php echo _T("Check of the database"); ?></h2>
    <?php
}

if ($supported_db === false) {
    renderMessageBox('error', [
        _T("Incompatible database version."),
        $zdb->getUnsupportedMessage()
    ]);
} elseif ($db_connected === true && $permsdb_ok === true) {
    // Success case - this should auto-advance with new system
    if (!isset($install_plugin)) {
        renderMessageBox('success', [
            _T("Connection to database successful"),
            _T("Permissions to database are OK.")
        ]);
    } else {
        renderMessageBox('success', _T("Permissions to database are OK."));
    }
}

if ($db_connected !== true) {
    $conndb_ok = false;
    renderMessageBox('error', [
        _T("Unable to connect to the database"),
        $db_connected->getMessage()
    ]);
}

if ($conndb_ok && $supported_db === true) {
    if (!isset($install_plugin)) {
        echo '<h2>' . _T("Permissions on the base") . '</h2>';
    }
    
    if (!$permsdb_ok) {
        $error_msg = $install->isInstall() 
            ? _T("GALETTE hasn't got enough permissions on the database to continue the installation.")
            : _T("GALETTE hasn't got enough permissions on the database to continue the update.");
        renderMessageBox('error', $error_msg);
    }
    
    renderValidationList($result, $install);
}
?>
```

**Avantages de cette approche :**
- ✅ **Pas de dépendance externe** — Fonctionne même si Composer a échoué
- ✅ **Robustesse maximale** — Le code le plus critique reste simple
- ✅ **Réutilisabilité** — Helpers PHP partagés entre les étapes
- ✅ **Maintenabilité** — Code mieux structuré sans changer de paradigme
- ✅ **Compatibilité** — Pas de breaking change, migration progressive facile
- ✅ **Tests** — Facile à tester unitairement les fonctions de composants

### 6. Maintenir la compatibilité plugins

**Objectif :** S'assurer que `PluginInstall` hérite correctement du nouveau système en n'utilisant que les étapes pertinentes.

**Fichiers concernés :**
- `galette/lib/Galette/Core/PluginInstall.php`
- `galette/lib/Galette/Console/Command/Plugins/PluginInstallDb.php`

**Stratégie :**
```php
class PluginInstall extends Install
{
    protected function buildSteps(): array
    {
        // Plugins skip certain steps
        return [
            new TypeStep($this),
            new DatabaseCheckStep($this), // DB already configured
            new VersionSelectionStep($this),
            new DatabaseInstallStep($this),
            // NO AdminStep
            // NO TelemetryStep
            new EndStep($this)
        ];
    }
}
```

**Validation :**
- Tester l'installation d'un plugin existant
- Vérifier que seules les étapes pertinentes sont exécutées
- Confirmer que les vues PHP s'adaptent (variable `install_plugin` déjà utilisée dans le code actuel)

**Note :** Les plugins bénéficient aussi de l'approche PHP pur, car ils utilisent les mêmes fichiers de vues dans `galette/install/steps/` (notamment `db_checks.php` qui a déjà une logique conditionnelle pour `$install_plugin`).

## Further Considerations

### 1. Transition progressive ou refonte complète ?

**Option A : Refonte progressive**
- Avantages :
  - Minimise les risques
  - Permet de tester chaque étape individuellement
  - Possibilité de rollback partiel
- Inconvénients :
  - Maintenance de deux systèmes en parallèle temporairement
  - Durée de migration plus longue

**Option B : Migration complète (big bang)**
- Avantages :
  - Code plus propre immédiatement
  - Pas de dette technique temporaire
- Inconvénients :
  - Risque élevé
  - Nécessite une période de test étendue
  - Difficile à rollback

**Recommandation : Option A** — Refactoriser étape par étape en conservant l'ancien système en parallèle, avec un feature flag si nécessaire.

**Plan de migration progressif :**
1. Créer l'infrastructure (`StepInterface`, `StepResult`, `Workflow`)
2. Migrer `CheckStep` (non critique, facile à tester)
3. Migrer `DatabaseCheckStep` (test du système auto-advance)
4. Migrer `DatabaseInstallStep` (test des modales/rapports)
5. Migrer les autres étapes
6. Supprimer l'ancien code

### 2. Système de mise à jour des bases de données

**Confirmation nécessaire :**
- La logique dans `executeScripts()` et `executeSql()` reste **inchangée**
- Seule l'orchestration autour change (comment on appelle ces méthodes, comment on affiche les résultats)

**Points de vigilance :**
- Les scripts de migration SQL ne doivent pas être modifiés
- Les classes `UpgradeTo*` dans `galette/install/scripts/` restent compatibles
- Le système de détection de version (`getUpdateScripts()`) est préservé

### 3. Gestion des erreurs et rollback

**Question stratégique :** Pour les étapes critiques (`db_checks`, `db_install`), faut-il :

**Option 1 : Rollback automatique**
```php
class DatabaseInstallStep implements StepInterface
{
    public function execute(): StepResult
    {
        try {
            $zdb->beginTransaction();
            $success = $this->install->executeScripts($zdb);
            
            if ($success) {
                $zdb->commit();
            } else {
                $zdb->rollback();
            }
        } catch (\Exception $e) {
            $zdb->rollback();
            return new StepResult(
                StepStatus::ERROR,
                [$e->getMessage()],
                requiresDisplay: true
            );
        }
    }
}
```

**Option 2 : Messages explicites uniquement**
- Pas de rollback automatique
- Messages clairs sur les actions à effectuer manuellement
- L'utilisateur garde le contrôle

**Recommandation : Hybride**
- Rollback automatique pour les transactions SQL (déjà implémenté dans `executeSql()`)
- Messages explicites pour les actions non-transactionnelles (écriture fichiers config, etc.)
- Journalisation détaillée dans les logs

### 4. Tests et validation

**Tests unitaires à créer :**
- `StepResultTest` — Validation des états et transitions
- `WorkflowTest` — Navigation entre étapes
- Chaque `*StepTest` — Logique métier isolée

**Tests d'intégration :**
- Scénario complet installation MySQL
- Scénario complet installation PostgreSQL
- Scénario complet mise à jour (plusieurs versions)
- Installation plugin

**Tests manuels critiques :**
- Installation sur environnement vierge
- Mise à jour depuis version 1.0.0, 1.1.0, 1.2.0
- Gestion des erreurs (mauvais mot de passe DB, permissions insuffisantes)
- Interface responsive sur mobile
- Changement de langue pendant l'installation

### 5. UX et accessibilité

**Améliorations UX à implémenter :**
- **Indicateur de progression** : Barre visuelle montrant X/10 étapes
- **Messages contextuels** : Tooltips d'aide pour chaque champ
- **Auto-save** : Sauvegarder les données saisies en session pour éviter la perte
- **Animations de transition** : Feedback visuel lors du passage auto à l'étape suivante
- **Logs en temps réel** : Pour `DatabaseInstallStep`, afficher la progression (modal avec loader)

**Accessibilité :**
- Tous les messages d'erreur doivent avoir des `aria-live` regions
- Navigation au clavier complète
- Labels explicites pour les screen readers
- Contraste suffisant pour les messages de statut

### 6. Internationalisation

**Points de vigilance :**
- Tous les nouveaux messages doivent être dans `_T()`
- Mise à jour de `galette/lang/*.pot` après ajout de textes
- Tester avec langue RTL (arabe) pour la disposition des modales

### 7. Documentation

**Documentation à mettre à jour :**
- `CONTRIBUTING.rst` — Section sur l'architecture d'installation
- `README.md` — Mise à jour des captures d'écran
- Documentation développeur (nouvelle section "Installation System Architecture")
- Comments PHP pour les nouvelles classes

**Documentation utilisateur :**
- https://doc.galette.eu/installation/ — Mise à jour avec nouvelles captures
- FAQ : "Que faire si l'installation bloque à l'étape X ?"

## Implémentation recommandée

### Phase 1 : Infrastructure (1-2 semaines)
- [ ] Créer `StepInterface`, `StepResult`, `StepStatus`
- [ ] Créer classe `Workflow`
- [ ] Tests unitaires infrastructure
- [ ] Documentation code

### Phase 2 : Premier handler (1 semaine)
- [ ] Créer `galette/install/views/components.php` avec fonctions helpers
- [ ] Implémenter `CheckStep` (handler complet)
- [ ] Refactoriser `check.php` pour utiliser les composants
- [ ] Intégrer dans `installer.php` avec fallback ancien système
- [ ] Tests

### Phase 3 : Auto-navigation (1 semaine)
- [ ] Implémenter `DatabaseCheckStep` avec auto-advance
- [ ] Logique de redirection dans `installer.php`
- [ ] Tests UX (vérifier que transition est fluide)

### Phase 4 : Rapports modaux (1-2 semaines)
- [ ] Implémenter `DatabaseInstallStep`
- [ ] Fonction `renderDbReportModal()` dans `components.php`
- [ ] JavaScript pour affichage modal automatique
- [ ] Tests sur gros volumes de requêtes

### Phase 5 : Migration complète (2-3 semaines)
- [ ] Migrer tous les handlers restants
- [ ] Adapter commande CLI
- [ ] Supprimer ancien code
- [ ] Tests complets

### Phase 6 : Plugins et finalisation (1 semaine)
- [ ] Adapter `PluginInstall`
- [ ] Tester installation plugins
- [ ] Documentation finale
- [ ] Release notes

**Total estimé : 7-10 semaines** (selon disponibilité et complexité découverte)

## Critères de succès

✅ L'installation web complète fonctionne sans régression  
✅ L'installation CLI fonctionne sans régression  
✅ Les plugins peuvent s'installer correctement  
✅ Les étapes réussies ne nécessitant pas d'input passent automatiquement  
✅ Les rapports SQL sont accessibles via modal  
✅ Code coverage >= 80% sur les nouveaux handlers  
✅ Temps d'installation inchangé ou réduit  
✅ Documentation à jour  
✅ Tous les tests CI passent  
✅ Code respecte PSR-12, PHPStan level max, pas d'erreurs PHPCS





