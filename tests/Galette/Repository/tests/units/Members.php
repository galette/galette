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

use atoum;

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
        $this->login = new \Galette\Core\Login($this->zdb, $this->i18n);
        $this->history = new \Galette\Core\History($this->zdb, $this->login, $this->preferences);

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
        $this->deleteGroups();
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


        $tests_members = json_decode(file_get_contents(GALETTE_TESTS_PATH . '/fixtures/tests_members.json'));

        $mids = [];
        $first = true;
        foreach ($tests_members as $test_member) {
            $test_member = (array)$test_member;
            $member = new \Galette\Entity\Adherent($this->zdb);
            $member->setDependencies(
                $this->preferences,
                $this->members_fields,
                $this->history
            );

            if (isset($test_member['societe_adh'])) {
                $test_member['is_company'] = true;
            }
            $this->boolean($member->check($test_member, [], []))->isTrue();
            $this->boolean($member->store())->isTrue();
            $mids[] = $member->id;

            //set first member displayed publically an active and up to date member
            if ($member->appearsInMembersList() && !$member->isDueFree() && $first === true) {
                $first = false;
                $contrib = new \Galette\Entity\Contribution($this->zdb, $this->login);

                $now = new \DateTime();
                $bdate = clone $now;
                $bdate->modify('-1 day');
                $edate = clone $bdate;
                $edate->modify('+1 year');

                $cdata = [
                    \Galette\Entity\Adherent::PK    => $member->id,
                    'type_paiement_cotis'           => \Galette\Entity\PaymentType::CASH,
                    'montant_cotis'                 => 20,
                    'date_enreg'                    => $bdate->format('Y-m-d'),
                    'date_debut_cotis'              => $bdate->format('Y-m-d'),
                    'date_fin_cotis'                => $edate->format('Y-m-d'),
                    \Galette\Entity\ContributionsTypes::PK  => \Galette\Entity\ContributionsTypes::DEFAULT_TYPE
                ];
                $this->boolean($contrib->check($cdata, [], []))->isTrue();
                $this->boolean($contrib->store())->isTrue();
            }

            //only one member is due free. add him a photo.
            if ($member->isDueFree()) {
                $file = GALETTE_TEMPIMAGES_PATH . 'fakephoto.jpg';
                $url = GALETTE_ROOT . '../tests/fake_image.jpg';

                $copied = copy($url, $file);
                $this->boolean($copied)->isTrue();
                $_FILES = array(
                    'photo' => array(
                        'name'      => 'fakephoto.jpg',
                        'type'      => 'image/jpeg',
                        'size'      => filesize($file),
                        'tmp_name'  => $file,
                        'error'     => 0
                    )
                );
                $this->integer((int)$member->picture->store($_FILES['photo'], true))->isGreaterThan(0);
            }
        }

        $this->mids = $mids;
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
     * Delete groups
     *
     * @return void
     */
    private function deleteGroups()
    {
        //clean groups
        $delete = $this->zdb->delete(\Galette\Entity\Group::GROUPSUSERS_TABLE);
        $this->zdb->execute($delete);

        $delete = $this->zdb->delete(\Galette\Entity\Group::TABLE);
        $delete->where->isNotNull('parent_group');
        $this->zdb->execute($delete);

        $delete = $this->zdb->delete(\Galette\Entity\Group::TABLE);
        $this->zdb->execute($delete);
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
        $filters = new \Galette\Filters\MembersList();
        $filters->filter_account = \Galette\Repository\Members::ACTIVE_ACCOUNT;
        $members = new \Galette\Repository\Members($filters);
        $this->object($members->getFilters())->isIdenticalTo($filters);
        $this->array($members->getErrors())->isEmpty();
        $list = $members->getList();

        $this->integer($list->count())->isIdenticalTo(9);

        //Filter on inactive accounts
        $filters = new \Galette\Filters\MembersList();
        $filters->filter_account = \Galette\Repository\Members::INACTIVE_ACCOUNT;
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->integer($list->count())->isIdenticalTo(1);

        //Filter with email
        $filters = new \Galette\Filters\MembersList();
        $filters->email_filter = \Galette\Repository\Members::FILTER_W_EMAIL;
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->integer($list->count())->isIdenticalTo(10);

        //Filter without email
        $filters = new \Galette\Filters\MembersList();
        $filters->email_filter = \Galette\Repository\Members::FILTER_WO_EMAIL;
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->integer($list->count())->isIdenticalTo(0);

        //Search on job
        $filters = new \Galette\Filters\MembersList();
        $filters->filter_str = 'eur';
        $filters->field_filter = \Galette\Repository\Members::FILTER_JOB;
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->integer($list->count())->isIdenticalTo(3);
        //Search on address
        $filters = new \Galette\Filters\MembersList();
        $filters->filter_str = 'avenue';
        $filters->field_filter = \Galette\Repository\Members::FILTER_ADDRESS;
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->integer($list->count())->isIdenticalTo(2);

        //search on email
        $filters = new \Galette\Filters\MembersList();
        $filters->filter_str = '.fr';
        $filters->field_filter = \Galette\Repository\Members::FILTER_MAIL;
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->integer($list->count())->isIdenticalTo(6);

        //search on name
        $filters = new \Galette\Filters\MembersList();
        $filters->filter_str = 'marc';
        $filters->field_filter = \Galette\Repository\Members::FILTER_NAME;
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        //search on company
        $filters = new \Galette\Filters\MembersList();
        $filters->filter_str = 'Galette';
        $filters->field_filter = \Galette\Repository\Members::FILTER_COMPANY_NAME;
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->integer($list->count())->isIdenticalTo(2);

        //search on infos
        $filters = new \Galette\Filters\MembersList();
        $filters->filter_str = 'any';
        $filters->field_filter = \Galette\Repository\Members::FILTER_INFOS;
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->integer($list->count())->isIdenticalTo(0);

        //search on member number
        $filters = new \Galette\Filters\MembersList();
        $filters->filter_str = $this->mids[2];
        $filters->field_filter = \Galette\Repository\Members::FILTER_NUMBER;
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->integer($list->count())->isIdenticalTo(1);

        //search on membership
        $filters = new \Galette\Filters\MembersList();
        $filters->membership_filter = \Galette\Repository\Members::MEMBERSHIP_UP2DATE;
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->integer($list->count())->isIdenticalTo(2);

        //membership staff
        $filters->membership_filter = \Galette\Repository\Members::MEMBERSHIP_STAFF;
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->integer($list->count())->isIdenticalTo(1);

        //membership admin
        $filters->membership_filter = \Galette\Repository\Members::MEMBERSHIP_ADMIN;
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->integer($list->count())->isIdenticalTo(1);

        //membership never
        $filters->membership_filter = \Galette\Repository\Members::MEMBERSHIP_NEVER;
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->integer($list->count())->isIdenticalTo(8);

        //membership never
        $filters->membership_filter = \Galette\Repository\Members::MEMBERSHIP_NONE;
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->integer($list->count())->isIdenticalTo(5);

        //membership late
        $filters->membership_filter = \Galette\Repository\Members::MEMBERSHIP_LATE;
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->integer($list->count())->isIdenticalTo(0);

        //membership never
        $filters->membership_filter = \Galette\Repository\Members::MEMBERSHIP_NEARLY;
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->integer($list->count())->isIdenticalTo(0);

        //Search on groups
        //group is ignored if it does not exists... TODO: create a group
        /*$filters = new \Galette\Filters\MembersList();
        $filters->group_filter = 3;
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->integer($list->count())->isIdenticalTo(0);*/

        // ADVANCED SEARCH

        //search on contribution begin date
        $filters = new \Galette\Filters\AdvancedMembersList();
        $contribdate = new \DateTime();
        $contribdate->modify('+2 days');
        $filters->contrib_begin_date_begin = $contribdate->format('Y-m-d');
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->integer($list->count())->isIdenticalTo(0);

        $contribdate->modify('-5 days');
        $filters->contrib_begin_date_begin = $contribdate->format('Y-m-d');
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();
        $this->integer($list->count())->isIdenticalTo(1);

        //search on contribution end date
        $filters = new \Galette\Filters\AdvancedMembersList();
        //$contribdate = new \DateTime();
        //$contribdate->modify('+2 years');
        $filters->contrib_begin_date_end = $contribdate->format('Y-m-d');
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->integer($list->count())->isIdenticalTo(0);

        $contribdate->modify('+5 days');
        $filters->contrib_begin_date_end = $contribdate->format('Y-m-d');
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();
        $this->integer($list->count())->isIdenticalTo(1);

        //search on public info visibility
        $filters = new \Galette\Filters\AdvancedMembersList();
        $filters->show_public_infos = \Galette\Repository\Members::FILTER_W_PUBINFOS;
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->integer($list->count())->isIdenticalTo(6);

        $filters->show_public_infos = \Galette\Repository\Members::FILTER_WO_PUBINFOS;
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->integer($list->count())->isIdenticalTo(4);

        $filters->show_public_infos = \Galette\Repository\Members::FILTER_DC_PUBINFOS;
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->integer($list->count())->isIdenticalTo(10);

        //search on status
        $filters = new \Galette\Filters\AdvancedMembersList();
        $filters->status = \Galette\Entity\Status::DEFAULT_STATUS;
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->integer($list->count())->isIdenticalTo(5);

        //search on contribution amount
        $filters = new \Galette\Filters\AdvancedMembersList();
        $filters->contrib_min_amount = 30.0;
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->integer($list->count())->isIdenticalTo(0);

        $filters->contrib_min_amount = 20.0;
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->integer($list->count())->isIdenticalTo(1);

        $filters = new \Galette\Filters\AdvancedMembersList();
        $filters->contrib_max_amount = 5.0;
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->integer($list->count())->isIdenticalTo(0);

        $filters->contrib_max_amount = 20.0;
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->integer($list->count())->isIdenticalTo(1);

        //search on contribution type
        $filters = new \Galette\Filters\AdvancedMembersList();
        $filters->contributions_types = \Galette\Entity\ContributionsTypes::DEFAULT_TYPE;
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->integer($list->count())->isIdenticalTo(1);

        $filters->contributions_types = [
            \Galette\Entity\ContributionsTypes::DEFAULT_TYPE,
            \Galette\Entity\ContributionsTypes::DEFAULT_TYPE + 1
        ];
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->integer($list->count())->isIdenticalTo(1);

        $filters->contributions_types = \Galette\Entity\ContributionsTypes::DEFAULT_TYPE + 1;
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->integer($list->count())->isIdenticalTo(0);

        //search on payment type
        $filters = new \Galette\Filters\AdvancedMembersList();
        $filters->payments_types = \Galette\Entity\PaymentType::CASH;
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->integer($list->count())->isIdenticalTo(1);

        $filters->payments_types = [
            \Galette\Entity\PaymentType::CASH,
            \Galette\Entity\PaymentType::CHECK
        ];
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->integer($list->count())->isIdenticalTo(1);

        $filters->payments_types = [
            \Galette\Entity\PaymentType::CHECK
        ];
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->integer($list->count())->isIdenticalTo(0);

        //not filtered list
        $members = new \Galette\Repository\Members();
        $list = $members->getList(true);

        $this->array($list)
            ->hasSize(10)
            ->object[0]->isInstanceOf('\Galette\Entity\Adherent');

        //get list with specified fields
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
        $this->integer($list->count())->isIdenticalTo(1);

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

        //search on infos - as admin
        global $login;
        $login = new \mock\Galette\Core\Login($this->zdb, $this->i18n);
        $this->calling($login)->isAdmin = true;

        $filters = new \Galette\Filters\MembersList();
        $filters->filter_str = 'any';
        $filters->field_filter = \Galette\Repository\Members::FILTER_INFOS;
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->integer($list->count())->isIdenticalTo(0);
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
        $this->array($list)->hasSize(2);
        $this->integer($members->getCount())->isIdenticalTo(2);

        $adh = $list[0];

        $this->object($adh)->isInstanceOf('\Galette\Entity\Adherent');
        $this->boolean($adh->appearsInMembersList())->isTrue();
        $this->variable($adh->_picture)->isNull();

        $list = $members->getPublicList(true);
        $this->array($list)->hasSize(1);
        $this->integer($members->getCount())->isIdenticalTo(1);

        $adh = $list[0];

        $this->object($adh)->isInstanceOf('\Galette\Entity\Adherent');
        $this->boolean($adh->appearsInMembersList())->isTrue();

        $this->boolean($adh->hasPicture())->isTrue();
    }

    /**
     * Test search on groups
     *
     * @return void
     */
    public function testGroupsSearch()
    {
        $members = new \Galette\Repository\Members();
        $list = $members->getList(true);
        $this->integer(count($list))->isIdenticalTo(10);
        $this->integer($members->getCount())->isIdenticalTo(10);

        $group = new \Galette\Entity\Group();
        $group->setName('World');
        $this->boolean($group->store())->isTrue();
        $world = $group->getId();
        $this->integer($world)->isGreaterThan(0);

        //cannot be parent of itself
        $this
            ->exception(
                function () use ($group) {
                    $group->setParentGroup($group->getId());
                }
            )->hasMessage('Group `World` cannot be set as parent!');

        $group = new \Galette\Entity\Group();
        $group->setName('Europe')->setParentGroup($world);
        $this->boolean($group->store())->isTrue();
        $europe = $group->getId();
        $this->integer($europe)->isGreaterThan(0);
        $this->boolean($group->setMembers([$list[0], $list[1]]))->isTrue();

        $group = new \Galette\Entity\Group();
        $group->setName('Asia')->setParentGroup($world);
        $this->boolean($group->store())->isTrue();
        $asia = $group->getId();
        $this->integer($asia)->isGreaterThan(0);
        $this->boolean($group->setMembers([$list[2], $list[3]]))->isTrue();

        $group = new \Galette\Entity\Group();
        $group->setName('Africa')->setParentGroup($world);
        $this->boolean($group->store())->isTrue();
        $africa = $group->getId();
        $this->integer($africa)->isGreaterThan(0);
        $this->boolean($group->setMembers([$list[4], $list[5]]))->isTrue();

        $group = new \Galette\Entity\Group();
        $group->setName('America')->setParentGroup($world);
        $this->boolean($group->store())->isTrue();
        $america = $group->getId();
        $this->integer($america)->isGreaterThan(0);
        $this->boolean($group->setMembers([$list[6], $list[7]]))->isTrue();

        $group = new \Galette\Entity\Group();
        $group->setName('Antarctica')->setParentGroup($world);
        $this->boolean($group->store())->isTrue();
        $antarctica = $group->getId();
        $this->integer($america)->isGreaterThan(0);
        $this->boolean($group->setMembers([$list[8], $list[9]]))->isTrue();

        $group = new \Galette\Entity\Group();
        $group->setName('Activities');
        $this->boolean($group->store())->isTrue();
        $activities = $group->getId();
        $this->integer($activities)->isGreaterThan(0);

        $group = new \Galette\Entity\Group();
        $group->setName('Pony')->setParentGroup($activities);
        $this->boolean($group->store())->isTrue();
        $pony = $group->getId();
        $this->integer($pony)->isGreaterThan(0);
        //assign Members to group
        $members = [];
        for ($i = 0; $i < 5; ++$i) {
            $members[] = $list[$i];
        }
        $this->boolean($group->setMembers($members))->isTrue();
        $this->integer(count($group->getMembers()))->isIdenticalTo(5);

        $group = new \Galette\Entity\Group();
        $group->setName('Swimming pool')->setParentGroup($activities);
        $this->boolean($group->store())->isTrue();
        $pool = $group->getId();
        $this->integer($pool)->isGreaterThan(0);
        //assign Members to group
        $members = [$list[0]];
        for ($i = 5; $i < 10; ++$i) {
            $members[] = $list[$i];
        }
        $this->boolean($group->setMembers($members))->isTrue();
        $this->integer(count($group->getMembers()))->isIdenticalTo(6);

        //all groups/members are setup. try to find them now.
        $filters = new \Galette\Filters\AdvancedMembersList();
        $filters->groups_search_log_op = \Galette\Filters\AdvancedMembersList::OP_OR;
        $filters->groups_search = ['idx' => 1, 'group' => $europe];
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->integer($list->count())->isIdenticalTo(2);

        $filters->groups_search = ['idx' => 2, 'group' => $pony];
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->integer($list->count())->isIdenticalTo(5);

        $filters->groups_search_log_op = \Galette\Filters\AdvancedMembersList::OP_AND;
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->integer($list->count())->isIdenticalTo(2);

        //another try
        $filters = new \Galette\Filters\AdvancedMembersList();
        $filters->groups_search_log_op = \Galette\Filters\AdvancedMembersList::OP_OR;
        $filters->groups_search = ['idx' => 1, 'group' => $africa];
        $filters->groups_search = ['idx' => 2, 'group' => $pony];
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->integer($list->count())->isIdenticalTo(6);

        $filters->groups_search_log_op = \Galette\Filters\AdvancedMembersList::OP_AND;
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->integer($list->count())->isIdenticalTo(1);
    }

    /**
     * Test reminders count
     *
     * @return void
     */
    public function testGetRemindersCount()
    {
        $members = new \Galette\Repository\Members();
        $counts = $members->getRemindersCount();
        $this->array($counts)->hasSize(3)
            ->hasKeys(['impending', 'nomail', 'late']);
        $this->integer((int)$counts['impending'])->isIdenticalTo(0);
        $this->integer((int)$counts['late'])->isIdenticalTo(0);
        $this->integer((int)$counts['nomail']['impending'])->isIdenticalTo(0);
        $this->integer((int)$counts['nomail']['late'])->isIdenticalTo(0);

        //create a close to be expired contribution
        $contrib = new \Galette\Entity\Contribution($this->zdb, $this->login);
        $now = new \DateTime();
        $edate = clone $now;
        $edate->modify('+6 days');
        $bdate = clone $edate;
        $bdate->modify('-1 year');

        $cdata = [
            \Galette\Entity\Adherent::PK    => $this->mids[9],
            'type_paiement_cotis'           => \Galette\Entity\PaymentType::CASH,
            'montant_cotis'                 => 20,
            'date_enreg'                    => $bdate->format('Y-m-d'),
            'date_debut_cotis'              => $bdate->format('Y-m-d'),
            'date_fin_cotis'                => $edate->format('Y-m-d'),
            \Galette\Entity\ContributionsTypes::PK  => \Galette\Entity\ContributionsTypes::DEFAULT_TYPE
        ];
        $this->boolean($contrib->check($cdata, [], []))->isTrue();
        $this->boolean($contrib->store())->isTrue();

        //create an expired contribution
        $edate = clone $now;
        $edate->modify('-6 days');
        $bdate = clone $edate;
        $bdate->modify('-1 year');

        $cdata = [
            \Galette\Entity\Adherent::PK    => $this->mids[8],
            'type_paiement_cotis'           => \Galette\Entity\PaymentType::CHECK,
            'montant_cotis'                 => 20,
            'date_enreg'                    => $bdate->format('Y-m-d'),
            'date_debut_cotis'              => $bdate->format('Y-m-d'),
            'date_fin_cotis'                => $edate->format('Y-m-d'),
            \Galette\Entity\ContributionsTypes::PK  => \Galette\Entity\ContributionsTypes::DEFAULT_TYPE
        ];
        $this->boolean($contrib->check($cdata, [], []))->isTrue();
        $this->boolean($contrib->store())->isTrue();

        $counts = $members->getRemindersCount();
        $this->array($counts)->hasSize(3)
            ->hasKeys(['impending', 'nomail', 'late']);
        $this->integer((int)$counts['impending'])->isIdenticalTo(1);
        $this->integer((int)$counts['late'])->isIdenticalTo(1);
        $this->integer((int)$counts['nomail']['impending'])->isIdenticalTo(0);
        $this->integer((int)$counts['nomail']['late'])->isIdenticalTo(0);

        //member without email
        $nomail = new \Galette\Entity\Adherent($this->zdb);
        $nomail->setDependencies(
            $this->preferences,
            $this->members_fields,
            $this->history
        );
        $this->boolean($nomail->load($this->mids[9]));
        $nomail->setDuplicate();
        $this->boolean($nomail->check(['login' => 'nomail_login'], [], []))->isTrue();
        $stored = $nomail->store();
        if (!$stored) {
            var_dump($nomail->getErrors());
        }
        $this->boolean($nomail->store())->isTrue();
        $nomail_id = $nomail->id;

        //create an expired contribution
        $cdata = [
            \Galette\Entity\Adherent::PK    => $nomail_id,
            'type_paiement_cotis'           => \Galette\Entity\PaymentType::CHECK,
            'montant_cotis'                 => 20,
            'date_enreg'                    => $bdate->format('Y-m-d'),
            'date_debut_cotis'              => $bdate->format('Y-m-d'),
            'date_fin_cotis'                => $edate->format('Y-m-d'),
            \Galette\Entity\ContributionsTypes::PK  => \Galette\Entity\ContributionsTypes::DEFAULT_TYPE
        ];
        $this->boolean($contrib->check($cdata, [], []))->isTrue();
        $this->boolean($contrib->store())->isTrue();

        $counts = $members->getRemindersCount();
        $this->array($counts)->hasSize(3)
            ->hasKeys(['impending', 'nomail', 'late']);
        $this->integer((int)$counts['impending'])->isIdenticalTo(1);
        $this->integer((int)$counts['late'])->isIdenticalTo(1);
        $this->integer((int)$counts['nomail']['impending'])->isIdenticalTo(0);
        $this->integer((int)$counts['nomail']['late'])->isIdenticalTo(1);

        //cleanup contribution
        $delete = $this->zdb->delete(\Galette\Entity\Contribution::TABLE);
        $delete->where([\Galette\Entity\Adherent::PK => $nomail_id]);
        $this->zdb->execute($delete);

        //create a close to be expired contribution
        $contrib = new \Galette\Entity\Contribution($this->zdb, $this->login);
        $now = new \DateTime();
        $edate = clone $now;
        $edate->modify('+6 days');
        $bdate = clone $edate;
        $bdate->modify('-1 year');

        $cdata = [
            \Galette\Entity\Adherent::PK    => $nomail_id,
            'type_paiement_cotis'           => \Galette\Entity\PaymentType::CASH,
            'montant_cotis'                 => 20,
            'date_enreg'                    => $bdate->format('Y-m-d'),
            'date_debut_cotis'              => $bdate->format('Y-m-d'),
            'date_fin_cotis'                => $edate->format('Y-m-d'),
            \Galette\Entity\ContributionsTypes::PK  => \Galette\Entity\ContributionsTypes::DEFAULT_TYPE
        ];
        $this->boolean($contrib->check($cdata, [], []))->isTrue();
        $this->boolean($contrib->store())->isTrue();

        $counts = $members->getRemindersCount();
        $this->array($counts)->hasSize(3)
            ->hasKeys(['impending', 'nomail', 'late']);
        $this->integer((int)$counts['impending'])->isIdenticalTo(1);
        $this->integer((int)$counts['late'])->isIdenticalTo(1);
        $this->integer((int)$counts['nomail']['impending'])->isIdenticalTo(1);
        $this->integer((int)$counts['nomail']['late'])->isIdenticalTo(0);

        //cleanup contribution
        $delete = $this->zdb->delete(\Galette\Entity\Contribution::TABLE);
        $delete->where([\Galette\Entity\Adherent::PK => $nomail_id]);
        $this->zdb->execute($delete);

        $delete = $this->zdb->delete(\Galette\Entity\Adherent::TABLE);
        $delete->where(['id_adh' => $nomail_id]);
        $this->zdb->execute($delete);
    }

    /**
     * Test selectized members
     *
     * @return void
     */
    public function testGetSelectizedMembers()
    {
        $members = new \Galette\Repository\Members();
        $selectized = $members->getSelectizedMembers($this->zdb);
        $this->array($selectized)->hasSize(10);
    }

    /**
     * Test getArrayList
     *
     * @return void
     */
    public function testGetArrayList()
    {
        $members = new \Galette\Repository\Members();

        $this->boolean($members->getArrayList($this->mids[0]))->isFalse();

        $selected = [
            $this->mids[0],
            $this->mids[3],
            $this->mids[6],
            $this->mids[9]
        ];
        $list = $members->getArrayList($selected, ['nom_adh', 'prenom_adh']);
        $this->array($list)->hasSize(4);
    }
}
