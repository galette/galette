<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Access;

use ArrayObject;
use Galette\Entity\AbstractEntity;
use Galette\Entity\Attributes\Column;

/**
 * Role: a set of permissions, possibly inheriting another role's
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Role extends AbstractEntity
{
    public const string TABLE = 'roles';
    public const string PK = 'id_role';

    #[Column(self::PK)]
    protected int $id;

    //system roles are created at install, their key never changes
    #[Column('role_key', insertable: false, updatable: false)]
    private ?string $key = null;

    #[Column('name')]
    private string $name = '';

    #[Column('id_parent')]
    private ?int $parent = null;

    /** @var ?array<string> */
    private ?array $permissions = null;

    /**
     * Main constructor
     *
     * @param int|ArrayObject<string, int|string>|null $args Arguments
     */
    public function __construct(int|ArrayObject|null $args = null)
    {
        if (is_int($args)) {
            $this->load($args);
        } elseif ($args instanceof ArrayObject) {
            $this->loadFromRS($args);
        }
    }

    /**
     * Load role from a result set
     *
     * @param ArrayObject<string, int|string> $rs Result set
     */
    public function loadFromRS(ArrayObject $rs): static
    {
        $this->id = (int)$rs->{self::PK};
        $this->key = $rs->role_key === null ? null : (string)$rs->role_key;
        $this->name = (string)$rs->name;
        $this->parent = $rs->id_parent === null ? null : (int)$rs->id_parent;
        $this->permissions = null;
        return $this;
    }

    /**
     * Is role stored?
     */
    public function isLoaded(): bool
    {
        return isset($this->id);
    }

    /**
     * Is it a system role?
     */
    public function isSystem(): bool
    {
        return $this->key !== null;
    }

    /**
     * Get system role key
     */
    public function getKey(): ?string
    {
        return $this->key;
    }

    /**
     * Get stored name
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get name to display, translated for system roles
     */
    public function getLabel(): string
    {
        return $this->key !== null ? Roles::getSystemLabel($this->key) : $this->name;
    }

    /**
     * Set name; system roles names cannot change
     *
     * @param string $name Name
     */
    public function setName(string $name): self
    {
        if ($this->isSystem()) {
            throw new \RuntimeException('System roles cannot be renamed.');
        }
        $this->name = trim(strip_tags($name));
        return $this;
    }

    /**
     * Get parent role identifier
     */
    public function getParentId(): ?int
    {
        return $this->parent;
    }

    /**
     * Set parent role
     *
     * @param ?int $parent Parent role identifier
     */
    public function setParentId(?int $parent): self
    {
        if ($this->isSystem()) {
            throw new \RuntimeException('System roles parent cannot change.');
        }
        if ($parent !== null && $this->isLoaded() && $parent === $this->id) {
            throw new \InvalidArgumentException('A role cannot inherit from itself.');
        }
        $this->parent = $parent;
        return $this;
    }

    /**
     * Get permissions of the role, not counting inherited ones
     *
     * @return array<string>
     */
    public function getPermissions(): array
    {
        if ($this->permissions === null) {
            $this->permissions = [];
            if ($this->isLoaded()) {
                $select = $this->getDB()->select(Roles::PERMISSIONS_TABLE);
                $select->columns(['permission'])->where([self::PK => $this->id]);
                foreach ($this->getDB()->execute($select) as $row) {
                    $this->permissions[] = (string)$row->permission;
                }
                sort($this->permissions);
            }
        }
        return $this->permissions;
    }

    /**
     * Set permissions of the role, stored with the role
     *
     * @param array<string> $permissions Permissions names
     * @param Permissions   $catalog     Permissions catalog
     *
     * @throws \InvalidArgumentException when a permission does not exist
     */
    public function setPermissions(array $permissions, Permissions $catalog): self
    {
        foreach ($permissions as $permission) {
            if (!$catalog->has($permission)) {
                throw new \InvalidArgumentException(sprintf('Unknown permission "%s"', $permission));
            }
        }
        $permissions = array_values(array_unique($permissions));
        sort($permissions);
        $this->permissions = $permissions;
        return $this;
    }

    /**
     * Name is required
     */
    protected function preInsert(): bool
    {
        return $this->name !== '';
    }

    /**
     * Name is required
     */
    protected function preUpdate(): bool
    {
        return $this->name !== '';
    }

    /**
     * Store permissions
     */
    protected function postInsert(): bool
    {
        return $this->storePermissions();
    }

    /**
     * Store permissions
     */
    protected function postUpdate(): bool
    {
        return $this->storePermissions();
    }

    /**
     * System roles cannot be removed
     */
    protected function preDelete(): bool
    {
        if ($this->isSystem()) {
            throw new \RuntimeException('System roles cannot be removed.');
        }
        return true;
    }

    /**
     * Store permissions, replacing existing ones
     */
    private function storePermissions(): bool
    {
        if ($this->permissions === null) {
            //never loaded nor set: unchanged
            return true;
        }

        $zdb = $this->getDB();
        $delete = $zdb->delete(Roles::PERMISSIONS_TABLE);
        $delete->where([self::PK => $this->id]);
        $zdb->execute($delete);

        foreach ($this->permissions as $permission) {
            $insert = $zdb->insert(Roles::PERMISSIONS_TABLE);
            $insert->values([self::PK => $this->id, 'permission' => $permission]);
            $zdb->execute($insert);
        }
        return true;
    }
}
