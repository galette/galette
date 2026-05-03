<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Entity;

use Galette\Tests\GaletteTestCase;

/**
 * Test abstract entity
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class AbstractEntity extends GaletteTestCase
{
    /**
     * Test that all concrete Entity classes define TABLE and PK constants
     */
    public function testAllEntitiesDefineRequiredConstants(): void
    {
        $entityPath = __DIR__ . '/../../../galette/lib/Galette/Entity';
        $this->assertDirectoryExists($entityPath);
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($entityPath)
        );

        $errors = [];

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $className = 'Galette\\Entity\\' . $file->getBasename('.php');

            if (!class_exists($className)) {
                continue;
            }

            $reflection = new \ReflectionClass($className);

            // Skip abstract classes and non-AbstractEntity classes
            if ($reflection->isAbstract() || !$reflection->isSubclassOf(\Galette\Entity\AbstractEntity::class)) {
                continue;
            }

            // Check TABLE constant
            if (!$reflection->hasConstant('TABLE')) {
                $errors[] = "$className does not define TABLE constant";
            } elseif ($reflection->getConstant('TABLE') === '') {
                $errors[] = "$className has empty TABLE constant";
            }

            // Check PK constant
            if (!$reflection->hasConstant('PK')) {
                $errors[] = "$className does not define PK constant";
            } elseif ($reflection->getConstant('PK') === '') {
                $errors[] = "$className has empty PK constant";
            }
        }

        $this->assertEmpty(
            $errors,
            "Entity validation errors:\n" . implode("\n", $errors)
        );
    }
}
