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

namespace Galette\Tests\Entity\DTO;

use Galette\Entity\DTO\ColumnMapping;
use Galette\Tests\GaletteTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Safe\DateTime;

/**
 * Test ColumnMapping DTO
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class ColumnMappingTest extends GaletteTestCase
{
    /**
     * Test DTO construction with all parameters
     */
    public function testConstructionWithAllParameters(): void
    {
        $mapping = new ColumnMapping(
            propertyName: 'firstName',
            columnName: 'first_name',
            value: 'John'
        );

        $this->assertSame('firstName', $mapping->propertyName);
        $this->assertSame('first_name', $mapping->columnName);
        $this->assertSame('John', $mapping->value);
    }

    /**
     * Test DTO construction with default value parameter
     */
    public function testConstructionWithDefaultValue(): void
    {
        $mapping = new ColumnMapping(
            propertyName: 'lastName',
            columnName: 'last_name'
        );

        $this->assertSame('lastName', $mapping->propertyName);
        $this->assertSame('last_name', $mapping->columnName);
        $this->assertNull($mapping->value, 'Default value should be null');
    }

    /**
     * Test DTO is readonly
     */
    public function testDTOIsReadonly(): void
    {
        $mapping = new ColumnMapping(
            propertyName: 'email',
            columnName: 'user_email',
            value: 'test@example.com'
        );

        $reflection = new \ReflectionClass($mapping);
        $this->assertTrue($reflection->isReadOnly(), 'ColumnMapping should be a readonly class');
    }

    /**
     * Test DTO is final
     */
    public function testDTOIsFinal(): void
    {
        $reflection = new \ReflectionClass(ColumnMapping::class);
        $this->assertTrue($reflection->isFinal(), 'ColumnMapping should be a final class');
    }

    /**
     * Test DTO with various value types
     */
    #[DataProvider('valueTypesProvider')]
    public function testWithVariousValueTypes(mixed $value, string $expectedType): void
    {
        $mapping = new ColumnMapping(
            propertyName: 'testProp',
            columnName: 'test_col',
            value: $value
        );

        $this->assertSame($value, $mapping->value);

        if ($expectedType === 'null') {
            $this->assertNull($mapping->value);
        } else {
            $this->assertNotNull($mapping->value);
        }
    }

    /**
     * Data provider for various value types
     *
     * @return array<string, list<array<string, string>|bool|float|int|string|null>>
     */
    public static function valueTypesProvider(): array
    {
        return [
            'string value' => ['test string', 'string'],
            'integer value' => [42, 'integer'],
            'float value' => [3.14, 'double'],
            'boolean true' => [true, 'boolean'],
            'boolean false' => [false, 'boolean'],
            'null value' => [null, 'null'],
            'array value' => [['key' => 'value'], 'array'],
            'empty string' => ['', 'string'],
            'zero integer' => [0, 'integer'],
        ];
    }

    /**
     * Test DTO with same property and column names
     */
    public function testWithSamePropertyAndColumnNames(): void
    {
        $mapping = new ColumnMapping(
            propertyName: 'id',
            columnName: 'id',
            value: 123
        );

        $this->assertSame('id', $mapping->propertyName);
        $this->assertSame('id', $mapping->columnName);
        $this->assertSame(123, $mapping->value);
    }

    /**
     * Test DTO with snake_case to camelCase mapping
     */
    public function testWithSnakeCaseToCamelCaseMapping(): void
    {
        $mapping = new ColumnMapping(
            propertyName: 'createdAt',
            columnName: 'created_at',
            value: '2026-03-20 14:30:00'
        );

        $this->assertSame('createdAt', $mapping->propertyName);
        $this->assertSame('created_at', $mapping->columnName);
        $this->assertSame('2026-03-20 14:30:00', $mapping->value);
    }

    /**
     * Test DTO properties are publicly accessible
     */
    public function testPropertiesArePublic(): void
    {
        $mapping = new ColumnMapping(
            propertyName: 'status',
            columnName: 'user_status',
            value: 'active'
        );

        // Direct property access should work
        $propertyName = $mapping->propertyName;
        $columnName = $mapping->columnName;
        $value = $mapping->value;

        $this->assertSame('status', $propertyName);
        $this->assertSame('user_status', $columnName);
        $this->assertSame('active', $value);
    }

    /**
     * Test DTO with object value
     */
    public function testWithObjectValue(): void
    {
        $dateTime = new DateTime('2026-03-20');

        $mapping = new ColumnMapping(
            propertyName: 'birthDate',
            columnName: 'birth_date',
            value: $dateTime
        );

        $this->assertSame('birthDate', $mapping->propertyName);
        $this->assertSame('birth_date', $mapping->columnName);
        $this->assertSame($dateTime, $mapping->value);
        $this->assertInstanceOf(\DateTime::class, $mapping->value);
    }

    /**
     * Test DTO with named arguments in different order
     */
    public function testWithNamedArgumentsInDifferentOrder(): void
    {
        $mapping = new ColumnMapping(
            value: 'test value',
            columnName: 'test_column',
            propertyName: 'testProperty'
        );

        $this->assertSame('testProperty', $mapping->propertyName);
        $this->assertSame('test_column', $mapping->columnName);
        $this->assertSame('test value', $mapping->value);
    }

    /**
     * Test DTO with unicode characters
     */
    public function testWithUnicodeCharacters(): void
    {
        $mapping = new ColumnMapping(
            propertyName: 'prénom',
            columnName: 'prenom',
            value: 'François'
        );

        $this->assertSame('prénom', $mapping->propertyName);
        $this->assertSame('prenom', $mapping->columnName);
        $this->assertSame('François', $mapping->value);
    }

    /**
     * Test multiple DTO instances are independent
     */
    public function testMultipleInstancesAreIndependent(): void
    {
        $mapping1 = new ColumnMapping(
            propertyName: 'prop1',
            columnName: 'col1',
            value: 'value1'
        );

        $mapping2 = new ColumnMapping(
            propertyName: 'prop2',
            columnName: 'col2',
            value: 'value2'
        );

        $this->assertNotSame($mapping1, $mapping2);
        $this->assertSame('prop1', $mapping1->propertyName);
        $this->assertSame('prop2', $mapping2->propertyName);
    }
}
