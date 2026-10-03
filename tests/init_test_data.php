<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

/**
 * Initialize tests-data directory
 */

require_once __DIR__ . '/test_env.inc.php';

if (is_dir(GALETTE_DATA_PATH)) {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            GALETTE_DATA_PATH,
            RecursiveDirectoryIterator::SKIP_DOTS
        ),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($files as $fileinfo) {
        $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
        $todo($fileinfo->getRealPath());
    }
    rmdir(GALETTE_DATA_PATH); //@phpstan-ignore theCodingMachineSafe.function
}

mkdir(GALETTE_DATA_PATH); //@phpstan-ignore theCodingMachineSafe.function
$directories = [
    'logs',
    'templates_c',
    'cache',
    'exports',
    'imports',
    'photos',
    'attachments',
    'files',
    'tempimages',
    'plugins',
    'documents'
];
foreach ($directories as $directory) {
    mkdir(GALETTE_DATA_PATH . $directory); //@phpstan-ignore theCodingMachineSafe.function
}
