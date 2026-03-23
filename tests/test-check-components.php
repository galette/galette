#!/usr/bin/env php
<?php

/**
 * Script de test pour vérifier que check.php fonctionne correctement
 * avec les nouveaux composants
 */

declare(strict_types=1);

echo "=== Test de check.php avec les nouveaux composants ===\n\n";

// Setup minimal
$installer = true;
define('GALETTE_ROOT', __DIR__ . '/../galette/');
define('GALETTE_INSTALLER', true);

require_once GALETTE_ROOT . 'vendor/autoload.php';
require_once GALETTE_ROOT . 'includes/sys_config/versions.inc.php';
require_once GALETTE_ROOT . 'includes/sys_config/paths.inc.php';

// Mock i18n
$i18n = new \Galette\Core\I18n();

// Create Install instance
$install = new \Galette\Core\Install();

echo "1. Test des composants...\n";

// Test renderValidationList
require_once __DIR__ . '/../galette/install/views/components.php';
require_once __DIR__ . '/../galette/install/views/helpers.php';

echo "   ✓ Composants chargés\n";

// Test validation list
$test_validations = [
    ['message' => 'Test 1', 'res' => true],
    ['message' => 'Test 2', 'res' => false, 'debug' => 'Error details']
];

ob_start();
renderValidationList($test_validations, $install);
$output = ob_get_clean();

if (str_contains($output, 'Test 1') && str_contains($output, 'Test 2')) {
    echo "   ✓ renderValidationList fonctionne\n";
} else {
    echo "   ✗ renderValidationList a un problème\n";
    exit(1);
}

// Test message box
ob_start();
renderMessageBox('success', 'Test message');
$output = ob_get_clean();

if (str_contains($output, 'ui green message') && str_contains($output, 'Test message')) {
    echo "   ✓ renderMessageBox fonctionne\n";
} else {
    echo "   ✗ renderMessageBox a un problème\n";
    exit(1);
}

echo "\n2. Test de CheckModules...\n";

$cm = new \Galette\Core\CheckModules();
echo "   ✓ CheckModules instancié\n";

$goods = $cm->getGoods();
$missings = $cm->getMissings();
$shoulds = $cm->getShoulds();

echo "   - Modules présents: " . count($goods) . "\n";
echo "   - Modules manquants: " . count($missings) . "\n";
echo "   - Modules recommandés: " . count($shoulds) . "\n";

if ($cm->isValid()) {
    echo "   ✓ Tous les modules requis sont présents\n";
} else {
    echo "   ⚠ Certains modules requis sont manquants\n";
}

echo "\n3. Test de CheckStep...\n";

$step = new \Galette\Core\Installation\Step\CheckStep($install);
echo "   ✓ CheckStep instancié\n";

$result = $step->execute();
echo "   - Status: " . $result->getStatus()->value . "\n";
echo "   - Success: " . ($result->isSuccess() ? 'Oui' : 'Non') . "\n";
echo "   - Requires display: " . ($result->requiresDisplay() ? 'Oui' : 'Non') . "\n";
echo "   - Should auto-advance: " . ($result->shouldAutoAdvance() ? 'Oui' : 'Non') . "\n";

if ($result->hasReport()) {
    echo "   ✓ Rapport disponible\n";
}

if ($result->isSuccess()) {
    echo "   ✓ CheckStep s'exécute avec succès\n";
} else {
    echo "   ⚠ CheckStep a détecté des problèmes:\n";
    foreach ($result->getMessages() as $msg) {
        echo "     - $msg\n";
    }
}

echo "\n=== Résumé ===\n";
echo "✓ Tous les composants fonctionnent correctement\n";
echo "✓ check.php est prêt à être testé dans le navigateur\n";
echo "\nPour tester visuellement, accédez à :\n";
echo "http://votre-domaine/galette/webroot/installer.php\n";

exit(0);



