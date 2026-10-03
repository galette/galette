<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Entity;

use ArrayObject;
use Galette\Entity\AbstractEntity;
use Galette\Entity\Attributes\Column;
use Galette\Entity\DTO\ColumnMapping;
use Galette\Tests\GaletteTestCase;

/**
 * Test column mapping functionality in AbstractEntity
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class ColumnMappingTest extends GaletteTestCase
{
    /**
     * Test that ColumnMapping DTO is properly constructed
     */
    public function testColumnMappingDTOConstruction(): void
    {
        $mapping = new ColumnMapping(
            propertyName: 'firstName',
            columnName: 'first_name',
            value: 'John',
            type: Column::TYPE_STRING
        );

        $this->assertSame('firstName', $mapping->propertyName);
        $this->assertSame('first_name', $mapping->columnName);
        $this->assertSame('John', $mapping->value);
        $this->assertSame(Column::TYPE_STRING, $mapping->type);
    }

    /**
     * Test that ColumnMapping DTO handles null values
     */
    public function testColumnMappingDTOWithNullValue(): void
    {
        $mapping = new ColumnMapping(
            propertyName: 'middleName',
            columnName: 'middle_name',
            value: null,
            type: Column::TYPE_STRING
        );

        $this->assertSame('middleName', $mapping->propertyName);
        $this->assertSame('middle_name', $mapping->columnName);
        $this->assertNull($mapping->value);
        $this->assertSame(Column::TYPE_STRING, $mapping->type);
    }

    /**
     * Test that ColumnMapping DTO with default value
     */
    public function testColumnMappingDTOWithDefaultValue(): void
    {
        $mapping = new ColumnMapping(
            propertyName: 'lastName',
            columnName: 'last_name'
        );

        $this->assertSame('lastName', $mapping->propertyName);
        $this->assertSame('last_name', $mapping->columnName);
        $this->assertNull($mapping->value);
        $this->assertSame(Column::TYPE_STRING, $mapping->type);
    }

    /**
     * Test getColumnMapping for insert operation
     */
    public function testGetColumnMappingForInsert(): void
    {
        $entity = new TestEntityWithColumns();
        $entity->setName('Test Name');
        $entity->setEmail('test@example.com');
        $entity->setCreatedAt('2026-03-20');

        $reflection = new \ReflectionMethod($entity, 'getColumnMapping');

        $mappings = $reflection->invoke($entity, ColumnMapping::SCOPE_INSERT);

        $this->assertIsArray($mappings);
        $this->assertContainsOnlyInstancesOf(ColumnMapping::class, $mappings);

        // Check that id is NOT present (insertable, but always remove as the PK autoincrement)
        $this->assertArrayNotHasKey('id', $mappings);

        // Check name property
        $this->assertArrayHasKey('name', $mappings);
        $this->assertSame('name', $mappings['name']->propertyName);
        $this->assertSame('test_name', $mappings['name']->columnName);
        $this->assertSame('Test Name', $mappings['name']->value);

        // Check email property
        $this->assertArrayHasKey('email', $mappings);
        $this->assertSame('email', $mappings['email']->propertyName);
        $this->assertSame('email', $mappings['email']->columnName);
        $this->assertSame('test@example.com', $mappings['email']->value);

        // Check that createdAt is included (insertable=true)
        $this->assertArrayHasKey('createdAt', $mappings);
        $this->assertSame('2026-03-20', $mappings['createdAt']->value);

        // Check that updatedAt is NOT included (insertable=false)
        $this->assertArrayNotHasKey('updatedAt', $mappings);

        // Check that static properties are not included
        $this->assertArrayNotHasKey('staticProp', $mappings);

        // Check that properties without Column attribute are not included
        $this->assertArrayNotHasKey('nonMappedProp', $mappings);
    }

    /**
     * Test getColumnMapping for update operation
     */
    public function testGetColumnMappingForUpdate(): void
    {
        $entity = new TestEntityWithColumns();
        $entity->setName('Updated Name');
        $entity->setEmail('updated@example.com');
        $entity->setUpdatedAt('2026-03-20');

        $reflection = new \ReflectionMethod($entity, 'getColumnMapping');

        $mappings = $reflection->invoke($entity, ColumnMapping::SCOPE_UPDATE);

        $this->assertIsArray($mappings);
        $this->assertContainsOnlyInstancesOf(ColumnMapping::class, $mappings);

        // Check that id is NOT present (updatable=false)
        $this->assertArrayNotHasKey('id', $mappings);

        // Check name property is present (updatable=true)
        $this->assertArrayHasKey('name', $mappings);
        $this->assertSame('Updated Name', $mappings['name']->value);

        // Check email property is present (updatable=true)
        $this->assertArrayHasKey('email', $mappings);
        $this->assertSame('updated@example.com', $mappings['email']->value);

        // Check that updatedAt is included (updatable=true)
        $this->assertArrayHasKey('updatedAt', $mappings);
        $this->assertSame('2026-03-20', $mappings['updatedAt']->value);

        // Check that createdAt is NOT included (updatable=false)
        $this->assertArrayNotHasKey('createdAt', $mappings);
    }

    /**
     * Test toColumnData returns correct format for insert
     */
    public function testToColumnDataForInsert(): void
    {
        $entity = new TestEntityWithColumns();
        $entity->setName('John Doe');
        $entity->setEmail('john@example.com');
        $entity->setCreatedAt('2026-03-20');

        $reflection = new \ReflectionMethod($entity, 'toColumnData');

        $data = $reflection->invoke($entity, ColumnMapping::SCOPE_INSERT);

        $this->assertIsArray($data);

        // Check array keys are column names, not property names
        // id should NOT be present (insertable=false, auto-increment)
        $this->assertArrayNotHasKey('test_id', $data);
        $this->assertArrayHasKey('test_name', $data);
        $this->assertArrayHasKey('email', $data);
        $this->assertArrayHasKey('created_at', $data);

        // Check values
        $this->assertSame('John Doe', $data['test_name']);
        $this->assertSame('john@example.com', $data['email']);
        $this->assertSame('2026-03-20', $data['created_at']);

        // updatedAt should NOT be present (insertable=false)
        $this->assertArrayNotHasKey('updated_at', $data);
    }

    /**
     * Test toColumnData returns correct format for update
     */
    public function testToColumnDataForUpdate(): void
    {
        $entity = new TestEntityWithColumns();
        $entity->setName('Jane Doe');
        $entity->setEmail('jane@example.com');
        $entity->setUpdatedAt('2026-03-21');

        $reflection = new \ReflectionMethod($entity, 'toColumnData');

        $data = $reflection->invoke($entity, ColumnMapping::SCOPE_UPDATE);

        $this->assertIsArray($data);

        // Check columns for update
        $this->assertArrayHasKey('test_name', $data);
        $this->assertArrayHasKey('email', $data);
        $this->assertArrayHasKey('updated_at', $data);

        // Check values
        $this->assertSame('Jane Doe', $data['test_name']);
        $this->assertSame('jane@example.com', $data['email']);
        $this->assertSame('2026-03-21', $data['updated_at']);

        // id and createdAt should NOT be present (updatable=false)
        $this->assertArrayNotHasKey('test_id', $data);
        $this->assertArrayNotHasKey('created_at', $data);
    }

    /**
     * Test that uninitialized properties return null in ColumnMapping
     */
    public function testUninitializedPropertiesReturnNull(): void
    {
        $entity = new TestEntityWithColumns();
        // Don't initialize any properties

        $reflection = new \ReflectionMethod($entity, 'getColumnMapping');

        $mappings = $reflection->invoke($entity, ColumnMapping::SCOPE_INSERT);

        // Check that uninitialized properties have null values
        $this->assertArrayHasKey('name', $mappings);
        $this->assertNull($mappings['name']->value);

        $this->assertArrayHasKey('email', $mappings);
        $this->assertNull($mappings['email']->value);
    }

    /**
     * Test loadFromRS uses toPropertyMap correctly
     */
    public function testLoadFromRSUsesToPropertyMap(): void
    {
        $entity = new TestEntityWithColumns();

        $rs = new ArrayObject([
            'test_id' => 42,
            'test_name' => 'Loaded Name',
            'email' => 'loaded@example.com',
            'created_at' => '2026-03-15',
            'updated_at' => '2026-03-20'
        ], ArrayObject::ARRAY_AS_PROPS);

        $entity->loadFromRS($rs);

        $this->assertSame(42, $entity->getId());
        $this->assertSame('Loaded Name', $entity->getName());
        $this->assertSame('loaded@example.com', $entity->getEmail());
        // Note: loadFromRS uses getColumnMapping(ColumnMapping::SCOPE_NONE),
        // which includes all #[Column] properties (including createdAt and updatedAt), even if they are not insertable or not updatable.
    }

    /**
     * Test that static properties are ignored in mapping
     */
    public function testStaticPropertiesAreIgnored(): void
    {
        $entity = new TestEntityWithColumns();

        $reflection = new \ReflectionMethod($entity, 'getColumnMapping');

        $mappings = $reflection->invoke($entity, ColumnMapping::SCOPE_INSERT);

        // Static property should not be in mappings
        $this->assertArrayNotHasKey('staticProp', $mappings);
    }

    /**
     * Test that properties without Column attribute are ignored
     */
    public function testPropertiesWithoutColumnAttributeAreIgnored(): void
    {
        $entity = new TestEntityWithColumns();
        $entity->setNonMappedProp('should not appear');

        $reflection = new \ReflectionMethod($entity, 'getColumnMapping');

        $mappings = $reflection->invoke($entity, ColumnMapping::SCOPE_INSERT);

        // Non-mapped property should not be in mappings
        $this->assertArrayNotHasKey('nonMappedProp', $mappings);
    }

    /**
     * Test custom column names are used correctly
     */
    public function testCustomColumnNamesAreUsed(): void
    {
        $entity = new TestEntityWithColumns();
        $entity->setName('Test');

        $reflection = new \ReflectionMethod($entity, 'toColumnData');

        $data = $reflection->invoke($entity, ColumnMapping::SCOPE_INSERT);

        // Check custom column names
        $this->assertArrayNotHasKey('test_id', $data); // NOT present because insertable=false
        $this->assertArrayHasKey('test_name', $data); // custom name from Column attribute
        $this->assertArrayHasKey('email', $data); // default to property name when not specified
    }
}

