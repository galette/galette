<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Access\test\units;

use Galette\Access\AccessControl;
use Galette\Access\LegacyResolver;
use Galette\Access\Permission;
use Galette\Access\Permissions;
use Galette\Core\Authentication;
use Galette\Core\Login;
use Galette\Tests\GaletteTestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

/**
 * AccessControl tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
#[AllowMockObjectsWithoutExpectations]
class AccessControlTest extends GaletteTestCase
{
    protected int $seed = 420322;

    /**
     * Get a catalog with test permissions
     */
    private function getPermissions(): Permissions
    {
        return new class extends Permissions {
            /**
             * Test permissions
             *
             * @return array<Permission>
             */
            protected function getCorePermissions(): array
            {
                return [
                    new Permission('test:staff', 'Staff only', Authentication::ACCESS_STAFF),
                    new Permission('test:managers', 'Managers', Authentication::ACCESS_STAFF, true),
                    new Permission(
                        'test:pref',
                        'Managers if pref is on',
                        Authentication::ACCESS_STAFF,
                        'pref_bool_groupsmanagers_exports'
                    ),
                    new Permission('test:admin', 'Admin', Authentication::ACCESS_ADMIN),
                ];
            }
        };
    }

    /**
     * Get access control instance
     *
     * @param ?Login $login Current login
     */
    private function getAccessControl(?Login $login = null): AccessControl
    {
        return new AccessControl(
            $this->getPermissions(),
            new LegacyResolver($this->zdb, $this->preferences),
            $login ?? $this->login
        );
    }

    /**
     * Get a login mock
     *
     * @param int  $level   Access level, one of Authentication::ACCESS_*
     * @param bool $manager Whether login manages at least one group
     */
    private function getLogin(int $level, bool $manager = false): Login
    {
        $login = $this->getMockBuilder(Login::class)
            ->setConstructorArgs([$this->zdb, $this->i18n])
            ->onlyMethods(['getAccessLevel', 'isGroupManager'])
            ->getMock();
        $login->method('getAccessLevel')->willReturn($level);
        $login->method('isGroupManager')->willReturn($manager);
        return $login;
    }

    /**
     * Test permission names
     */
    public function testPermissionName(): void
    {
        $permission = new Permission('member:edit', 'Edit members');
        $this->assertSame('member', $permission->getDomain());
        $this->assertSame(Authentication::ACCESS_ADMIN, $permission->level);
        $this->assertNull($permission->managers);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid permission name "member"');
        new Permission('member', 'No action');
    }

    /**
     * Test unknown permission
     */
    public function testUnknownPermission(): void
    {
        $access = $this->getAccessControl();
        $this->assertFalse($this->getPermissions()->has('test:unknown'));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown permission "test:unknown"');
        $access->can('test:unknown');
    }

    /**
     * Test permissions granted from legacy access levels
     */
    public function testLegacyLevels(): void
    {
        $access = $this->getAccessControl();
        $expected = [
            Authentication::ACCESS_PUBLIC => false,
            Authentication::ACCESS_USER => false,
            Authentication::ACCESS_MANAGER => false,
            Authentication::ACCESS_STAFF => true,
            Authentication::ACCESS_ADMIN => true,
            Authentication::ACCESS_SUPERADMIN => true,
        ];

        foreach ($expected as $level => $granted) {
            $this->assertSame($granted, $access->can('test:staff', login: $this->getLogin($level)), "Level $level");
        }

        $this->assertFalse($access->can('test:admin', login: $this->getLogin(Authentication::ACCESS_STAFF)));
        $this->assertTrue($access->can('test:admin', login: $this->getLogin(Authentication::ACCESS_ADMIN)));
    }

    /**
     * Test permissions granted to groups managers
     */
    public function testLegacyManagers(): void
    {
        $access = $this->getAccessControl();
        $manager = $this->getLogin(Authentication::ACCESS_MANAGER, true);
        $member = $this->getLogin(Authentication::ACCESS_USER);

        $this->assertFalse($access->can('test:staff', login: $manager));
        $this->assertTrue($access->can('test:managers', login: $manager));
        $this->assertFalse($access->can('test:managers', login: $member));

        $exports = $this->preferences->pref_bool_groupsmanagers_exports;
        $this->preferences->pref_bool_groupsmanagers_exports = false;
        $this->assertFalse($access->can('test:pref', login: $manager));
        $this->preferences->pref_bool_groupsmanagers_exports = true;
        $this->assertTrue($access->can('test:pref', login: $manager));
        $this->assertFalse($access->can('test:pref', login: $member));
        $this->preferences->pref_bool_groupsmanagers_exports = $exports; //reset
    }

    /**
     * Test current login is checked unless another one is given
     */
    public function testCurrentLogin(): void
    {
        $access = $this->getAccessControl($this->getLogin(Authentication::ACCESS_STAFF));
        $this->assertTrue($access->can('test:staff'));
        $this->assertFalse($access->can('test:staff', login: $this->getLogin(Authentication::ACCESS_USER)));

        $access = $this->getAccessControl();
        $this->assertFalse($access->can('test:staff'));
        $this->logSuperAdmin();
        $this->assertTrue($access->can('test:staff'));
    }

    /**
     * Test container provides access control
     */
    public function testContainer(): void
    {
        $this->assertInstanceOf(AccessControl::class, $this->container->get(AccessControl::class));
        $this->assertInstanceOf(
            LegacyResolver::class,
            $this->container->get(\Galette\Interfaces\PermissionResolverInterface::class)
        );
    }
}
