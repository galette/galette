<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Access\test\units;

use Galette\Access\Permission;
use Galette\Access\Permissions;
use Galette\Access\Role;
use Galette\Access\Roles;
use Galette\Core\Authentication;
use Galette\Core\Plugins;
use Galette\Tests\GaletteTestCase;

/**
 * Permissions catalog tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class PermissionsTest extends GaletteTestCase
{
    /**
     * Get a plugins instance declaring permissions
     *
     * @param array<string, array{label: string, permissions: array<Permission>}> $declared Declared permissions
     */
    private function getPlugins(array $declared): Plugins
    {
        return new class ($declared) extends Plugins {
            /**
             * Constructor
             *
             * @param array<string, array{label: string, permissions: array<Permission>}> $declared Declared permissions
             */
            public function __construct(private readonly array $declared)
            {
            }

            /**
             * Get declared permissions
             *
             * @return array<string, array{label: string, permissions: array<Permission>}>
             */
            public function getPermissions(): array
            {
                return $this->declared;
            }
        };
    }

    /**
     * Test active plugins permissions are part of the catalog
     */
    public function testPluginsPermissions(): void
    {
        $permissions = $this->container->get(Permissions::class);

        $this->assertTrue($permissions->has('plugin1:read'));
        $this->assertTrue($permissions->has('plugin1:manage'));
        $this->assertSame(Authentication::ACCESS_STAFF, $permissions->get('plugin1:manage')->level);
        $this->assertSame('Galette Test1 Plugin', $permissions->getDomainLabel('plugin1'));
        $this->assertSame(
            ['plugin1:read', 'plugin1:manage'],
            array_keys($permissions->getByDomain()['plugin1'])
        );

        //core catalog alone
        $this->assertFalse((new Permissions())->has('plugin1:read'));
    }

    /**
     * Test plugins cannot declare permissions out of their own domain
     */
    public function testPluginsDomains(): void
    {
        $permissions = new Permissions($this->getPlugins([
            'myplugin' => [
                'label' => 'My plugin',
                'permissions' => [
                    new Permission('myplugin:read', 'Read', Authentication::ACCESS_USER),
                    new Permission('member:fly', 'Fly members', Authentication::ACCESS_USER),
                ]
            ],
            'member' => [
                'label' => 'Members plugin',
                'permissions' => [
                    new Permission('member:walk', 'Walk members', Authentication::ACCESS_USER),
                ]
            ],
        ]));

        $this->assertTrue($permissions->has('myplugin:read'));
        $this->assertFalse($permissions->has('member:fly'));
        $this->assertFalse($permissions->has('member:walk'));
        $this->assertSame('Members', $permissions->getDomainLabel('member'));
        $this->assertSame('My plugin', $permissions->getDomainLabel('myplugin'));

        $this->expectLogEntry(
            \Analog\Analog::WARNING,
            'Plugin "My plugin" permission "member:fly" is ignored: it must be in the "myplugin" domain.'
        );
        $this->expectLogEntry(
            \Analog\Analog::WARNING,
            'Plugin "Members plugin" permissions are ignored: "member" is a core domain.'
        );
    }

    /**
     * Test a role keeps permissions the catalog does not know, from a disabled plugin
     */
    public function testRoleKeepsUnknownPermissions(): void
    {
        (new Roles($this->zdb, new Permissions()))->installInit($this->preferences);
        $catalog = $this->container->get(Permissions::class);

        $role = new Role();
        $role->setName('Plugin one manager')
            ->setPermissions(['member:read', 'plugin1:manage'], $catalog);
        $this->assertTrue($role->store($this->zdb));

        try {
            //plugin is now disabled
            $role = new Role($role->getId());
            $role->setPermissions(['member:edit'], new Permissions());
            $this->assertTrue($role->store($this->zdb));

            $role = new Role($role->getId());
            $this->assertSame(['member:edit', 'plugin1:manage'], $role->getPermissions());

            //plugin is back
            $role->setPermissions(['member:edit'], $catalog);
            $this->assertTrue($role->store($this->zdb));
            $this->assertSame(['member:edit'], (new Role($role->getId()))->getPermissions());
        } finally {
            $role->delete();
        }
    }
}