// phpcs:disable PSR1.Classes.ClassDeclaration.MultipleClasses
/**
 * Concrete test entity for testing column mapping
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class TestEntityWithColumns extends AbstractEntity
{
    public const string TABLE = 'test_table';
    public const string PK = 'test_id';

    #[Column(name: 'test_id', type: Column::TYPE_INT, insertable: false, updatable: false)]
    protected int $id;

    #[Column(name: 'test_name', type: Column::TYPE_STRING, insertable: true, updatable: true)]
    private string $name;

    #[Column(type: Column::TYPE_STRING, insertable: true, updatable: true)]
    private string $email;

    #[Column(name: 'created_at', type: Column::TYPE_STRING, insertable: true, updatable: false)]
    private ?string $createdAt = null; //@phpstan-ignore property.onlyWritten (just a test case)

    #[Column(name: 'updated_at', type: Column::TYPE_STRING, insertable: false, updatable: true)]
    private ?string $updatedAt = null; //@phpstan-ignore property.onlyWritten (just a test case)

    // Property without Column attribute - should be ignored
    private string $nonMappedProp = 'ignored'; //@phpstan-ignore property.onlyWritten (just a test case)

    // Static property - should be ignored
    private static string $staticProp = 'also ignored'; //@phpstan-ignore property.onlyWritten (just a test case)

    /** Set name */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /** Get name */
    public function getName(): string
    {
        return $this->name;
    }

    /** Set email */
    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    /** Get email */
    public function getEmail(): string
    {
        return $this->email;
    }

    /** Set creation date */
    public function setCreatedAt(string $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    /** Set update date */
    public function setUpdatedAt(string $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    /** Set property non managed */
    public function setNonMappedProp(string $value): void
    {
        $this->nonMappedProp = $value;
    }
}
// phpcs:enable
