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
