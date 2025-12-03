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

namespace Galette\Entity;

use Analog\Analog;
use ArrayObject;
use DI\Attribute\Inject;
use Galette\Core\Db;
use Galette\Entity\Attributes\Column;
use Galette\Entity\DTO\ColumnMapping;
use Galette\Exception\EntityException;
use ReflectionClass;
use ReflectionNamedType;
use Throwable;

/**
 * Abstract entity class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
abstract class AbstractEntity
{
    /**
     * Database table name
     * MUST be overridden in child classes
     */
    public const string TABLE = '';

    /**
     * Primary key field name
     * MUST be overridden in child classes
     */
    public const string PK = '';

    //must be declared in child class with the appropriate Column attribute
    protected int $id;

    #[Inject]
    protected Db $zdb;

    /**
     * Get id
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Get Entity table name.
     * Must be defined in child class as TABLE constant.
     *
     * @return non-empty-string
     */
    public function getTableName(): string
    {
        if (static::TABLE === '') {
            throw new EntityException(
                sprintf('Constant TABLE must be defined in %s', static::class)
            );
        }
        return static::TABLE;
    }

    /**
     * Get Primary Key field name.
     * Must be defined in child class as PK constant.
     *
     * @return non-empty-string
     */
    public function getPkField(): string
    {
        if (static::PK === '') {
            throw new EntityException(
                sprintf('Constant PK must be defined in %s', static::class)
            );
        }
        return static::PK;
    }

    /**
     * Save entity (insert or update)
     */
    public function save(): bool
    {
        if (isset($this->id) && $this->id > 0) {
            return $this->update();
        }
        return $this->insert();
    }

    /**
     * Store entity in database
     *
     * @param Db $zdb Database instance
     * @deprecated 1.3.0 Use save() instead
     */
    public function store(Db $zdb): bool
    {
        if (!isset($this->zdb)) {
            $this->zdb = $zdb;
        }
        return $this->save();
    }

    /**
     * Instructions to be processed before insert
     */
    protected function preInsert(): bool
    {
        //to be overridden in child class if needed
        return true;
    }

    /**
     * Insert in database
     */
    private function insert(): bool
    {
        $zdb = $this->getDB();
        $data = $this->toColumnData(ColumnMapping::SCOPE_INSERT);
        try {
            $zdb->beginTransaction();

            if (!$this->preInsert()) {
                $zdb->rollBack();
                Analog::log('preInsert failed!', Analog::ERROR);
                return false;
            }

            unset($data['id'], $data[static::PK]);

            $insert = $zdb->insert(static::TABLE);
            $insert->values($data);
            $add = $zdb->execute($insert);
            if ($add->count() <= 0) {
                $zdb->rollBack();
                Analog::log('Insert failed', Analog::ERROR);
                return false;
            }
            $this->id = $zdb->getLastGeneratedValue($this);

            if (!$this->postInsert()) {
                $zdb->rollBack();
                Analog::log('postInsert failed!', Analog::ERROR);
                return false;
            }

            $zdb->commit();
            return true;
        } catch (Throwable $e) {
            $zdb->rollBack();
            $msg = sprintf(
                "An error occurred inserting %s: %s\n%s",
                static::class,
                $e->getMessage(),
                var_export($data, true)
            );
            Analog::log($msg, Analog::ERROR);
            throw new EntityException($msg, $e->getCode(), $e);
        }
    }

    /**
     * Instructions to be processed after insert
     */
    protected function postInsert(): bool
    {
        //to be overridden in child class if needed
        return true;
    }

    /**
     * Load an entity from its PK
     */
    public function load(int $id): static
    {
        $zdb = $this->getDB();
        try {
            $select = $zdb->select(static::TABLE);
            $select->limit(1)->where([static::PK => $id]);

            $results = $zdb->execute($select);
            $res = $results->current();

            $this->loadFromRS($res);
        } catch (Throwable $e) {
            $msg = sprintf(
                "An error occurred loading %s #%s Message:\n%s",
                static::class,
                $id,
                $e->getMessage()
            );
            Analog::log($msg, Analog::ERROR);
            throw new EntityException($msg, $e->getCode(), $e);
        }

        return $this;
    }

    /**
     * Load entity from a db ResultSet
     *
     * @param ArrayObject<string, int|string> $rs ResultSet
     */
    public function loadFromRS(ArrayObject $rs): static
    {
        $reflection = new ReflectionClass($this);
        $data = $this->getColumnMapping(ColumnMapping::SCOPE_NONE);

        $this->id = (int)$rs->{static::PK};
        if (property_exists($this, static::PK)) {
            $this->{static::PK} = $this->id;
        }
        unset($data['id'], $data[static::PK]);
        foreach ($data as $property => $column) {
            $prop = $reflection->getProperty($property);
            $prop->setValue($this, $rs->{$column->columnName});
        }

        return $this;
    }

    /**
     * Instructions to be processed before update
     */
    protected function preUpdate(): bool
    {
        //to be overridden in child class if needed
        return true;
    }

    /**
     * Update in database
     */
    private function update(): bool
    {
        $zdb = $this->getDB();
        $data = $this->toColumnData(ColumnMapping::SCOPE_UPDATE);
        try {
            $zdb->beginTransaction();
            if (!$this->preUpdate()) {
                Analog::log('preUpdate failed!', Analog::ERROR);
                $zdb->rollBack();
                return false;
            }
            unset($data['id'], $data[static::PK]);

            $update = $zdb->update(static::TABLE);
            $update->set($data)->where([static::PK => $this->id ?? $this->{static::PK}]);
            $zdb->execute($update);

            if (!$this->postUpdate()) {
                Analog::log('postUpdate failed!', Analog::ERROR);
                $zdb->rollBack();
                return false;
            }

            $zdb->commit();
            return true;
        } catch (Throwable $e) {
            $zdb->rollBack();
            $msg = sprintf(
                "An error occurred updating %s: %s\n%s",
                static::class,
                $e->getMessage(),
                var_export($data, true)
            );
            Analog::log($msg, Analog::ERROR);
            throw new EntityException($msg, $e->getCode(), $e);
        }
    }

    /**
     * Instructions to be processed after update
     */
    protected function postUpdate(): bool
    {
        //to be overridden in child class if needed
        return true;
    }

    /**
     * Instructions to be processed before delete
     */
    protected function preDelete(): bool
    {
        //to be overridden in child class if needed
        return true;
    }

    /**
     * Update in database
     */
    public function delete(): bool
    {
        $zdb = $this->getDB();
        $id = $this->id ?? $this->{static::PK};
        try {
            $zdb->beginTransaction();

            if (!$this->preDelete()) {
                $zdb->rollBack();
                Analog::log('preDelete failed!', Analog::ERROR);
                return false;
            }

            $delete = $zdb->delete(static::TABLE);
            $delete->where([static::PK => $id]);
            $zdb->execute($delete);

            if (!$this->postDelete()) {
                $zdb->rollBack();
                Analog::log('postDelete failed!', Analog::ERROR);
                return false;
            }

            $zdb->commit();
            return true;
        } catch (Throwable $e) {
            $zdb->rollBack();
            $msg = sprintf(
                'Unable to delete %s #%s',
                static::class,
                $id
            );
            Analog::log($msg, Analog::ERROR);
            throw new EntityException($msg, $e->getCode(), $e);
        }
    }

    /**
     * Instructions to be processed after delete
     */
    protected function postDelete(): bool
    {
        //to be overridden in child class if needed
        return true;
    }

    /**
     * Get mapped columns information from entity properties using #[Column] attribute.
     *
     * @param int $scope ColumnMapping::SCOPE_*
     * @return array<string, ColumnMapping> Array of ColumnMapping objects indexed by property name
     */
    private function getColumnMapping(int $scope): array
    {
        $reflection = new ReflectionClass($this);
        $data = [];

        foreach ($reflection->getProperties() as $prop) {
            if ($prop->isStatic()) {
                continue;
            }

            // Check if property has #[Column] attribute
            $attributes = $prop->getAttributes(Column::class);
            if (empty($attributes)) {
                continue;
            }

            /** @var Column $column */
            $column = $attributes[0]->newInstance();

            if (!$this->shouldIncludeColumn($column, $scope)) {
                continue;
            }

            /** @var ReflectionNamedType $php_type */
            $php_type = $prop->getType();
            $type = $php_type->getName();
            $this->validateColumnType($column, $type);

            $data[$prop->getName()] = new ColumnMapping(
                propertyName: $prop->getName(),
                columnName: $column->getColumnName($prop->getName()),
                value: $prop->isInitialized($this) ? $prop->getValue($this) : null,
                type: $type
            );
        }

        return $data;
    }

    /**
     * Check if column should be included based on scope
     */
    private function shouldIncludeColumn(Column $column, int $scope): bool
    {
        return match ($scope) {
            ColumnMapping::SCOPE_INSERT => $column->insertable,
            ColumnMapping::SCOPE_UPDATE => $column->updatable,
            ColumnMapping::SCOPE_ALL => $column->insertable || $column->updatable,
            default => true
        };
    }

    /**
     * Validate that column type matches property type
     */
    private function validateColumnType(Column $column, string $type): void
    {
        $column_type = $column->getType() ?? $type;
        if ($column_type !== $type) {
            throw new EntityException(
                sprintf(
                    'Property type %s does not match type on Column %s',
                    $type,
                    $column->getType()
                )
            );
        }
    }

    /**
     * Get property values mapped to their database column names.
     * Used for INSERT/UPDATE/DELETE operations.
     *
     * @param int $scope ColumnMapping::SCOPE_*
     * @return array<string, mixed> Array of column_name => value (e.g., ['nom_colonne' => 'valeur'])
     */
    private function toColumnData(int $scope): array
    {
        $mappings = $this->getColumnMapping($scope);
        $data = [];
        foreach ($mappings as $mapping) {
            $data[$mapping->columnName] = $mapping->value;
        }
        return $data;
    }

    /**
     * Get Db instance
     * Fix until everything is loaded form the DI and injection works
     */
    protected function getDB(): Db
    {
        if (!isset($this->zdb)) {
            global $zdb;
            $this->zdb = $zdb;
        }
        return $this->zdb;
    }
}
