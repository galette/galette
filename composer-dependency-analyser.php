<?php

/**
 * Copyright © 2003-2026 The Galette Team
 *
 * This file is part of Galette (https://galette.eu).
 *
 * Galette is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Galette is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Galette. If not, see <http://www.gnu.org/licenses/>.
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
