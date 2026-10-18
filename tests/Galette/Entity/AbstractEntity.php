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

    /**
     * Raw database values provider
     *
     * @return array<string, array{mixed, bool}>
     */
    public static function booleansProvider(): array
    {
        return [
            'native true' => [true, true],
            'native false' => [false, false],
            'mysql true' => [1, true],
            'mysql false' => [0, false],
            'stringified true' => ['1', true],
            'stringified false' => ['0', false],
            'empty string' => ['', false],
            'pgsql true' => ['t', true],
            'pgsql false' => ['f', false],
        ];
    }

    /**
     * Test database values are converted to property types on load
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('booleansProvider')]
    public function testLoadBooleans(mixed $raw, bool $expected): void
    {
        $entity = new \Galette\Tests\Fixtures\TypedEntity();
        $entity->loadFromRS(new \ArrayObject(
            ['id_typed' => '3', 'flag' => $raw, 'number' => '42', 'optional' => null, 'label' => 'A label'],
            \ArrayObject::ARRAY_AS_PROPS
        ));

        $this->assertSame(3, $entity->getId());
        $this->assertSame($expected, $entity->flag);
        $this->assertSame(42, $entity->number);
        $this->assertNull($entity->optional);
        $this->assertSame('A label', $entity->label);
    }

    /**
     * Test booleans are sent in a form the current database engine accepts
     */
    public function testBooleansToDb(): void
    {
        global $zdb;
        $zdb = $this->zdb;

        $entity = new \Galette\Tests\Fixtures\TypedEntity();
        $method = new \ReflectionMethod(\Galette\Entity\AbstractEntity::class, 'toColumnData');

        $entity->flag = false;
        $data = $method->invoke($entity, \Galette\Entity\DTO\ColumnMapping::SCOPE_INSERT);
        $this->assertSame($this->zdb->isPostgres() ? 'false' : 0, $data['flag']);
        $this->assertSame(0, $data['number']);
        $this->assertSame('', $data['label']);

        $entity->flag = true;
        $data = $method->invoke($entity, \Galette\Entity\DTO\ColumnMapping::SCOPE_INSERT);
        $this->assertSame($this->zdb->isPostgres() ? 'true' : 1, $data['flag']);
    }
}
