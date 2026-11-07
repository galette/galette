<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Navigation;

use Galette\Tests\GaletteTestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

/**
 * Dashboards tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class DashboardsTest extends GaletteTestCase
{
    protected int $seed = 20230324120838;
    protected bool $load_plugins = true;

    /**
     * New releases notices
     */
    private function releases(): \Galette\Navigation\ReleaseNotices
    {
        return new \Galette\Navigation\ReleaseNotices(
            fn(): \Galette\Util\Release => $this->container->get(\Galette\Util\Release::class)
        );
    }

    /**
     * Dashboards built from the globals tests replace with mocks
     *
     * @phpstan-impure
     */
    private function dashboards(): \Galette\Navigation\Dashboards
    {
        global $zdb, $login, $plugins;
        return new \Galette\Navigation\Dashboards(
            zdb: $zdb,
            login: $login,
            plugins: $plugins,
            access: $this->container->get(\Galette\Access\AccessControl::class),
            releases: $this->releases()
        );
    }

    /**
     * Test getDashboards
     */
    #[AllowMockObjectsWithoutExpectations]
    public function testGetDashboards(): void
    {
        global $login;

        $db = new \Galette\Core\Db();

        $dashboards = $this->dashboards()->getEntries();
        $mydashboards = $this->dashboards()->getMyEntries();
        $this->assertCount(0, $dashboards);
        $this->assertCount(0, $mydashboards);

        $login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs([$db, new \Galette\Core\I18n()])
            ->onlyMethods(['isLogged', 'isStaff', 'isAdmin', 'isSuperAdmin'])
            ->getMock();

        $login->method('isLogged')->willReturn(true);
        $login->method('isStaff')->willReturn(true);
        $login->method('isAdmin')->willReturn(true);
        $login->method('isSuperAdmin')->willReturn(true);

        $dashboards = $this->dashboards()->getEntries();
        $mydashboards = $this->dashboards()->getMyEntries();
        $this->assertSame(
            [
                'members',
                'groups',
                'mailings',
                'contributions',
                'contributions',
                'reminders',
                'documentsPublicList',
                'preferences',
                'plugins'
            ],
            array_map(fn(\Galette\Navigation\MenuEntry $entry): ?string => $entry->route?->name, $dashboards)
        );
        $this->assertSame(['type' => 'transactions'], $dashboards[4]->route?->args);
        $this->assertSame('control_knobs', $dashboards[7]->icon);
        $this->assertNull($dashboards[8]->badge);
        $this->assertCount(0, $mydashboards);

        $login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs([$db, new \Galette\Core\I18n()])
            ->onlyMethods(['isLogged', 'isStaff', 'isAdmin', 'isSuperAdmin'])
            ->getMock();

        $login->method('isLogged')->willReturn(true);
        $login->method('isStaff')->willReturn(true);
        $login->method('isAdmin')->willReturn(true);
        $login->method('isSuperAdmin')->willReturn(false);

        $dashboards = $this->dashboards()->getEntries();
        $mydashboards = $this->dashboards()->getMyEntries();
        $this->assertCount(8, $dashboards);
        $this->assertSame(['My information', 'My contributions', 'My transactions'], array_column($mydashboards, 'label'));

        $login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs([$db, new \Galette\Core\I18n()])
            ->onlyMethods(['isLogged', 'isStaff', 'isAdmin', 'isSuperAdmin'])
            ->getMock();

        $login->method('isLogged')->willReturn(true);
        $login->method('isStaff')->willReturn(true);
        $login->method('isAdmin')->willReturn(false);
        $login->method('isSuperAdmin')->willReturn(false);

        $dashboards = $this->dashboards()->getEntries();
        $mydashboards = $this->dashboards()->getMyEntries();
        $this->assertCount(6, $dashboards);
        $this->assertCount(3, $mydashboards);

        $login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs([$db, new \Galette\Core\I18n()])
            ->onlyMethods(['isLogged', 'isStaff', 'isAdmin', 'isSuperAdmin'])
            ->getMock();

        $login->method('isLogged')->willReturn(true);
        $login->method('isStaff')->willReturn(false);
        $login->method('isAdmin')->willReturn(false);
        $login->method('isSuperAdmin')->willReturn(false);

        $dashboards = $this->dashboards()->getEntries();
        $mydashboards = $this->dashboards()->getMyEntries();
        $this->assertCount(0, $dashboards);
        $this->assertCount(3, $mydashboards);
    }

    /**
     * Test getDashboards for groups managers
     *
     * Contributions and transactions entries depend on dedicated preferences.
     */
    #[AllowMockObjectsWithoutExpectations]
    public function testGetGroupManagerDashboards(): void
    {
        global $login, $preferences;

        $db = new \Galette\Core\Db();

        $login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs([$db, new \Galette\Core\I18n()])
            ->onlyMethods(['isLogged', 'isStaff', 'isAdmin', 'isSuperAdmin', 'isGroupManager', 'getManagedGroups'])
            ->getMock();

        $login->method('isLogged')->willReturn(true);
        $login->method('isStaff')->willReturn(false);
        $login->method('isAdmin')->willReturn(false);
        $login->method('isSuperAdmin')->willReturn(false);
        $login->method('isGroupManager')->willReturn(true);
        $login->method('getManagedGroups')->willReturn([1]);

        //groups managers cannot see contributions nor transactions
        $this->revokeFromRole(\Galette\Access\Roles::MANAGER, 'contribution:read');
        $this->revokeFromRole(\Galette\Access\Roles::MANAGER, 'transaction:read');

        $dashboards = $this->dashboards()->getEntries();
        $this->assertSame(
            ['Members', 'Groups'],
            array_column($dashboards, 'label')
        );
        $this->assertCount(3, $this->dashboards()->getMyEntries());

        //groups managers can see contributions
        $this->grantToRole(\Galette\Access\Roles::MANAGER, ['contribution:read', 'transaction:attach']);
        $this->revokeFromRole(\Galette\Access\Roles::MANAGER, 'transaction:read');

        $dashboards = $this->dashboards()->getEntries();
        $this->assertSame(
            ['Members', 'Groups', 'Contributions'],
            array_column($dashboards, 'label')
        );

        //groups managers can see transactions
        $this->revokeFromRole(\Galette\Access\Roles::MANAGER, 'contribution:read');
        $this->grantToRole(\Galette\Access\Roles::MANAGER, 'transaction:read');

        $dashboards = $this->dashboards()->getEntries();
        $this->assertSame(
            ['Members', 'Groups', 'Transactions'],
            array_column($dashboards, 'label')
        );

        //groups managers can see both contributions and transactions
        $this->grantToRole(\Galette\Access\Roles::MANAGER, ['contribution:read', 'transaction:attach']);
        $this->grantToRole(\Galette\Access\Roles::MANAGER, 'transaction:read');

        $dashboards = $this->dashboards()->getEntries();
        $this->assertSame(
            ['Members', 'Groups', 'Contributions', 'Transactions'],
            array_column($dashboards, 'label')
        );

        //mailings and reminders are never proposed to groups managers
        $this->assertNotContains('Mailings', array_column($dashboards, 'label'));
        $this->assertNotContains('Reminders', array_column($dashboards, 'label'));

        //staff members get all entries, whatever groups managers preferences are
        $login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs([$db, new \Galette\Core\I18n()])
            ->onlyMethods(['isLogged', 'isStaff', 'isAdmin', 'isSuperAdmin', 'isGroupManager'])
            ->getMock();

        $login->method('isLogged')->willReturn(true);
        $login->method('isStaff')->willReturn(true);
        $login->method('isAdmin')->willReturn(false);
        $login->method('isSuperAdmin')->willReturn(false);
        $login->method('isGroupManager')->willReturn(true);

        $this->revokeFromRole(\Galette\Access\Roles::MANAGER, 'contribution:read');
        $this->revokeFromRole(\Galette\Access\Roles::MANAGER, 'transaction:read');

        $dashboards = $this->dashboards()->getEntries();
        $this->assertSame(
            ['Members', 'Groups', 'Mailings', 'Contributions', 'Transactions', 'Reminders'],
            array_column($dashboards, 'label')
        );
    }
}
