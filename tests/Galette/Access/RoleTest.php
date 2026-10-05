<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Access\test\units;

use Galette\Access\Permissions;
use Galette\Access\Role;
use Galette\Access\Roles;
use Galette\Tests\GaletteTestCase;

/**
 * Role tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class RoleTest extends GaletteTestCase
{
    protected int $seed = 20261006;
    private Roles $roles;

    /**
     * Set up tests
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->roles = new Roles($this->zdb, new Permissions());
        $this->roles->installInit($this->preferences);
    }

    /**
     * Tear down tests
     */
    public function tearDown(): void
    {
        $this->cleanMembers();
        parent::tearDown();
    }

    /**
     * Create a custom role
     *
     * @param string        $name        Name
     * @param ?int          $parent      Parent role
     * @param array<string> $permissions Permissions
     */
    private function createRole(string $name, ?int $parent = null, array $permissions = []): Role
    {
        $role = new Role();
        $role->setName($name)
            ->setParentId($parent)
            ->setPermissions($permissions, new Permissions());
        $this->assertTrue($role->store($this->zdb));
        return $role;
    }

    /**
     * Test custom roles are stored with their permissions
     */
    public function testStore(): void
    {
        $role = $this->createRole('<b>Treasurer</b>', 4, ['contribution:edit', 'contribution:create']);
        $this->assertFalse($role->isSystem());

        $role = new Role($role->getId());
        $this->assertSame('Treasurer', $role->getName());
        $this->assertSame('Treasurer', $role->getLabel());
        $this->assertSame(4, $role->getParentId());
        $this->assertSame(['contribution:create', 'contribution:edit'], $role->getPermissions());

        $role->setPermissions(['member:read'], new Permissions())->setParentId(null);
        $this->assertTrue($role->store($this->zdb));
        $role = new Role($role->getId());
        $this->assertNull($role->getParentId());
        $this->assertSame(['member:read'], $role->getPermissions());

        $this->assertTrue($role->delete());
        $select = $this->zdb->select(Roles::PERMISSIONS_TABLE);
        $select->where([Roles::PK => $role->getId()]);
        $this->assertCount(0, $this->zdb->execute($select));
    }

    /**
     * Test name is required and permissions must exist
     */
    public function testValidation(): void
    {
        $role = new Role();
        $role->setName('  ');
        $this->assertFalse($role->store($this->zdb));
        $this->expectLogEntry(\Analog\Analog::ERROR, 'preInsert failed!');

        $this->expectException(\InvalidArgumentException::class);
        $role->setPermissions(['member:fly'], new Permissions());
    }

    /**
     * Test system roles cannot be renamed nor removed
     */
    public function testSystemRole(): void
    {
        $role = new Role(4);
        $this->assertTrue($role->isSystem());
        $this->assertSame(Roles::STAFF, $role->getKey());
        $this->assertSame('Staff member', $role->getLabel());

        try {
            $role->setName('Board');
            $this->fail('System role renamed');
        } catch (\RuntimeException) {
            //expected
        }

        $this->expectException(\Galette\Exception\EntityException::class);
        try {
            $role->delete();
        } finally {
            $this->expectLogEntry(\Analog\Analog::ERROR, 'Unable to delete Galette\\Access\\Role #4');
        }
    }

    /**
     * Test inheritance helpers
     */
    public function testInheritance(): void
    {
        $deputy = $this->createRole('Deputy', 4, ['member:create']);
        $assistant = $this->createRole('Assistant', $deputy->getId());

        $inherited = $this->roles->getInheritedPermissions($assistant);
        $this->assertContains('member:create', $inherited);
        $this->assertContains('contribution:delete', $inherited);
        $this->assertSame([], $this->roles->getInheritedPermissions(new Role(1)));

        $parents = array_keys($this->roles->getPossibleParents($deputy));
        $this->assertNotContains($deputy->getId(), $parents);
        $this->assertNotContains($assistant->getId(), $parents);
        $this->assertContains(4, $parents);
        $this->assertContains($assistant->getId(), array_keys($this->roles->getPossibleParents(new Role())));

        $this->expectException(\InvalidArgumentException::class);
        $deputy->setParentId($deputy->getId());
    }

    /**
     * Test giving roles to members
     */
    public function testGive(): void
    {
        $member = $this->getMemberOne();
        $role = $this->createRole('Treasurer');

        $this->logSuperAdmin();
        $group = new \Galette\Entity\Group();
        $group->setName('Treasury');
        $this->assertTrue($group->store());
        $this->login->logOut();

        $this->assertTrue($this->roles->give((int)$member->id, $role->getId()));
        $this->assertFalse($this->roles->give((int)$member->id, $role->getId()));
        $this->assertTrue($this->roles->give((int)$member->id, $role->getId(), $group->getId()));

        $given = $this->roles->getMembersOf($role->getId());
        $this->assertCount(2, $given);
        $first = reset($given);
        $this->assertSame((int)$member->id, $first['id_adh']);
        $this->assertSame('Durand René', $first['member']);
        $this->assertNull($first['id_group']);
        $last = end($given);
        $this->assertSame('Treasury', $last['group']);

        $this->assertFalse($this->roles->take(1, $first['id']));
        $this->assertTrue($this->roles->take($role->getId(), $first['id']));
        $this->assertCount(1, $this->roles->getMembersOf($role->getId()));
    }
}
