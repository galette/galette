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
 * Members actions tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class MemberActionsTest extends GaletteTestCase
{
    protected int $seed = 20230324120838;
    protected bool $load_plugins = true;

    /**
     * Members actions built from the globals tests replace with mocks
     *
     * @phpstan-impure
     */
    private function memberActions(): \Galette\Navigation\MemberActions
    {
        global $login, $preferences, $plugins;
        return new \Galette\Navigation\MemberActions(
            login: $login,
            preferences: $preferences,
            plugins: $plugins,
            access: $this->container->get(\Galette\Access\AccessControl::class),
            coordinates: $this->container->get(\Galette\Maps\Coordinates::class)
        );
    }

    /**
     * Test getListActions
     */
    #[AllowMockObjectsWithoutExpectations]
    public function testGetListActions(): void
    {
        global $login;

        $db = new \Galette\Core\Db();
        $member = $this->getMemberOne();

        $actions = $this->memberActions()->getListActions($member);
        $this->assertCount(0, $actions);

        $login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs([$db, new \Galette\Core\I18n()])
            ->onlyMethods(['isLogged', 'isStaff', 'isAdmin', 'isSuperAdmin'])
            ->getMock();

        $login->method('isLogged')->willReturn(true);
        $login->method('isStaff')->willReturn(true);
        $login->method('isAdmin')->willReturn(true);
        $login->method('isSuperAdmin')->willReturn(true);

        $actions = $this->memberActions()->getListActions($member);
        $this->assertSame(
            ['editMember', 'contributions', 'removeMember', 'impersonate'],
            array_map(fn(\Galette\Navigation\MenuEntry $action): ?string => $action->route?->name, $actions)
        );
        $this->assertSame(['id' => $member->id], $actions[0]->route?->args);
        $this->assertSame('delete', $actions[2]->extraClass);

        $login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs([$db, new \Galette\Core\I18n()])
            ->onlyMethods(['isLogged', 'isStaff', 'isAdmin', 'isSuperAdmin'])
            ->getMock();

        $login->method('isLogged')->willReturn(true);
        $login->method('isStaff')->willReturn(true);
        $login->method('isAdmin')->willReturn(true);
        $login->method('isSuperAdmin')->willReturn(false);

        $actions = $this->memberActions()->getListActions($member);
        $this->assertCount(3, $actions);

        $login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs([$db, new \Galette\Core\I18n()])
            ->onlyMethods(['isLogged', 'isStaff', 'isAdmin', 'isSuperAdmin'])
            ->getMock();

        $login->method('isLogged')->willReturn(true);
        $login->method('isStaff')->willReturn(true);
        $login->method('isAdmin')->willReturn(false);
        $login->method('isSuperAdmin')->willReturn(false);

        $actions = $this->memberActions()->getListActions($member);
        $this->assertCount(3, $actions);

        $login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs([$db, new \Galette\Core\I18n()])
            ->onlyMethods(['isLogged', 'isStaff', 'isAdmin', 'isSuperAdmin'])
            ->getMock();

        $login->method('isLogged')->willReturn(true);
        $login->method('isStaff')->willReturn(false);
        $login->method('isAdmin')->willReturn(false);
        $login->method('isSuperAdmin')->willReturn(false);

        $actions = $this->memberActions()->getListActions($member);
        $this->assertCount(0, $actions);
    }

    /**
     * Test getDetailledActions
     */
    public function testGetDetailledActions(): void
    {
        $member = $this->getMemberOne();

        //no core actions yet
        $actions = $this->memberActions()->getDetailedActions($member);
        $this->assertCount(0, $actions);
    }

    /**
     * Test getBatchActions
     */
    #[AllowMockObjectsWithoutExpectations]
    public function testGetBatchActions(): void
    {
        global $login;

        $db = new \Galette\Core\Db();
        $this->getMemberOne();

        $actions = $this->memberActions()->getBatchActions();
        $this->assertCount(0, $actions);

        $login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs([$db, new \Galette\Core\I18n()])
            ->onlyMethods(['isLogged', 'isStaff', 'isAdmin', 'isSuperAdmin'])
            ->getMock();

        $login->method('isLogged')->willReturn(true);
        $login->method('isStaff')->willReturn(true);
        $login->method('isAdmin')->willReturn(true);
        $login->method('isSuperAdmin')->willReturn(true);

        $actions = $this->memberActions()->getBatchActions();
        $this->assertSame(
            [
                'masschange',
                'masscontributions',
                'delete',
                'attendance_sheet',
                'labels__directdownload',
                'cards__directdownload',
                'csv__directdownload'
            ],
            array_column($actions, 'name')
        );

        $login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs([$db, new \Galette\Core\I18n()])
            ->onlyMethods(['isLogged', 'isStaff', 'isAdmin', 'isSuperAdmin'])
            ->getMock();

        $login->method('isLogged')->willReturn(true);
        $login->method('isStaff')->willReturn(true);
        $login->method('isAdmin')->willReturn(true);
        $login->method('isSuperAdmin')->willReturn(false);

        $actions = $this->memberActions()->getBatchActions();
        $this->assertCount(7, $actions);

        $login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs([$db, new \Galette\Core\I18n()])
            ->onlyMethods(['isLogged', 'isStaff', 'isAdmin', 'isSuperAdmin'])
            ->getMock();

        $login->method('isLogged')->willReturn(true);
        $login->method('isStaff')->willReturn(true);
        $login->method('isAdmin')->willReturn(false);
        $login->method('isSuperAdmin')->willReturn(false);

        $actions = $this->memberActions()->getBatchActions();
        $this->assertCount(7, $actions);

        $login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs([$db, new \Galette\Core\I18n()])
            ->onlyMethods(['isLogged', 'isStaff', 'isAdmin', 'isSuperAdmin'])
            ->getMock();

        $login->method('isLogged')->willReturn(true);
        $login->method('isStaff')->willReturn(false);
        $login->method('isAdmin')->willReturn(false);
        $login->method('isSuperAdmin')->willReturn(false);

        $actions = $this->memberActions()->getBatchActions();
        $this->assertCount(0, $actions);
    }
}
