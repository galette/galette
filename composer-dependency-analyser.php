<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

$config = new Configuration();

return $config
    ->addPathsToScan([
        'galette/includes',
        'galette/install',
        'galette/webroot',
        'stubs',
        // 'galette/lib' Loaded from the autoloader
    ], false)

    ->ignoreUnknownClasses(['GalettePaypal\Paypal', 'XHProfRuns_Default'])

    // Only loaded in a conditional block that checks if the environment is dev
    ->ignoreErrorsOnPackages([
        'alisqi/twigqi',
    ], [ErrorType::DEV_DEPENDENCY_IN_PROD])

    ->ignoreErrorsOnExtension('ext-simplexml', [ErrorType::UNUSED_DEPENDENCY]) // Required; used from Safe lib
    ->ignoreErrorsOnPackages([
        'symfony/polyfill-php80', //Required by some Slim packages
        'laminas/laminas-stdlib', // Required by... Laminas...
        'laminas/laminas-servicemanager', // Required by... Laminas...
    ], [ErrorType::UNUSED_DEPENDENCY])
;
