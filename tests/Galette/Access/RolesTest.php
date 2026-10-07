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
    /** @var array<string> */
    private const array MANAGERS_PREFERENCES = [
        'pref_bool_groupsmanagers_create_member',
        'pref_bool_groupsmanagers_edit_member',
        'pref_bool_groupsmanagers_edit_groups',
        'pref_bool_groupsmanagers_create_contributions',
        'pref_bool_groupsmanagers_create_transactions',
        'pref_bool_groupsmanagers_see_contributions',
        'pref_bool_groupsmanagers_see_transactions',
        'pref_bool_groupsmanagers_exports',
        'pref_bool_groupsmanagers_mailings',
        'pref_bool_create_member',
    ];

    /** @var array<string, bool> */
    private array $saved_preferences = [];

    /**
     * Set up tests
     */
    public function setUp(): void
    {
        parent::setUp();
        foreach (self::MANAGERS_PREFERENCES as $preference) {
            $this->saved_preferences[$preference] = $this->preferences->$preference;
        }
    }

    /**
     * Tear down tests
     */
    public function tearDown(): void
    {
        foreach ($this->saved_preferences as $preference => $value) {
            $this->preferences->$preference = $value;
        }
        parent::tearDown();
    }

    /**
     * Set all groups managers related preferences
     *
     * @param bool $value Value
     */
    private function setPreferences(bool $value): void
    {
        foreach (self::MANAGERS_PREFERENCES as $preference) {
            $this->preferences->$preference = $value;
        }
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
     * Test default permissions follow legacy levels and preferences
     */
    public function testDefaultPermissions(): void
    {
        $roles = new Roles($this->zdb, new Permissions());
        $all = array_keys((new Permissions())->getAll());

        $this->setPreferences(false);
        $this->assertSame(
            [
                Roles::MEMBER => [],
                Roles::UPTODATE => [],
                Roles::MANAGER => ['member:read'],
                Roles::STAFF => $all,
                Roles::ADMIN => [],
            ],
            $roles->getDefaultPermissions($this->preferences)
        );

        $this->setPreferences(true);
        $this->assertSame(
            [
                Roles::MEMBER => ['member:create'],
                Roles::UPTODATE => [],
                Roles::MANAGER => [
                    'member:read',
                    'member:edit',
                    'member:delete',
                    'member:export',
                    'member:print',
                    'contribution:create',
                    'contribution:read',
                    'transaction:create',
                    'transaction:read',
                    'transaction:attach',
                    'group:edit',
                    'group:export',
                    'mailing:send',
                ],
                Roles::STAFF => array_values(array_diff($all, ['member:create'])),
                Roles::ADMIN => [],
            ],
            $roles->getDefaultPermissions($this->preferences)
        );
    }

    /**
     * Test system roles are stored with their permissions
     */
    public function testInstallInit(): void
    {
        $this->setPreferences(false);
        $roles = new Roles($this->zdb, new Permissions());
        $this->assertTrue($roles->installInit($this->preferences));

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

        $select = $this->zdb->select(Roles::PERMISSIONS_TABLE);
        $select->where([Roles::PK => 3]);
        $permissions = [];
        foreach ($this->zdb->execute($select) as $row) {
            $permissions[] = $row->permission;
        }
        $this->assertSame(['member:read'], $permissions);
    }
}
