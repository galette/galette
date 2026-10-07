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
use Galette\Access\RoleResolver;
use Galette\Core\Authentication;
use Galette\Core\FeatureFlagManager;
use Galette\Core\Login;
use Galette\Interfaces\PermissionResolverInterface;
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
                    new Permission(
                        'test:any',
                        'Managers if any pref is on',
                        Authentication::ACCESS_STAFF,
                        ['pref_bool_groupsmanagers_exports', 'pref_bool_groupsmanagers_mailings']
                    ),
                    new Permission(
                        'test:members',
                        'Members if pref is on',
                        Authentication::ACCESS_STAFF,
                        null,
                        'pref_bool_create_member'
                    ),
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
            ->onlyMethods(['getAccessLevel', 'isGroupManager', 'isLogged', 'getManagedGroups'])
            ->getMock();
        $login->method('getAccessLevel')->willReturn($level);
        $login->method('isLogged')->willReturn($level > Authentication::ACCESS_PUBLIC);
        $login->method('isGroupManager')->willReturn($manager);
        $login->method('getManagedGroups')->willReturn($manager ? [3, 7] : []);
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
        $this->assertNull($permission->members);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid permission name "member"');
        new Permission('member', 'No action');
    }

    /**
     * Test routes ACLs only name known permissions
     */
    public function testRoutesPermissionsExist(): void
    {
        $permissions = new Permissions();
        $levels = ['superadmin', 'admin', 'staff', 'groupmanager', 'member'];
        $found = 0;
        foreach ($this->container->get('acls') as $route => $acl) {
            if (in_array($acl, $levels, true)) {
                continue;
            }
            foreach (explode('|', $acl) as $permission) {
                $this->assertTrue(
                    $permissions->has($permission),
                    sprintf('Unknown permission "%s" for route %s', $permission, $route)
                );
                ++$found;
            }
        }
        $this->assertGreaterThan(0, $found);
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

        $mailings = $this->preferences->pref_bool_groupsmanagers_mailings;
        $this->preferences->pref_bool_groupsmanagers_exports = false;
        $this->preferences->pref_bool_groupsmanagers_mailings = false;
        $this->assertFalse($access->can('test:any', login: $manager));
        $this->preferences->pref_bool_groupsmanagers_mailings = true;
        $this->assertTrue($access->can('test:any', login: $manager));
        $this->assertFalse($access->can('test:any', login: $member));

        $this->preferences->pref_bool_groupsmanagers_exports = $exports; //reset
        $this->preferences->pref_bool_groupsmanagers_mailings = $mailings; //reset
    }

    /**
     * Test groups scope from legacy levels and groups managers
     */
    public function testLegacyGroupScope(): void
    {
        $access = $this->getAccessControl();
        $manager = $this->getLogin(Authentication::ACCESS_MANAGER, true);
        $member = $this->getLogin(Authentication::ACCESS_USER);

        $this->assertNull($access->getGroupScope('test:staff', $this->getLogin(Authentication::ACCESS_STAFF)));
        $this->assertSame([], $access->getGroupScope('test:staff', $manager));
        $this->assertSame([3, 7], $access->getGroupScope('test:managers', $manager));
        $this->assertSame([], $access->getGroupScope('test:managers', $member));

        $create_member = $this->preferences->pref_bool_create_member;
        $this->preferences->pref_bool_create_member = true;
        $this->assertNull($access->getGroupScope('test:members', $member));
        $this->preferences->pref_bool_create_member = false;
        $this->assertSame([], $access->getGroupScope('test:members', $member));
        $this->preferences->pref_bool_create_member = $create_member; //reset
    }

    /**
     * Test the exports preference grants every export permission, mailings its own
     */
    public function testLegacyExportsAndMailings(): void
    {
        $access = new AccessControl(
            new Permissions(),
            new LegacyResolver($this->zdb, $this->preferences),
            $this->login
        );
        $manager = $this->getLogin(Authentication::ACCESS_MANAGER, true);
        $staff = $this->getLogin(Authentication::ACCESS_STAFF);
        $exports_permissions = ['member:export', 'member:print', 'group:export'];

        $exports = $this->preferences->pref_bool_groupsmanagers_exports;
        $mailings = $this->preferences->pref_bool_groupsmanagers_mailings;

        $this->preferences->pref_bool_groupsmanagers_exports = false;
        $this->preferences->pref_bool_groupsmanagers_mailings = false;
        foreach ([...$exports_permissions, 'mailing:send'] as $permission) {
            $this->assertFalse($access->can($permission, login: $manager), $permission);
            $this->assertTrue($access->can($permission, login: $staff), $permission);
        }

        $this->preferences->pref_bool_groupsmanagers_exports = true;
        foreach ($exports_permissions as $permission) {
            $this->assertTrue($access->can($permission, login: $manager), $permission);
        }
        $this->assertFalse($access->can('mailing:send', login: $manager));

        $this->preferences->pref_bool_groupsmanagers_exports = false;
        $this->preferences->pref_bool_groupsmanagers_mailings = true;
        foreach ($exports_permissions as $permission) {
            $this->assertFalse($access->can($permission, login: $manager), $permission);
        }
        $this->assertTrue($access->can('mailing:send', login: $manager));

        $this->preferences->pref_bool_groupsmanagers_exports = $exports; //reset
        $this->preferences->pref_bool_groupsmanagers_mailings = $mailings; //reset
    }

    /**
     * Test permissions granted to any member
     */
    public function testLegacyMembers(): void
    {
        $access = $this->getAccessControl();
        $member = $this->getLogin(Authentication::ACCESS_USER);
        $anonymous = $this->getLogin(Authentication::ACCESS_PUBLIC);

        $create_member = $this->preferences->pref_bool_create_member;
        $this->preferences->pref_bool_create_member = false;
        $this->assertFalse($access->can('test:members', login: $member));
        $this->preferences->pref_bool_create_member = true;
        $this->assertTrue($access->can('test:members', login: $member));
        $this->assertFalse($access->can('test:members', login: $anonymous));
        $this->assertTrue($access->can('test:members', login: $this->getLogin(Authentication::ACCESS_STAFF)));
        $this->preferences->pref_bool_create_member = $create_member; //reset
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
     * Test container provides access control, resolving from roles with acls flag only
     */
    public function testContainer(): void
    {
        $this->assertInstanceOf(AccessControl::class, $this->container->get(AccessControl::class));
        $this->assertInstanceOf(
            LegacyResolver::class,
            $this->container->get(PermissionResolverInterface::class)
        );

        $flags = $this->container->get(FeatureFlagManager::class);
        $acls = $this->createStub(FeatureFlagManager::class);
        $acls->method('isEnabled')->willReturnCallback(fn(string $flag): bool => $flag === 'acls');
        $this->container->set(FeatureFlagManager::class, $acls);
        try {
            $this->assertInstanceOf(RoleResolver::class, $this->container->make(PermissionResolverInterface::class));
        } finally {
            $this->container->set(FeatureFlagManager::class, $flags);
        }
        $this->assertInstanceOf(LegacyResolver::class, $this->container->make(PermissionResolverInterface::class));
    }
}
