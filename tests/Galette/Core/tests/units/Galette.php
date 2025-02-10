<?php

/**
 * Copyright © 2003-2025 The Galette Team
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

namespace Galette\Core\test\units;

use Galette\GaletteTestCase;

/**
 * Galette tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Galette extends GaletteTestCase
{
    protected int $seed = 20230324120838;

    /**
     * Test gitVersion
     *
     * @return void
     */
    public function testGitVersion(): void
    {
        $gitversion = \Galette\Core\Galette::gitVersion();
        $this->assertStringStartsWith(str_replace('-dev', '', GALETTE_VERSION), $gitversion);
        $this->assertSame(
            1,
            preg_match(
                '/-git-.+ \(\d{4}-\d{2}-\d{2}\)/',
                str_replace(GALETTE_VERSION, '', $gitversion)
            )
        );
    }

    /**
     * Test storing into session of various objects to detect serialization issues
     *
     * @return void
     */
    public function testSerialization(): void
    {
        //global objects
        $login = new \Galette\Core\Login($this->zdb, $this->i18n);
        $this->session->login_test = $login;
        $this->assertInstanceOf(\Galette\Core\Login::class, $this->session->login_test);

        $mailing = new \Galette\Core\Mailing($this->preferences);
        $this->session->mailing_test = $mailing;
        $this->assertInstanceOf(\Galette\Core\Mailing::class, $this->session->mailing_test);

        $gaptcha = new \Galette\Core\Gaptcha($this->i18n);
        $this->session->gaptcha_test = $gaptcha;
        $this->assertInstanceOf(\Galette\Core\Gaptcha::class, $this->session->gaptcha_test);

        $plugin_install = new \Galette\Core\PluginInstall();
        $this->session->plugininstall_test = $plugin_install;
        $this->assertInstanceOf(\Galette\Core\PluginInstall::class, $this->session->plugininstall_test);

        $i18n = new \Galette\Core\I18n();
        $this->session->i18n_test = $i18n;
        $this->assertInstanceOf(\Galette\Core\I18n::class, $this->session->i18n_test);

        //entities
        $contribution = new \Galette\Entity\Contribution($this->zdb, $this->login);
        $this->session->contribution_test = $contribution;
        $this->assertInstanceOf(\Galette\Entity\Contribution::class, $this->session->contribution_test);

        $df = \Galette\DynamicFields\DynamicField::getFieldType($this->zdb, \Galette\DynamicFields\DynamicField::LINE);
        $this->session->df_filter_test = $df;
        $this->assertInstanceOf(\Galette\DynamicFields\Line::class, $this->session->df_filter_test);

        $member = new \Galette\Entity\Adherent($this->zdb);
        $this->session->member_test = $member;
        $this->assertInstanceOf(\Galette\Entity\Adherent::class, $this->session->member_test);

        $transaction = new \Galette\Entity\Transaction($this->zdb, $this->login);
        $this->session->transaction_test = $transaction;
        $this->assertInstanceOf(\Galette\Entity\Transaction::class, $this->session->transaction_test);

        //filters
        $contribution_filter = new \Galette\Filters\ContributionsList();
        $this->session->contribution_filter_test = $contribution_filter;
        $this->assertInstanceOf(\Galette\Filters\ContributionsList::class, $this->session->contribution_filter_test);

        $member_advanced_filter = new \Galette\Filters\AdvancedMembersList();
        $this->session->member_advanced_filter_test = $member_advanced_filter;
        $this->assertInstanceOf(\Galette\Filters\AdvancedMembersList::class, $this->session->member_advanced_filter_test);

        $member_filter = new \Galette\Filters\MembersList();
        $this->session->member_filter_test = $member_filter;
        $this->assertInstanceOf(\Galette\Filters\MembersList::class, $this->session->member_filter_test);

        $history_filter = new \Galette\Filters\HistoryList();
        $this->session->history_filter_test = $history_filter;
        $this->assertInstanceOf(\Galette\Filters\HistoryList::class, $this->session->history_filter_test);

        $mailing_filter = new \Galette\Filters\MailingsList();
        $this->session->mailing_filter_test = $mailing_filter;
        $this->assertInstanceOf(\Galette\Filters\MailingsList::class, $this->session->mailing_filter_test);

        $saved_filter = new \Galette\Filters\SavedSearchesList();
        $this->session->saved_filter_test = $saved_filter;
        $this->assertInstanceOf(\Galette\Filters\SavedSearchesList::class, $this->session->saved_filter_test);

        $transaction_filter = new \Galette\Filters\TransactionsList();
        $this->session->transaction_filter_test = $transaction_filter;
        $this->assertInstanceOf(\Galette\Filters\TransactionsList::class, $this->session->transaction_filter_test);
    }

    /**
     * Test getMenus
     *
     * @return void
     */
    public function testGetMenus(): void
    {
        global $preferences, $login, $plugins;
        $db = new \Galette\Core\Db();
        $plugins = new \Galette\Core\Plugins($db);
        $preferences = $this->getMockBuilder(\Galette\Core\Preferences::class)
            ->setConstructorArgs(array($db))
            ->onlyMethods(array('showPublicPage'))
            ->getMock();

        $preferences->method('showPublicPage')->willReturn(true);

        $menus = \Galette\Core\Galette::getMenus();
        $this->assertIsArray($menus);
        $this->assertCount(0, $menus);

        $menus = \Galette\Core\Galette::getMenus(true);
        $this->assertIsArray($menus);
        $this->assertCount(1, $menus);
        $this->assertArrayHasKey('public', $menus);

        $login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs(array($db, new \Galette\Core\I18n()))
            ->onlyMethods(array('isLogged', 'isStaff', 'isAdmin', 'isSuperAdmin'))
            ->getMock();

        $login->method('isLogged')->willReturn(true);
        $login->method('isStaff')->willReturn(true);
        $login->method('isAdmin')->willReturn(true);
        $login->method('isSuperAdmin')->willReturn(false);

        $menus = \Galette\Core\Galette::getMenus(true);
        $this->assertIsArray($menus);
        $this->assertCount(6, $menus);

        $this->assertArrayHasKey('myaccount', $menus);
        $this->assertArrayHasKey('members', $menus);
        $this->assertArrayHasKey('contributions', $menus);
        $this->assertArrayHasKey('management', $menus);
        $this->assertArrayHasKey('configuration', $menus);
        $this->assertArrayHasKey('public', $menus);

        $login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs(array($db, new \Galette\Core\I18n()))
            ->onlyMethods(array('isLogged', 'isStaff', 'isAdmin', 'isSuperAdmin'))
            ->getMock();

        $login->method('isLogged')->willReturn(true);
        $login->method('isStaff')->willReturn(true);
        $login->method('isAdmin')->willReturn(false);
        $login->method('isSuperAdmin')->willReturn(false);

        $menus = \Galette\Core\Galette::getMenus(true);
        $this->assertIsArray($menus);
        $this->assertCount(5, $menus);

        $this->assertArrayHasKey('myaccount', $menus);
        $this->assertArrayHasKey('members', $menus);
        $this->assertArrayHasKey('contributions', $menus);
        $this->assertArrayHasKey('management', $menus);
        $this->assertArrayNotHasKey('configuration', $menus);
        $this->assertArrayHasKey('public', $menus);

        $login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs(array($db, new \Galette\Core\I18n()))
            ->onlyMethods(array('isLogged', 'isStaff', 'isAdmin', 'isSuperAdmin'))
            ->getMock();

        $login->method('isLogged')->willReturn(true);
        $login->method('isStaff')->willReturn(true);
        $login->method('isAdmin')->willReturn(true);
        $login->method('isSuperAdmin')->willReturn(true);

        $menus = \Galette\Core\Galette::getMenus(true);
        $this->assertIsArray($menus);
        $this->assertCount(5, $menus);

        $this->assertArrayNotHasKey('myaccount', $menus);
        $this->assertArrayHasKey('members', $menus);
        $this->assertArrayHasKey('contributions', $menus);
        $this->assertArrayHasKey('management', $menus);
        $this->assertArrayHasKey('configuration', $menus);
        $this->assertArrayHasKey('public', $menus);
    }

    /**
     * Test getPublicMenus
     *
     * @return void
     */
    public function testGetPublicMenus(): void
    {
        global $preferences;

        $db = new \Galette\Core\Db();
        $preferences = $this->getMockBuilder(\Galette\Core\Preferences::class)
            ->setConstructorArgs(array($db))
            ->onlyMethods(array('showPublicPage'))
            ->getMock();
        $preferences->method('showPublicPage')->willReturn(false);

        $menus = \Galette\Core\Galette::getPublicMenus();
        $this->assertIsArray($menus);
        $this->assertCount(0, $menus, print_r($menus, true));

        $preferences = $this->getMockBuilder(\Galette\Core\Preferences::class)
            ->setConstructorArgs(array($db))
            ->onlyMethods(array('showPublicPage'))
            ->getMock();
        $preferences->method('showPublicPage')->willReturn(true);

        $menus = \Galette\Core\Galette::getPublicMenus();
        $this->assertIsArray($menus);
        $this->assertCount(1, $menus);
    }

    /**
     * Test getDashboards
     *
     * @return void
     */
    public function testGetDashboards(): void
    {
        global $login;

        $db = new \Galette\Core\Db();

        $dashboards = \Galette\Core\Galette::getDashboards();
        $mydashboards = \Galette\Core\Galette::getMyDashboards();
        $this->assertCount(0, $dashboards);
        $this->assertCount(0, $mydashboards);

        $login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs(array($db, new \Galette\Core\I18n()))
            ->onlyMethods(array('isLogged', 'isStaff', 'isAdmin', 'isSuperAdmin'))
            ->getMock();

        $login->method('isLogged')->willReturn(true);
        $login->method('isStaff')->willReturn(true);
        $login->method('isAdmin')->willReturn(true);
        $login->method('isSuperAdmin')->willReturn(true);

        $dashboards = \Galette\Core\Galette::getDashboards();
        $mydashboards = \Galette\Core\Galette::getMyDashboards();
        $this->assertCount(9, $dashboards);
        $this->assertCount(0, $mydashboards);

        $login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs(array($db, new \Galette\Core\I18n()))
            ->onlyMethods(array('isLogged', 'isStaff', 'isAdmin', 'isSuperAdmin'))
            ->getMock();

        $login->method('isLogged')->willReturn(true);
        $login->method('isStaff')->willReturn(true);
        $login->method('isAdmin')->willReturn(true);
        $login->method('isSuperAdmin')->willReturn(false);

        $dashboards = \Galette\Core\Galette::getDashboards();
        $mydashboards = \Galette\Core\Galette::getMyDashboards();
        $this->assertCount(8, $dashboards);
        $this->assertCount(3, $mydashboards);

        $login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs(array($db, new \Galette\Core\I18n()))
            ->onlyMethods(array('isLogged', 'isStaff', 'isAdmin', 'isSuperAdmin'))
            ->getMock();

        $login->method('isLogged')->willReturn(true);
        $login->method('isStaff')->willReturn(true);
        $login->method('isAdmin')->willReturn(false);
        $login->method('isSuperAdmin')->willReturn(false);

        $dashboards = \Galette\Core\Galette::getDashboards();
        $mydashboards = \Galette\Core\Galette::getMyDashboards();
        $this->assertCount(6, $dashboards);
        $this->assertCount(3, $mydashboards);

        $login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs(array($db, new \Galette\Core\I18n()))
            ->onlyMethods(array('isLogged', 'isStaff', 'isAdmin', 'isSuperAdmin'))
            ->getMock();

        $login->method('isLogged')->willReturn(true);
        $login->method('isStaff')->willReturn(false);
        $login->method('isAdmin')->willReturn(false);
        $login->method('isSuperAdmin')->willReturn(false);

        $dashboards = \Galette\Core\Galette::getDashboards();
        $mydashboards = \Galette\Core\Galette::getMyDashboards();
        $this->assertCount(0, $dashboards);
        $this->assertCount(3, $mydashboards);
    }

    /**
     * Test getListActions
     *
     * @return void
     */
    public function testGetListActions(): void
    {
        global $login;

        $db = new \Galette\Core\Db();
        $member = $this->getMemberOne();

        $actions = \Galette\Core\Galette::getListActions($member);
        $this->assertCount(0, $actions);

        $login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs(array($db, new \Galette\Core\I18n()))
            ->onlyMethods(array('isLogged', 'isStaff', 'isAdmin', 'isSuperAdmin'))
            ->getMock();

        $login->method('isLogged')->willReturn(true);
        $login->method('isStaff')->willReturn(true);
        $login->method('isAdmin')->willReturn(true);
        $login->method('isSuperAdmin')->willReturn(true);

        $actions = \Galette\Core\Galette::getListActions($member);
        $this->assertCount(4, $actions);

        $login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs(array($db, new \Galette\Core\I18n()))
            ->onlyMethods(array('isLogged', 'isStaff', 'isAdmin', 'isSuperAdmin'))
            ->getMock();

        $login->method('isLogged')->willReturn(true);
        $login->method('isStaff')->willReturn(true);
        $login->method('isAdmin')->willReturn(true);
        $login->method('isSuperAdmin')->willReturn(false);

        $actions = \Galette\Core\Galette::getListActions($member);
        $this->assertCount(3, $actions);

        $login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs(array($db, new \Galette\Core\I18n()))
            ->onlyMethods(array('isLogged', 'isStaff', 'isAdmin', 'isSuperAdmin'))
            ->getMock();

        $login->method('isLogged')->willReturn(true);
        $login->method('isStaff')->willReturn(true);
        $login->method('isAdmin')->willReturn(false);
        $login->method('isSuperAdmin')->willReturn(false);

        $actions = \Galette\Core\Galette::getListActions($member);
        $this->assertCount(3, $actions);

        $login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs(array($db, new \Galette\Core\I18n()))
            ->onlyMethods(array('isLogged', 'isStaff', 'isAdmin', 'isSuperAdmin'))
            ->getMock();

        $login->method('isLogged')->willReturn(true);
        $login->method('isStaff')->willReturn(false);
        $login->method('isAdmin')->willReturn(false);
        $login->method('isSuperAdmin')->willReturn(false);

        $actions = \Galette\Core\Galette::getListActions($member);
        $this->assertCount(0, $actions);
    }

    /**
     * Test getDetailledActions
     *
     * @return void
     */
    public function testGetDetailledActions(): void
    {
        $member = $this->getMemberOne();

        //no core actions yet
        $actions = \Galette\Core\Galette::getDetailedActions($member);
        $this->assertCount(0, $actions);
    }

    /**
     * Test getBatchActions
     *
     * @return void
     */
    public function testGetBatchActions(): void
    {
        global $login;

        $db = new \Galette\Core\Db();
        $member = $this->getMemberOne();

        $actions = \Galette\Core\Galette::getBatchActions();
        $this->assertCount(0, $actions);

        $login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs(array($db, new \Galette\Core\I18n()))
            ->onlyMethods(array('isLogged', 'isStaff', 'isAdmin', 'isSuperAdmin'))
            ->getMock();

        $login->method('isLogged')->willReturn(true);
        $login->method('isStaff')->willReturn(true);
        $login->method('isAdmin')->willReturn(true);
        $login->method('isSuperAdmin')->willReturn(true);

        $actions = \Galette\Core\Galette::getBatchActions();
        $this->assertCount(7, $actions);

        $login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs(array($db, new \Galette\Core\I18n()))
            ->onlyMethods(array('isLogged', 'isStaff', 'isAdmin', 'isSuperAdmin'))
            ->getMock();

        $login->method('isLogged')->willReturn(true);
        $login->method('isStaff')->willReturn(true);
        $login->method('isAdmin')->willReturn(true);
        $login->method('isSuperAdmin')->willReturn(false);

        $actions = \Galette\Core\Galette::getBatchActions();
        $this->assertCount(7, $actions);

        $login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs(array($db, new \Galette\Core\I18n()))
            ->onlyMethods(array('isLogged', 'isStaff', 'isAdmin', 'isSuperAdmin'))
            ->getMock();

        $login->method('isLogged')->willReturn(true);
        $login->method('isStaff')->willReturn(true);
        $login->method('isAdmin')->willReturn(false);
        $login->method('isSuperAdmin')->willReturn(false);

        $actions = \Galette\Core\Galette::getBatchActions();
        $this->assertCount(7, $actions);

        $login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs(array($db, new \Galette\Core\I18n()))
            ->onlyMethods(array('isLogged', 'isStaff', 'isAdmin', 'isSuperAdmin'))
            ->getMock();

        $login->method('isLogged')->willReturn(true);
        $login->method('isStaff')->willReturn(false);
        $login->method('isAdmin')->willReturn(false);
        $login->method('isSuperAdmin')->willReturn(false);

        $actions = \Galette\Core\Galette::getBatchActions();
        $this->assertCount(0, $actions);
    }
}
