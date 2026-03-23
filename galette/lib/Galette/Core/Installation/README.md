# Nouveau système d'installation Galette

Ce document explique comment utiliser le nouveau système d'installation modulaire.

## Vue d'ensemble

Le nouveau système sépare clairement :
- **Logique métier** : Classes Step dans `galette/lib/Galette/Core/Installation/`
- **Orchestration** : Classe Workflow
- **Présentation** : Fonctions helpers PHP dans `galette/install/views/`

## Architecture

```
galette/
├── lib/Galette/Core/Installation/
│   ├── StepStatus.php          # Enum pour les statuts
│   ├── StepResult.php          # Classe de résultat
│   ├── StepInterface.php       # Interface des steps
│   ├── AbstractStep.php        # Classe de base
│   ├── Workflow.php            # Gestionnaire de workflow
│   └── Step/                   # Steps concrets
│       ├── CheckStep.php
│       ├── TypeStep.php
│       ├── DatabaseStep.php
│       ├── DatabaseCheckStep.php
│       ├── VersionSelectionStep.php
│       ├── DatabaseInstallStep.php
│       ├── AdminStep.php
│       ├── TelemetryStep.php
│       ├── InitializationStep.php
│       └── EndStep.php
└── install/
    ├── views/
    │   ├── components.php      # Composants de rendu
    │   └── helpers.php         # Fonctions utilitaires
    └── steps/
        └── *.php               # Vues des étapes (PHP pur)
```

## Utilisation des Steps

### Créer un nouveau step

```php
use Galette\Core\Installation\AbstractStep;
use Galette\Core\Installation\StepResult;

class MyCustomStep extends AbstractStep
{
    public const STEP_NAME = 'my_custom';
    public const STEP_ORDER = 25; // Position dans la séquence

    public function execute(array $data = []): StepResult
    {
        // Logique métier
        $success = $this->doSomething();
        
        if ($success) {
            return StepResult::success(
                [_T("Operation successful")],
                requiresDisplay: false // Auto-advance
            );
        }
        
        return StepResult::error(
            [_T("Operation failed")],
            report: ['details' => 'error info']
        );
    }

    public function getStepName(): string
    {
        return self::STEP_NAME;
    }

    public function getStepTitle(): string
    {
        return _T("My Custom Step");
    }

    public function getOrder(): int
    {
        return self::STEP_ORDER;
    }
}
```

### Utiliser le Workflow

```php
use Galette\Core\Installation\Workflow;

$workflow = new Workflow($install);
$workflow->buildSteps(); // Construit automatiquement la séquence

// Exécuter l'étape courante
$result = $workflow->executeCurrentStep($_POST);

// Vérifier le résultat
if ($result->isSuccess() && $result->shouldAutoAdvance()) {
    $workflow->advance();
    redirectToInstaller();
}

// Accéder au step courant
$currentStep = $workflow->getCurrentStep();
echo $currentStep->getStepTitle();
```

## Utilisation des composants de vue

### Afficher une liste de validation

```php
require_once __DIR__ . '/../views/components.php';

$validations = [
    ['message' => 'PHP Version', 'res' => true],
    ['message' => 'Database connection', 'res' => false, 'debug' => 'Connection refused'],
];

renderValidationList($validations, $install);
```

### Afficher une boîte de message

```php
// Message de succès
renderMessageBox('success', 'Installation completed!');

// Message d'erreur avec plusieurs messages
renderMessageBox('error', [
    'Database connection failed',
    'Please check your credentials'
]);

// Message d'avertissement
renderMessageBox('warning', 'Some features may not be available');
```

### Afficher le modal de rapport SQL

```php
$report = $install->getDbInstallReport();
renderDbReportModal($report, $install, $i18n, $success = true);
```

### Afficher la navigation

```php
renderFormNavigation(
    canAdvance: $all_checks_passed,
    canGoBack: !$install->isCheckStep(),
    showRetry: !$all_checks_passed,
    i18n: $i18n,
    hiddenInputs: ['step_validated' => '1']
);
```

## Fonctions utilitaires

```php
require_once __DIR__ . '/../views/helpers.php';

// Échappement sécurisé
echo escapeHtml($user_input);

// Vérifier POST
if (isPost() && hasPost('submit')) {
    $value = getPost('field_name', 'default');
}

// Redirection
redirectToInstaller(['step' => 'next']);

// Formatage
echo formatFileSize(1024 * 1024); // "1 MB"
echo getPhpConfig('memory_limit'); // "128M"

// Vérifier extension
if (isExtensionLoaded('pdo_mysql')) {
    // ...
}
```

