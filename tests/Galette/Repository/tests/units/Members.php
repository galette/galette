<?php

/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * Members repository tests
 *
 * PHP version 5
 *
 * Copyright © 2017 The Galette Team
 *
 * This file is part of Galette (http://galette.tuxfamily.org).
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
 *
 * @category  Repository
 * @package   GaletteTests
 *
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2017 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @version   SVN: $Id$
 * @link      http://galette.tuxfamily.org
 * @since     2017-04-15
 */

namespace Galette\Repository\test\units;

use \atoum;

/**
 * Members repository tests
 *
 * @category  Repository
 * @name      Members
 * @package   GaletteTests
 * @author    Johan Cwiklinski <johan@x-tnd.be>
 * @copyright 2017 The Galette Team
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GPL License 3.0 or (at your option) any later version
 * @link      http://galette.tuxfamily.org
 * @since     2017-04-15
 */
class Members extends atoum
{
    private $zdb;
    private $i18n;
    private $preferences;
    private $session;
    private $login;
    private $history;
    private $members_fields;
    private $seed = 335689;
    private $mids;

    /**
     * Set up tests
     *
     * @param string $testMethod Calling method
     *
     * @return void
     */
    public function beforeTestMethod($testMethod)
    {
        $this->zdb = new \Galette\Core\Db();
        $this->i18n = new \Galette\Core\I18n(
            \Galette\Core\I18n::DEFAULT_LANG
        );
        $this->preferences = new \Galette\Core\Preferences(
            $this->zdb
        );
        $this->session = new \RKA\Session();
        $this->login = new \Galette\Core\Login($this->zdb, $this->i18n, $this->session);
        $this->history = new \Galette\Core\History($this->zdb, $this->login);

        include_once GALETTE_ROOT . 'includes/fields_defs/members_fields.php';
        $this->members_fields = $members_fields;
        $this->createMembers();
    }

    /**
     * Tear down tests
     *
     * @param string $testMethod Calling method
     *
     * @return void
     */
    public function afterTestMethod($testMethod)
    {
        $this->deleteMembers();
    }

    /**
     * Create members and get their id
     *
     * @return int[]
     */
    private function createMembers()
    {
        global $zdb, $login, $hist, $i18n; // globals :(
        $zdb = $this->zdb;
        $login = $this->login;
        $hist = $this->history;
        $i18n = $this->i18n;

        try {
            $this->deleteMembers();
        } catch (\Exception $e) {
            //empty catch
        }

        $status = new \Galette\Entity\Status($this->zdb);
        if (count($status->getList()) === 0) {
            $res = $status->installInit();
            $this->boolean($res)->isTrue();
        }

        $contribtypes = new \Galette\Entity\ContributionsTypes($this->zdb);
        if (count($contribtypes->getCompleteList()) === 0) {
            $res = $contribtypes->installInit();
            $this->boolean($res)->isTrue();
        }

        $fakedata = new \Galette\Util\FakeData($this->zdb, $this->i18n);

        define(
            '_CURRENT_THEME_PATH',
            GALETTE_THEMES_PATH . $this->preferences->pref_theme . '/'
        );

        $fakedata
            ->setSeed($this->seed)
            ->setNbMembers(10)
            ->setWithPhotos(true)
            ->setNbGroups(0)
            ->setNbTransactions(0)
            ->setMaxContribs(0)
            ->setDependencies(
                $this->preferences,
                $this->members_fields,
                $this->history,
                $this->login
            );

        $fakedata->generate();

        $report = $fakedata->getReport();

        $this->array($report['success'])->hasSize(2);
        $this->array($report['errors'])->hasSize(0);
        $this->array($report['warnings'])->hasSize(0);

        $this->mids = $fakedata->getMembersIds();
    }

    /**
     * Delete member
     *
     * @return void
     */
    private function deleteMembers()
    {
        if (is_array($this->mids) && count($this->mids) > 0) {
            $delete = $this->zdb->delete(\Galette\Entity\Contribution::TABLE);
            $delete->where->in(\Galette\Entity\Adherent::PK, $this->mids);
            $this->zdb->execute($delete);
        }

        $delete = $this->zdb->delete(\Galette\Entity\Adherent::TABLE);
        $delete->where(['fingerprint' => 'FAKER' . $this->seed]);
        $this->zdb->execute($delete);

        //Clean logs
        $this->zdb->db->query(
            'TRUNCATE TABLE ' . PREFIX_DB . \Galette\Core\History::TABLE,
            \Zend\Db\Adapter\Adapter::QUERY_MODE_EXECUTE
        );

        //FIXME: Photos should be removed, but this fail for now :(
        $this->zdb->db->query(
            'TRUNCATE TABLE ' . PREFIX_DB . \Galette\Core\Picture::TABLE,
            \Zend\Db\Adapter\Adapter::QUERY_MODE_EXECUTE
        );
    }

    /**
     * Test getList
     *
     * @return void
     */
    public function testGetList()
    {
        $members = new \Galette\Repository\Members();

        $list = $members->getList();
        $this->integer($list->count())->isIdenticalTo(10);

        $list = $members->getEmails($this->zdb);
        $this->array($list)->hasSize(10)
            ->hasKeys([
                'georges.didier@perrot.fr',
                'marc25@pires.org'
            ]);


        //Filter on active accounts
        $filters = new \Galette\Filters\MembersList;
        $filters->account_status_filter = \Galette\Repository\Members::ACTIVE_ACCOUNT;
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->integer($list->count())->isIdenticalTo(9);

        //Filter on inactive accounts
        $filters = new \Galette\Filters\MembersList;
        $filters->account_status_filter = \Galette\Repository\Members::INACTIVE_ACCOUNT;
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->integer($list->count())->isIdenticalTo(1);

        //Search on address
        $filters = new \Galette\Filters\MembersList;
        $filters->filter_str = 'avenue';
        $filters->field_filter = \Galette\Repository\Members::FILTER_ADDRESS;
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->integer($list->count())->isIdenticalTo(2);

        $members = new \Galette\Repository\Members();
        $list = $members->getList(true);

        $this->array($list)
            ->hasSize(10)
            ->object[0]->isInstanceOf('\Galette\Entity\Adherent');

        $members = new \Galette\Repository\Members();
        $list = $members->getList(false, ['nom_adh', 'ville_adh']);
        $this->integer($list->count())->isIdenticalTo(10);
        $arraylist = $list->toArray();
        foreach ($arraylist as $array) {
            $this->array($array)
                ->hasSize(3)
                ->keys->isIdenticalTo([
                    'nom_adh',
                    'ville_adh',
                    'id_adh',
                ]);
        }

        //Get staff
        $members = new \Galette\Repository\Members();
        $list = $members->getStaffMembersList();
        $this->integer($list->count())->isIdenticalTo(2);

        //Remove 2 members
        $torm = [];
        $mids = $this->mids;
        $torm[] = array_pop($mids);
        $torm[] = array_pop($mids);
        $this->mids = $mids;

        $members = new \Galette\Repository\Members();
        $this->boolean($members->removeMembers('notanid'))->isFalse();
        $this->boolean($members->removeMembers($torm))->isTrue();

        $list = $members->getList();
        $this->integer($list->count())->isIdenticalTo(8);
    }

    /**
     * Test getPublicList
     *
     * @return void
     */
    public function testGetPublicList()
    {
        $members = new \Galette\Repository\Members();

        $list = $members->getPublicList(false);
        $this->array($list)->hasSize(1);

        $adh = $list[0];

        $this->object($adh)->isInstanceOf('\Galette\Entity\Adherent');
        $this->boolean($adh->appearsInMembersList())->isTrue();
        $this->variable($adh->_picture)->isNull();

        $list = $members->getPublicList(true);
        $this->array($list)->hasSize(1);

        $adh = $list[0];

        $this->object($adh)->isInstanceOf('\Galette\Entity\Adherent');
        $this->boolean($adh->appearsInMembersList())->isTrue();

        $this->boolean($adh->hasPicture())->isTrue();
    }
}
