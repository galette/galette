<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Access\test\units;

use Galette\Access\Permissions;
use Galette\Access\Roles;
use Galette\Tests\GaletteTestCase;

/**
 * Roles tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class RolesTest extends GaletteTestCase
{
    protected int $seed = 20261011;

    /**
     * Tear down tests
     */
    public function tearDown(): void
    {
        $this->cleanMembers();
        parent::tearDown();
    }

    /**
     * Get former rights settings, all on or off
     *
     * @param bool $value Value
     *
     * @return array<string, bool>
     */
    private function getRights(bool $value): array
    {
        return array_fill_keys(array_keys(Roles::LEGACY_RIGHTS), $value);
    }

    /**
     * Test system roles labels
     */
    public function testSystemLabels(): void
    {
        $this->assertSame('Group manager', Roles::getSystemLabel(Roles::MANAGER));

        $this->expectException(\InvalidArgumentException::class);
        Roles::getSystemLabel('unknown');
    }

    /**
     * Test default permissions follow levels and former rights settings
     */
    public function testDefaultPermissions(): void
    {
        $roles = new Roles($this->zdb, new Permissions());
        $all = array_keys((new Permissions())->getAll());
        $admin = array_keys(array_filter(
            (new Permissions())->getAll(),
            fn($permission): bool => $permission->level === \Galette\Core\Authentication::ACCESS_ADMIN
        ));
        //members creating their children is granted from former rights settings only
        $staff = array_values(array_diff($all, $admin, ['member:create-child']));

        $this->assertSame(
            [
                Roles::MEMBER => [],
                Roles::UPTODATE => [],
                Roles::MANAGER => ['member:read', 'group:read'],
                Roles::STAFF => $staff,
                Roles::ADMIN => $admin,
            ],
            $roles->getDefaultPermissions($this->getRights(false))
        );

        $this->assertSame(
            [
                Roles::MEMBER => ['member:create-child'],
                Roles::UPTODATE => [],
                Roles::MANAGER => [
                    'member:create',
                    'member:read',
                    'member:edit',
                    'member:export',
                    'member:print',
                    'contribution:create',
                    'contribution:read',
                    'transaction:create',
                    'transaction:read',
                    'transaction:attach',
                    'group:read',
                    'group:edit',
                    'group:export',
                    'mailing:send',
                ],
                Roles::STAFF => $staff,
                Roles::ADMIN => $admin,
            ],
            $roles->getDefaultPermissions($this->getRights(true))
        );

        //groups managers export by default
        $this->assertSame(
            ['member:read', 'member:export', 'member:print', 'group:read', 'group:export'],
            $roles->getDefaultPermissions()[Roles::MANAGER]
        );
    }

    /**
     * Test system roles are stored with their permissions
     */
    public function testInstallInit(): void
    {
        $roles = new Roles($this->zdb, new Permissions());
        $this->assertTrue($roles->installInit($this->getRights(false)));

        $select = $this->zdb->select(Roles::TABLE);
        $select->order(Roles::PK);
        $stored = [];
        foreach ($this->zdb->execute($select) as $row) {
            $stored[$row->role_key] = $row->id_parent === null ? null : (int)$row->id_parent;
        }
        $this->assertSame(
            [
                Roles::MEMBER => null,
                Roles::UPTODATE => 1,
                Roles::MANAGER => 1,
                Roles::STAFF => 1,
                Roles::ADMIN => 4,
            ],
            $stored
        );

        $this->assertSame(['group:read', 'member:read'], $this->getStoredPermissions(3));
    }

    /**
     * Test roles of a member, computed and given
     */
    public function testGetRolesOf(): void
    {
        $roles = new Roles($this->zdb, new Permissions());
        $this->assertTrue($roles->installInit());

        $member = $this->getMemberOne();
        $group = new \Galette\Entity\Group();
        $group->setName('Roles group');
        $this->assertTrue($group->store());
        $this->assertTrue($group->setManagers([$member]));

        $role = new \Galette\Access\Role();
        $role->setName('Treasurer');
        $this->assertTrue($role->store($this->zdb));
        $this->assertTrue($roles->give($member->id, $role->getId(), $group->getId()));
        $this->assertTrue($roles->give($member->id, $role->getId()));

        $member = new \Galette\Entity\Adherent($this->zdb, $member->id);
        $list = array_map(
            fn(array $entry): array => [$entry['role']->getLabel(), $entry['group'], $entry['given']],
            $roles->getRolesOf($member)
        );
        $expected = [['Member', null, false]];
        if ($member->isUp2Date()) {
            $expected[] = ['Up to date member', null, false];
        }
        $expected[] = ['Group manager', 'Roles group', false];
        $expected[] = ['Treasurer', null, true];
        $expected[] = ['Treasurer', 'Roles group', true];
        $this->assertSame($expected, $list);
    }

    /**
     * Get stored permissions of a role
     *
     * @param int $role Role identifier
     *
     * @return array<string>
     */
    private function getStoredPermissions(int $role): array
    {
        $select = $this->zdb->select(Roles::PERMISSIONS_TABLE);
        $select->where([Roles::PK => $role]);
        $permissions = [];
        foreach ($this->zdb->execute($select) as $row) {
            $permissions[] = $row->permission;
        }
        sort($permissions);
        return $permissions;
    }

    /**
     * Test system roles get default permissions of new permissions, once
     */
    public function testSyncPermissions(): void
    {
        $core = new Permissions();
        (new Roles($this->zdb, $core))->installInit($this->getRights(false));

        $select = $this->zdb->select(Roles::KNOWN_TABLE);
        $this->assertCount(count($core->getAll()), $this->zdb->execute($select));
        $this->assertSame([], (new Roles($this->zdb, $core))->syncPermissions());

        //test plugin is active
        $roles = new Roles($this->zdb, $this->container->get(Permissions::class));
        $this->assertSame(['plugin1:read', 'plugin1:manage'], $roles->syncPermissions());

        $this->assertContains('plugin1:read', $this->getStoredPermissions(1));
        $this->assertNotContains('plugin1:manage', $this->getStoredPermissions(1));
        $this->assertSame(['group:read', 'member:read', 'plugin1:manage'], $this->getStoredPermissions(3));
        $this->assertContains('plugin1:manage', $this->getStoredPermissions(4));
        $this->assertNotContains('plugin1:read', $this->getStoredPermissions(4));

        //removed from a role: not given again
        $delete = $this->zdb->delete(Roles::PERMISSIONS_TABLE);
        $delete->where([Roles::PK => 3, 'permission' => 'plugin1:manage']);
        $this->zdb->execute($delete);
        $this->assertSame([], $roles->syncPermissions());
        $this->assertSame(['group:read', 'member:read'], $this->getStoredPermissions(3));
    }
}