## Auto-avancement

Les steps peuvent décider s'ils nécessitent l'affichage d'une page :

```php
// Step qui s'auto-avance en cas de succès
public function canSkipDisplay(): bool
{
    return true;
}

// Dans execute()
return StepResult::success(
    ['All checks passed'],
    requiresDisplay: false // Auto-advance
);
```

Quand `shouldAutoAdvance()` retourne `true`, le workflow peut :
1. Afficher une notification brève
2. Rediriger automatiquement vers l'étape suivante

```php
if ($result->shouldAutoAdvance()) {
    renderAutoAdvanceNotification(
        _T("Database checks passed, proceeding..."),
        delay: 1500 // milliseconds
    );
}
```

## Tests

### Tester un step

```php
use PHPUnit\Framework\TestCase;
use Galette\Core\Install;
use Galette\Core\Installation\Step\CheckStep;

class CheckStepTest extends TestCase
{
    public function testExecuteWithValidSystem(): void
    {
        $install = new Install();
        $step = new CheckStep($install);
        
        $result = $step->execute();
        
        $this->assertTrue($result->isSuccess());
        $this->assertFalse($result->requiresDisplay());
    }
}
```

### Tester le workflow

```php
public function testWorkflowNavigation(): void
{
    $workflow = new Workflow($install);
    $workflow->buildSteps();
    
    $this->assertEquals(0, $workflow->getCurrentStepIndex());
    
    $workflow->advance();
    $this->assertEquals(1, $workflow->getCurrentStepIndex());
}
```

## Migration progressive

Le système est conçu pour une migration progressive :

### Étape 1 : Utiliser les composants dans les vues existantes

```php
// Dans galette/install/steps/check.php
require_once __DIR__ . '/../views/components.php';

// Remplacer
echo '<ul class="leaders">...';
// Par
renderValidationList($checks, $install);
```

### Étape 2 : Implémenter la logique dans les Steps

```php
// Déplacer la logique métier de check.php vers CheckStep::execute()
```

### Étape 3 : Intégrer le Workflow

```php
// Dans installer.php, ajouter support du workflow en parallèle de l'ancien système
```

### Étape 4 : Supprimer l'ancien code

Une fois tout testé et validé.

## Bonnes pratiques

### Steps

- ✅ Un step = une responsabilité
- ✅ Logique métier isolée de la présentation
- ✅ Messages toujours dans `_T()` pour i18n
- ✅ Utiliser `StepResult` pour communiquer le résultat
- ✅ Documenter avec PHPDoc

### Vues

- ✅ Utiliser les composants helpers
- ✅ Éviter le HTML inline répétitif
- ✅ Échapper toutes les données utilisateur avec `escapeHtml()`
- ✅ Tester l'accessibilité (navigation clavier, screen readers)

### Tests

- ✅ Tester la logique métier des steps
- ✅ Tester la navigation du workflow
- ✅ Mocker les dépendances externes
- ✅ Coverage >= 80%

## Dépannage

### Les composants ne s'affichent pas

Vérifier que les fichiers sont bien inclus :
```php
require_once __DIR__ . '/../views/components.php';
require_once __DIR__ . '/../views/helpers.php';
```

### Auto-avancement ne fonctionne pas

Vérifier :
1. `StepResult::requiresDisplay` est `false`
2. `StepResult::isSuccess()` retourne `true`
3. JavaScript est activé (pour la notification)

### Erreur "Class not found"

Régénérer l'autoloader :
```bash
composer dump-autoload
```

### Tests échouent

Vérifier :
1. Base de données configurée : `bin/console galette:install`
2. Variable `DB` définie : `DB=mysql`
3. PHPUnit exécuté avec `--test-suffix=.php`

## Ressources

- **Plan complet** : `plan-refonteSystemeInstallation.prompt.md`
- **État d'avancement** : `INSTALLATION_REFACTOR_STATUS.md`
- **Code existant** : `galette/lib/Galette/Core/Install.php`
- **Tests** : `tests/Galette/Core/Installation/`
- **Documentation** : https://doc.galette.eu/

## Support

Pour toute question :
- GitHub Issues : https://github.com/galette/galette/issues
- Liste développeurs : galette-devel@mailman3.com
- IRC : #galette sur Libera.Chat

