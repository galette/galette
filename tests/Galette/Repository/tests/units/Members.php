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

namespace Galette\Repository\test\units;

use Galette\GaletteTestCase;

/**
 * Members repository tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Members extends GaletteTestCase
{
    protected int $seed = 335689;
    private array $mids = [];

    private ?string $contents_table = null;

    /**
     * Set up tests
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->contents_table = null;
        $this->createMembers();
    }

    /**
     * Tear down tests
     *
     * @return void
     */
    public function tearDown(): void
    {
        parent::tearDown();

        $this->deleteGroups();
        $this->deleteMembers();

        $delete = $this->zdb->delete(\Galette\Entity\DynamicFieldsHandle::TABLE);
        $this->zdb->execute($delete);
        $delete = $this->zdb->delete(\Galette\DynamicFields\DynamicField::TABLE);
        $this->zdb->execute($delete);
        //cleanup dynamic translations
        $delete = $this->zdb->delete(\Galette\Core\L10n::TABLE);
        $delete->where([
            'text_orig' => [
                'Dynamic choice field',
                'Dynamic date field',
                'Dynamic text field'
            ]
        ]);
        $this->zdb->execute($delete);

        if ($this->contents_table !== null) {
            $this->zdb->drop($this->contents_table);
        }
    }

    /**
     * Create members and store their id
     *
     * @return void
     */
    private function createMembers(): void
    {
        global $zdb, $login, $hist, $i18n; // globals :(
        $zdb = $this->zdb;
        $login = $this->login;
        $hist = $this->history;
        $i18n = $this->i18n;

        $this->logSuperAdmin();
        try {
            $this->deleteMembers();
        } catch (\Exception $e) {
            //empty catch
        }

        $status = $this->container->get(\Galette\Entity\Status::class);
        if (count($status->getList()) === 0) {
            $res = $status->installInit();
            $this->assertTrue($res);
        }

        $contribtypes = new \Galette\Entity\ContributionsTypes($this->zdb);
        if (count($contribtypes->getCompleteList()) === 0) {
            $res = $contribtypes->installInit();
            $this->assertTrue($res);
        }


        $tests_members = json_decode(file_get_contents(GALETTE_TESTS_PATH . '/fixtures/tests_members.json'));

        $mids = [];
        $first = true;
        $this->logSuperAdmin();
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
            $this->assertTrue($member->check($test_member, [], []));
            $this->assertTrue($member->store());
            $mids[] = $member->id;

            //set first member displayed publicly an active and up-to-date member
            if ($member->appearsInMembersList() && !$member->isDueFree() && $first === true) {
                $first = false;
                $contrib = new \Galette\Entity\Contribution($this->zdb, $this->login);

                $now = new \DateTime();
                $begin_date = clone $now;
                $begin_date->sub(new \DateInterval('P1D'));
                $due_date = clone $begin_date;
                $due_date->sub(new \DateInterval('P1D'));
                $due_date->add(new \DateInterval('P1Y'));

                $cdata = [
                    \Galette\Entity\Adherent::PK    => $member->id,
                    'type_paiement_cotis'           => \Galette\Entity\PaymentType::CASH,
                    'montant_cotis'                 => 20,
                    'date_enreg'                    => $begin_date->format('Y-m-d'),
                    'date_debut_cotis'              => $begin_date->format('Y-m-d'),
                    'date_fin_cotis'                => $due_date->format('Y-m-d'),
                    \Galette\Entity\ContributionsTypes::PK  => 1 // annual fee
                ];
                $this->assertTrue($contrib->check($cdata, [], []));
                $this->assertTrue($contrib->store());
            }

            //only one member is due free. add him a photo.
            if ($member->isDueFree()) {
                $file = GALETTE_TEMPIMAGES_PATH . 'fakephoto.jpg';
                $url = GALETTE_ROOT . '../tests/fake_image.jpg';

                $copied = copy($url, $file);
                $this->assertTrue($copied);
                $_FILES = array(
                    'photo' => array(
                        'name'      => 'fakephoto.jpg',
                        'type'      => 'image/jpeg',
                        'size'      => filesize($file),
                        'tmp_name'  => $file,
                        'error'     => 0
                    )
                );
                $this->assertGreaterThan(0, (int)$member->picture->store($_FILES['photo'], true));
                $this->expectLogEntry(\Analog::ERROR, 'Unable to remove picture database entry for ' . $member->id);
            }
        }
        $this->login->logOut();

        $this->login->logOut();
        $this->mids = $mids;
    }

    /**
     * Delete member
     *
     * @return void
     */
    private function deleteMembers(): void
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
            \Laminas\Db\Adapter\Adapter::QUERY_MODE_EXECUTE
        );

        //FIXME: Photos should be removed, but this fail for now :(
        $this->zdb->db->query(
            'TRUNCATE TABLE ' . PREFIX_DB . \Galette\Core\Picture::TABLE,
            \Laminas\Db\Adapter\Adapter::QUERY_MODE_EXECUTE
        );
    }

    /**
     * Delete groups
     *
     * @return void
     */
    private function deleteGroups(): void
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
    public function testGetList(): void
    {
        $members = new \Galette\Repository\Members();

        $list = $members->getList();
        $this->assertSame(10, $list->count());

        $list = $members->getEmails($this->zdb);
        $this->assertCount(10, $list);
        $this->assertArrayHasKey('georges.didier@perrot.fr', $list);
        $this->assertArrayHasKey('marc25@pires.org', $list);

        //Filter on active accounts
        $filters = new \Galette\Filters\MembersList();
        $filters->filter_account = \Galette\Repository\Members::ACTIVE_ACCOUNT;
        $members = new \Galette\Repository\Members($filters);
        $this->assertSame($filters, $members->getFilters());
        $this->assertEmpty($members->getErrors());
        $list = $members->getList();

        $this->assertSame(9, $list->count());

        //Filter on inactive accounts
        $filters = new \Galette\Filters\MembersList();
        $filters->filter_account = \Galette\Repository\Members::INACTIVE_ACCOUNT;
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->assertSame(1, $list->count());

        //Filter with email
        $filters = new \Galette\Filters\MembersList();
        $filters->email_filter = \Galette\Repository\Members::FILTER_W_EMAIL;
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->assertSame(10, $list->count());

        //Filter without email
        $filters = new \Galette\Filters\MembersList();
        $filters->email_filter = \Galette\Repository\Members::FILTER_WO_EMAIL;
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->assertSame(0, $list->count());

        //Search on job
        $filters = new \Galette\Filters\MembersList();
        $filters->filter_str = 'eur';
        $filters->field_filter = \Galette\Repository\Members::FILTER_JOB;
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->assertSame(3, $list->count());

        //Search on address
        $filters = new \Galette\Filters\MembersList();
        $filters->filter_str = 'avenue';
        $filters->field_filter = \Galette\Repository\Members::FILTER_ADDRESS;
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->assertSame(2, $list->count());

        //search on email
        $filters = new \Galette\Filters\MembersList();
        $filters->filter_str = '.fr';
        $filters->field_filter = \Galette\Repository\Members::FILTER_MAIL;
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->assertSame(6, $list->count());

        //search on name
        $filters = new \Galette\Filters\MembersList();
        $filters->filter_str = 'marc';
        $filters->field_filter = \Galette\Repository\Members::FILTER_NAME;
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->assertSame(4, $list->count());

        //search on company
        $filters = new \Galette\Filters\MembersList();
        $filters->filter_str = 'Galette';
        $filters->field_filter = \Galette\Repository\Members::FILTER_COMPANY_NAME;
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->assertSame(2, $list->count());

        //search on infos
        $filters = new \Galette\Filters\MembersList();
        $filters->filter_str = 'any';
        $filters->field_filter = \Galette\Repository\Members::FILTER_INFOS;
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->assertSame(0, $list->count());

        //search on member number
        $filters = new \Galette\Filters\MembersList();
        $filters->filter_str = (string)$this->mids[2];
        $filters->field_filter = \Galette\Repository\Members::FILTER_ID;
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->assertSame(1, $list->count());

        //search on membership
        $filters = new \Galette\Filters\MembersList();
        $filters->membership_filter = \Galette\Repository\Members::MEMBERSHIP_UP2DATE;
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->assertSame(2, $list->count());

        //membership staff
        $filters->membership_filter = \Galette\Repository\Members::MEMBERSHIP_STAFF;
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->assertSame(1, $list->count());

        //membership admin
        $filters->membership_filter = \Galette\Repository\Members::MEMBERSHIP_ADMIN;
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->assertSame(1, $list->count());

        //membership never
        $filters->membership_filter = \Galette\Repository\Members::MEMBERSHIP_NEVER;
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->assertSame(8, $list->count());

        //membership none
        $filters->membership_filter = \Galette\Repository\Members::MEMBERSHIP_NONE;
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->assertSame(5, $list->count());

        //membership late
        $filters->membership_filter = \Galette\Repository\Members::MEMBERSHIP_LATE;
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->assertSame(0, $list->count());

        //membership nearly expired
        $filters->membership_filter = \Galette\Repository\Members::MEMBERSHIP_NEARLY;
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->assertSame(0, $list->count());

        //Search on groups
        //group is ignored if it does not exists... TODO: create a group
        /*$filters = new \Galette\Filters\MembersList();
        $filters->group_filter = 3;
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->assertSame(0, $list->count());*/

        // ADVANCED SEARCH

        //search on contribution begin date
        $filters = new \Galette\Filters\AdvancedMembersList();
        $contribdate = new \DateTime();
        $contribdate->modify('+2 days');
        $filters->contrib_begin_date_begin = $contribdate->format('Y-m-d');
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->assertSame(0, $list->count());

        $contribdate->modify('-5 days');
        $filters->contrib_begin_date_begin = $contribdate->format('Y-m-d');
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();
        $this->assertSame(1, $list->count());

        //search on contribution end date
        $filters = new \Galette\Filters\AdvancedMembersList();
        //$contribdate = new \DateTime();
        //$contribdate->modify('+2 years');
        $filters->contrib_begin_date_end = $contribdate->format('Y-m-d');
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->assertSame(0, $list->count());

        $contribdate->modify('+5 days');
        $filters->contrib_begin_date_end = $contribdate->format('Y-m-d');
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();
        $this->assertSame(1, $list->count());

        //search on public info visibility
        $filters = new \Galette\Filters\AdvancedMembersList();
        $filters->show_public_infos = \Galette\Repository\Members::FILTER_W_PUBINFOS;
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->assertSame(6, $list->count());

        $filters->show_public_infos = \Galette\Repository\Members::FILTER_WO_PUBINFOS;
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->assertSame(4, $list->count());

        $filters->show_public_infos = \Galette\Repository\Members::FILTER_DC_PUBINFOS;
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->assertSame(10, $list->count());

        //search on status
        $filters = new \Galette\Filters\AdvancedMembersList();
        $filters->status = \Galette\Entity\Status::DEFAULT_STATUS;
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->assertSame(5, $list->count());

        //search on status
        $filters = new \Galette\Filters\AdvancedMembersList();
        $filters->status = [(string)\Galette\Entity\Status::DEFAULT_STATUS];
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();
        $this->assertSame(5, $list->count());

        //search on non existing status
        $filters = new \Galette\Filters\AdvancedMembersList();
        $filters->status = [999];
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();
        $this->assertSame(10, $list->count());
        $this->expectLogEntry(\Analog::WARNING, 'Status #999 does not exists!');

        //search on status from free search
        $filters = new \Galette\Filters\AdvancedMembersList();
        $filters->free_search = [
            'idx' => 1,
            'field' => \Galette\Entity\Status::PK,
            'type' => 0,
            'search' => \Galette\Entity\Status::DEFAULT_STATUS,
            'log_op' => $filters::OP_AND,
            'qry_op' => $filters::OP_EQUALS
        ];
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->assertSame(5, $list->count());

        //search on contribution amount
        $filters = new \Galette\Filters\AdvancedMembersList();
        $filters->contrib_min_amount = 30.0;
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->assertSame(0, $list->count());

        $filters->contrib_min_amount = 20.0;
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->assertSame(1, $list->count());

        $filters = new \Galette\Filters\AdvancedMembersList();
        $filters->contrib_max_amount = 5.0;
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->assertSame(0, $list->count());

        $filters->contrib_max_amount = 20.0;
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->assertSame(1, $list->count());

        //search on contribution type
        $filters = new \Galette\Filters\AdvancedMembersList();
        $filters->contributions_types = 1;
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->assertSame(1, $list->count());

        //search on contribution type
        $filters = new \Galette\Filters\AdvancedMembersList();
        $filters->contributions_types = '1';
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->assertSame(1, $list->count());

        $filters->contributions_types = [
            1, // annual fee
            2 //reduced annual fee
        ];
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->assertSame(1, $list->count());

        $filters->contributions_types = 2;
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->assertSame(0, $list->count());

        //search on non existing contribution type
        $filters = new \Galette\Filters\AdvancedMembersList();
        $filters->contributions_types = 999;
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->assertSame(10, $list->count());
        $this->expectLogEntry(\Analog::WARNING, 'Contribution type #999 does not exists!');

        //search on payment type
        $filters = new \Galette\Filters\AdvancedMembersList();
        $filters->payments_types = \Galette\Entity\PaymentType::CASH;
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->assertSame(1, $list->count());

        $filters->payments_types = [
            \Galette\Entity\PaymentType::CASH,
            \Galette\Entity\PaymentType::CHECK
        ];
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->assertSame(1, $list->count());

        $filters->payments_types = [
            \Galette\Entity\PaymentType::CHECK
        ];
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->assertSame(0, $list->count());

        //not filtered list
        $members = new \Galette\Repository\Members();
        $list = $members->getList(true);

        $this->assertCount(10, $list);
        $this->assertInstanceOf(\Galette\Entity\Adherent::class, $list[0]);

        //get list with specified fields
        $members = new \Galette\Repository\Members();
        $list = $members->getList(false, ['nom_adh', 'prenom_adh', 'ville_adh']);
        $this->assertSame(10, $list->count());
        $arraylist = $list->toArray();
        foreach ($arraylist as $array) {
            $this->assertCount(5, $array);
            $this->assertArrayHasKey('nom_adh', $array);
            $this->assertArrayHasKey('prenom_adh', $array);
            $this->assertArrayHasKey('ville_adh', $array);
            $this->assertArrayHasKey('id_adh', $array);
            $this->assertArrayHasKey('priorite_statut', $array);
        }

        //get export list (no priorite_statut if not explicitely required)
        $members = new \Galette\Repository\Members();
        $list = $members->getMembersList(false, ['nom_adh', 'prenom_adh', 'ville_adh'], true, false, false, true, true);
        $this->assertSame(10, $list->count());
        $arraylist = $list->toArray();
        foreach ($arraylist as $array) {
            $this->assertCount(4, $array);
            $this->assertArrayHasKey('nom_adh', $array);
            $this->assertArrayHasKey('prenom_adh', $array);
            $this->assertArrayHasKey('ville_adh', $array);
            $this->assertArrayHasKey('id_adh', $array);
        }

        //Get staff
        $members = new \Galette\Repository\Members();
        $list = $members->getStaffMembersList();
        $this->assertSame(1, $list->count());

        //Remove 2 members
        $torm = [];
        $mids = $this->mids;
        $torm[] = array_pop($mids);
        $torm[] = array_pop($mids);
        $this->mids = $mids;

        $members = new \Galette\Repository\Members();
        $this->assertTrue($members->removeMembers($torm));

        $list = $members->getList();
        $this->assertSame(8, $list->count());

        //search on infos - as admin
        global $login;
        $login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs(array($this->zdb, $this->i18n))
            ->onlyMethods(array('isAdmin'))
            ->getMock();
        $login->method('isAdmin')->willReturn(true);

        $filters = new \Galette\Filters\MembersList();
        $filters->filter_str = 'any';
        $filters->field_filter = \Galette\Repository\Members::FILTER_INFOS;
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->assertSame(0, $list->count());
    }

    /**
     * Test getList with contribution dynamic fields
     *
     * @return void
     */
    public function testGetListContributionDynamics(): void
    {
        // Advanced search on contributions dynamic fields

        //add dynamic fields on contributions
        $field_data = [
            'form_name'         => 'contrib',
            'field_name'        => 'Dynamic text field',
            'field_perm'        => \Galette\Entity\FieldsConfig::USER_WRITE,
            'field_type'        => \Galette\DynamicFields\DynamicField::TEXT,
            'field_required'    => 1,
            'field_repeat'      => 1
        ];

        $tdf = \Galette\DynamicFields\DynamicField::getFieldType($this->zdb, $field_data['field_type']);

        $stored = $tdf->store($field_data);
        $error_detected = $tdf->getErrors();
        $warning_detected = $tdf->getWarnings();
        $this->assertTrue(
            $stored,
            implode(
                ' ',
                $tdf->getErrors() + $tdf->getWarnings()
            )
        );
        $this->assertEmpty($error_detected, implode(' ', $tdf->getErrors()));
        $this->assertEmpty($warning_detected, implode(' ', $tdf->getWarnings()));

        //new dynamic field, of type choice.
        $values = [
            'First value',
            'Second value',
            'Third value'
        ];
        $field_data = [
            'form_name'         => 'contrib',
            'field_name'        => 'Dynamic choice field',
            'field_perm'        => \Galette\Entity\FieldsConfig::USER_WRITE,
            'field_type'        => \Galette\DynamicFields\DynamicField::CHOICE,
            'field_required'    => 0,
            'field_repeat'      => 1,
            'fixed_values'      => implode("\n", $values)
        ];

        $cdf = \Galette\DynamicFields\DynamicField::getFieldType($this->zdb, $field_data['field_type']);

        $stored = $cdf->store($field_data);
        $error_detected = $cdf->getErrors();
        $warning_detected = $cdf->getWarnings();
        $this->assertTrue(
            $stored,
            implode(
                ' ',
                $cdf->getErrors() + $cdf->getWarnings()
            )
        );
        $this->assertEmpty($error_detected, implode(' ', $cdf->getErrors()));
        $this->assertEmpty($warning_detected, implode(' ', $cdf->getWarnings()));
        //cleanup dynamic choices table
        $this->contents_table = $cdf->getFixedValuesTableName($cdf->getId());

        //new dynamic field, of type date.
        $field_data = [
            'form_name'         => 'contrib',
            'field_name'        => 'Dynamic date field',
            'field_perm'        => \Galette\Entity\FieldsConfig::USER_WRITE,
            'field_type'        => \Galette\DynamicFields\DynamicField::DATE,
            'field_required'    => 0,
            'field_repeat'      => 1
        ];

        $ddf = \Galette\DynamicFields\DynamicField::getFieldType($this->zdb, $field_data['field_type']);

        $stored = $ddf->store($field_data);
        $error_detected = $ddf->getErrors();
        $warning_detected = $ddf->getWarnings();
        $this->assertTrue(
            $stored,
            implode(
                ' ',
                $ddf->getErrors() + $ddf->getWarnings()
            )
        );
        $this->assertEmpty($error_detected, implode(' ', $ddf->getErrors()));
        $this->assertEmpty($warning_detected, implode(' ', $ddf->getWarnings()));

        //search on contribution dynamic text field
        $filters = new \Galette\Filters\AdvancedMembersList();
        $filters->contrib_dynamic = [$tdf->getId() => 'text value'];
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->assertSame(0, $list->count());

        $contrib = new \Galette\Entity\Contribution($this->zdb, $this->login);

        $now = new \DateTime();
        $begin_date = clone $now;
        $begin_date->sub(new \DateInterval('P1D'));
        $due_date = clone $begin_date;
        $due_date->sub(new \DateInterval('P1D'));
        $due_date->add(new \DateInterval('P1Y'));

        $cdata = [
            \Galette\Entity\Adherent::PK    => $this->mids[0],
            'type_paiement_cotis'           => \Galette\Entity\PaymentType::CASH,
            'montant_cotis'                 => 20,
            'date_enreg'                    => $begin_date->format('Y-m-d'),
            'date_debut_cotis'              => $begin_date->format('Y-m-d'),
            'date_fin_cotis'                => $due_date->format('Y-m-d'),
            \Galette\Entity\ContributionsTypes::PK  => 4, //donation in kind
            'info_field_' . $tdf->getId() . '_1' => 'A contribution with a dynamic text value set on it'
        ];
        $this->logSuperAdmin();
        $this->assertTrue($contrib->check($cdata, [], []));
        $this->assertTrue($contrib->store());
        $this->login->logout();

        $list = $members->getList();
        $this->assertSame(1, $list->count());

        //search on contribution dynamic date field
        $filters = new \Galette\Filters\AdvancedMembersList();
        $ddate = new \DateTime('2020-01-01');
        $filters->contrib_dynamic = [$ddf->getId() => $ddate->format(__('Y-m-d'))];
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->assertSame(0, $list->count());

        $cdata += [
            'id_cotis' => $contrib->id,
            'info_field_' . $ddf->getId() . '_1' => $ddate->format(__('Y-m-d'))
        ];
        $this->logSuperAdmin();
        $this->assertTrue($contrib->check($cdata, [], []));
        $this->assertTrue($contrib->store());
        $this->login->logout();

        $list = $members->getList();
        $this->assertSame(1, $list->count());

        //search on contribution dynamic choice field
        $filters = new \Galette\Filters\AdvancedMembersList();
        $filters->contrib_dynamic = [$cdf->getId() => 2]; //3rd options is selected
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->assertSame(0, $list->count());

        $cdata += [
            'id_cotis' => $contrib->id,
            'info_field_' . $cdf->getId() . '_1' => '2'
        ];
        $this->logSuperAdmin();
        $this->assertTrue($contrib->check($cdata, [], []));
        $this->assertTrue($contrib->store());
        $this->login->logout();

        $list = $members->getList();
        $this->assertSame(1, $list->count());

        //search on multiple contribution dynamic choice field
        $filters = new \Galette\Filters\AdvancedMembersList();
        $filters->contrib_dynamic = [$cdf->getId() => [0, 2]]; //1st OR 3rd options are selected
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->assertSame(1, $list->count());
    }

    /**
     * Test getPublicList
     *
     * @return void
     */
    public function testGetPublicList(): void
    {
        $members = new \Galette\Repository\Members();

        $list = $members->getPublicList(false);
        $this->assertSame(2, $members->getCount());
        $this->assertArrayHasKey('staff', $list);
        $this->assertArrayHasKey('members', $list);

        $staff = $list['staff'];
        $list_members = $list['members'];
        $this->assertCount(1, $staff);
        $this->assertCount(1, $list_members);

        $adh = $list_members[0];

        $this->assertInstanceOf(\Galette\Entity\Adherent::class, $adh);
        $this->assertTrue($adh->appearsInMembersList());
        $this->assertNull($adh->picture);

        $list = $members->getPublicList(true);
        $this->assertSame(1, $members->getCount());

        $staff = $list['staff'];
        $list_members = $list['members'];
        $this->assertCount(1, $staff);
        $this->assertCount(0, $list_members);

        $adh = $staff[0];

        $this->assertInstanceOf(\Galette\Entity\Adherent::class, $adh);
        $this->assertTrue($adh->appearsInMembersList());

        $this->assertTrue($adh->hasPicture());
    }

    /**
     * Test search on groups
     *
     * @return void
     */
    public function testGroupsSearch(): void
    {
        $members = new \Galette\Repository\Members();
        $list = $members->getList(true);
        $this->assertSame(10, count($list));
        $this->assertSame(10, $members->getCount());

        $world_group = new \Galette\Entity\Group();
        $world_group->setName('World');
        $this->assertTrue($world_group->store());
        $world = $world_group->getId();
        $this->assertGreaterThan(0, $world);

        $group = new \Galette\Entity\Group();
        $group->setName('Europe')->setParentGroup($world);
        $this->assertTrue($group->store());
        $europe = $group->getId();
        $this->assertGreaterThan(0, $europe);
        $this->assertTrue($group->setMembers([$list[0], $list[1]]));

        $group = new \Galette\Entity\Group();
        $group->setName('Asia')->setParentGroup($world);
        $this->assertTrue($group->store());
        $asia = $group->getId();
        $this->assertGreaterThan(0, $asia);
        $this->assertTrue($group->setMembers([$list[2], $list[3]]));

        $group = new \Galette\Entity\Group();
        $group->setName('Africa')->setParentGroup($world);
        $this->assertTrue($group->store());
        $africa = $group->getId();
        $this->assertGreaterThan(0, $africa);
        $this->assertTrue($group->setMembers([$list[4], $list[5]]));

        $group = new \Galette\Entity\Group();
        $group->setName('America')->setParentGroup($world);
        $this->assertTrue($group->store());
        $america = $group->getId();
        $this->assertGreaterThan(0, $america);
        $this->assertTrue($group->setMembers([$list[6], $list[7]]));

        $group = new \Galette\Entity\Group();
        $group->setName('Antarctica')->setParentGroup($world);
        $this->assertTrue($group->store());
        $antarctica = $group->getId();
        $this->assertGreaterThan(0, $antarctica);
        $this->assertTrue($group->setMembers([$list[8], $list[9]]));

        $group = new \Galette\Entity\Group();
        $group->setName('Activities');
        $this->assertTrue($group->store());
        $activities = $group->getId();
        $this->assertGreaterThan(0, $activities);

        $group = new \Galette\Entity\Group();
        $group->setName('Pony')->setParentGroup($activities);
        $this->assertTrue($group->store());
        $pony = $group->getId();
        $this->assertGreaterThan(0, $pony);
        //assign Members to group
        $members = [];
        for ($i = 0; $i < 5; ++$i) {
            $members[] = $list[$i];
        }
        $this->assertTrue($group->setMembers($members));
        $this->assertSame(5, count($group->getMembers()));

        $group = new \Galette\Entity\Group();
        $group->setName('Swimming pool')->setParentGroup($activities);
        $this->assertTrue($group->store());
        $pool = $group->getId();
        $this->assertGreaterThan(0, $pool);
        //assign Members to group
        $members = [$list[0]];
        for ($i = 5; $i < 10; ++$i) {
            $members[] = $list[$i];
        }
        $this->assertTrue($group->setMembers($members));
        $this->assertSame(6, count($group->getMembers()));

        //all groups/members are set up. try to find them now.
        $filters = new \Galette\Filters\AdvancedMembersList();
        $filters->groups_search_log_op = \Galette\Filters\AdvancedMembersList::OP_OR;
        $filters->groups_search = ['idx' => 1, 'group' => $europe];
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->assertSame(2, $list->count());

        $filters->groups_search = ['idx' => 2, 'group' => $pony];
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->assertSame(5, $list->count());

        $filters->groups_search_log_op = \Galette\Filters\AdvancedMembersList::OP_AND;
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->assertSame(2, $list->count());

        //another try
        $filters = new \Galette\Filters\AdvancedMembersList();
        $filters->groups_search_log_op = \Galette\Filters\AdvancedMembersList::OP_OR;
        $filters->groups_search = ['idx' => 1, 'group' => $africa];
        $filters->groups_search = ['idx' => 2, 'group' => $pony];
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->assertSame(6, $list->count());

        $filters->groups_search_log_op = \Galette\Filters\AdvancedMembersList::OP_AND;
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->assertSame(1, $list->count());

        //cannot be parent of itself
        $this->expectExceptionMessage('Group `World` cannot be set as parent!');
        $world_group->setParentGroup($world_group->getId());

        //test addMember
        $world_group = new \Galette\Entity\Group($world);
        $this->assertCount(2, $world_group->getMembers());
        //add member for a new user
        $world_group->addMember($list[2]);
        $this->assertCount(3, $world_group->getMembers());

        //add same mmeber, again
        //add member for a new user
        $world_group->addMember($list[2]);
        $this->assertCount(3, $world_group->getMembers());

        $world_group = new \Galette\Entity\Group($world);
        $this->assertCount(3, $world_group->getMembers());
    }

    /**
     * Test reminders count
     *
     * @return void
     */
    public function testGetRemindersCount(): void
    {
        $this->logSuperAdmin();
        $members = new \Galette\Repository\Members();
        $counts = $members->getRemindersCount();
        $this->assertCount(3, $counts);
        $this->assertArrayHasKey('impending', $counts);
        $this->assertArrayHasKey('late', $counts);
        $this->assertArrayHasKey('nomail', $counts);

        $this->assertSame(0, (int)$counts['impending']);
        $this->assertSame(0, (int)$counts['late']);
        $this->assertSame(0, (int)$counts['nomail']['impending']);
        $this->assertSame(0, (int)$counts['nomail']['late']);

        //create a close to be expired contribution
        $contrib = new \Galette\Entity\Contribution($this->zdb, $this->login);
        $now = new \DateTime();
        $begin_date = clone $now;
        $begin_date->add(new \DateInterval('P6D'));
        $begin_date->sub(new \DateInterval('P1Y'));
        $due_date = clone $begin_date;
        $due_date->sub(new \DateInterval('P1D'));
        $due_date->add(new \DateInterval('P1Y'));

        $cdata = [
            \Galette\Entity\Adherent::PK    => $this->mids[9],
            'type_paiement_cotis'           => \Galette\Entity\PaymentType::CASH,
            'montant_cotis'                 => 20,
            'date_enreg'                    => $begin_date->format('Y-m-d'),
            'date_debut_cotis'              => $begin_date->format('Y-m-d'),
            'date_fin_cotis'                => $due_date->format('Y-m-d'),
            \Galette\Entity\ContributionsTypes::PK  => 1 // annual fee
        ];
        $this->logSuperAdmin();
        $this->assertTrue($contrib->check($cdata, [], []));
        $this->assertTrue($contrib->store());
        $this->login->logout();

        $counts = $members->getRemindersCount();
        $this->assertCount(3, $counts);
        $this->assertArrayHasKey('impending', $counts);
        $this->assertArrayHasKey('late', $counts);
        $this->assertArrayHasKey('nomail', $counts);

        $this->assertSame(1, (int)$counts['impending']);
        $this->assertSame(0, (int)$counts['late']);
        $this->assertSame(0, (int)$counts['nomail']['impending']);
        $this->assertSame(0, (int)$counts['nomail']['late']);

        //create an expired contribution
        $begin_date = clone $now;
        $begin_date->sub(new \DateInterval('P30D'));
        $begin_date->sub(new \DateInterval('P1Y'));
        $due_date = clone $begin_date;
        $due_date->sub(new \DateInterval('P1D'));
        $due_date->add(new \DateInterval('P1Y'));

        $cdata = [
            \Galette\Entity\Adherent::PK    => $this->mids[8],
            'type_paiement_cotis'           => \Galette\Entity\PaymentType::CHECK,
            'montant_cotis'                 => 20,
            'date_enreg'                    => $begin_date->format('Y-m-d'),
            'date_debut_cotis'              => $begin_date->format('Y-m-d'),
            'date_fin_cotis'                => $due_date->format('Y-m-d'),
            \Galette\Entity\ContributionsTypes::PK  => 1 // annual fee
        ];
        $this->logSuperAdmin();
        $this->assertTrue($contrib->check($cdata, [], []));
        $this->assertTrue($contrib->store());
        $this->login->logout();

        $counts = $members->getRemindersCount();
        $this->assertCount(3, $counts);
        $this->assertArrayHasKey('impending', $counts);
        $this->assertArrayHasKey('late', $counts);
        $this->assertArrayHasKey('nomail', $counts);

        $this->assertSame(1, (int)$counts['impending']);
        $this->assertSame(1, (int)$counts['late']);
        $this->assertSame(0, (int)$counts['nomail']['impending']);
        $this->assertSame(0, (int)$counts['nomail']['late']);

        //member without email
        $this->logSuperAdmin();
        $nomail = new \Galette\Entity\Adherent($this->zdb);
        $nomail->setDependencies(
            $this->preferences,
            $this->members_fields,
            $this->history
        );
        $this->assertTrue($nomail->load($this->mids[9]));
        $nomail->setDuplicate();
        $this->assertTrue($nomail->check(['login' => 'nomail_login'], [], []));
        $stored = $nomail->store();
        if (!$stored) {
            var_dump($nomail->getErrors());
        }
        $this->assertTrue($nomail->store());
        $this->login->logout();
        $nomail_id = $nomail->id;

        //create an expired contribution without email
        $cdata = [
            \Galette\Entity\Adherent::PK    => $nomail_id,
            'type_paiement_cotis'           => \Galette\Entity\PaymentType::CHECK,
            'montant_cotis'                 => 20,
            'date_enreg'                    => $begin_date->format('Y-m-d'),
            'date_debut_cotis'              => $begin_date->format('Y-m-d'),
            'date_fin_cotis'                => $due_date->format('Y-m-d'),
            \Galette\Entity\ContributionsTypes::PK  => 1 // annual fee
        ];
        $this->logSuperAdmin();
        $this->assertTrue($contrib->check($cdata, [], []));
        $this->assertTrue($contrib->store());
        $this->login->logout();

        $counts = $members->getRemindersCount();
        $this->assertCount(3, $counts);
        $this->assertArrayHasKey('impending', $counts);
        $this->assertArrayHasKey('late', $counts);
        $this->assertArrayHasKey('nomail', $counts);

        $this->assertSame(1, (int)$counts['impending']);
        $this->assertSame(1, (int)$counts['late']);
        $this->assertSame(0, (int)$counts['nomail']['impending']);
        $this->assertSame(1, (int)$counts['nomail']['late']);

        //cleanup contribution
        $delete = $this->zdb->delete(\Galette\Entity\Contribution::TABLE);
        $delete->where([\Galette\Entity\Adherent::PK => $nomail_id]);
        $this->zdb->execute($delete);

        //create a close to be expired contribution without email
        $contrib = new \Galette\Entity\Contribution($this->zdb, $this->login);
        $begin_date = clone $now;
        $begin_date->add(new \DateInterval('P6D'));
        $begin_date->sub(new \DateInterval('P1Y'));
        $due_date = clone $begin_date;
        $due_date->sub(new \DateInterval('P1D'));
        $due_date->add(new \DateInterval('P1Y'));

        $cdata = [
            \Galette\Entity\Adherent::PK    => $nomail_id,
            'type_paiement_cotis'           => \Galette\Entity\PaymentType::CASH,
            'montant_cotis'                 => 20,
            'date_enreg'                    => $begin_date->format('Y-m-d'),
            'date_debut_cotis'              => $begin_date->format('Y-m-d'),
            'date_fin_cotis'                => $due_date->format('Y-m-d'),
            \Galette\Entity\ContributionsTypes::PK  => 1 // annual fee
        ];
        $this->logSuperAdmin();
        $this->assertTrue($contrib->check($cdata, [], []));
        $this->assertTrue($contrib->store());
        $this->login->logout();

        $counts = $members->getRemindersCount();
        $this->assertCount(3, $counts);
        $this->assertArrayHasKey('impending', $counts);
        $this->assertArrayHasKey('late', $counts);
        $this->assertArrayHasKey('nomail', $counts);

        $this->assertSame(1, (int)$counts['impending']);
        $this->assertSame(1, (int)$counts['late']);
        $this->assertSame(1, (int)$counts['nomail']['impending']);
        $this->assertSame(0, (int)$counts['nomail']['late']);

        //cleanup contribution
        $delete = $this->zdb->delete(\Galette\Entity\Contribution::TABLE);
        $delete->where([\Galette\Entity\Adherent::PK => $nomail_id]);
        $this->zdb->execute($delete);

        $delete = $this->zdb->delete(\Galette\Entity\Adherent::TABLE);
        $delete->where(['id_adh' => $nomail_id]);
        $this->zdb->execute($delete);
        $this->login->logout();
    }

    /**
     * Test dropdown members
     *
     * @return void
     */
    public function testGetDropdownMembers(): void
    {
        $this->logSuperAdmin();
        $members = new \Galette\Repository\Members();
        $this->logSuperAdmin();
        $dropdown = $members->getDropdownMembers($this->zdb, $this->login);
        $this->assertCount(10, $dropdown);
        $this->login->logOut();
    }

    /**
     * Test getArrayList
     *
     * @return void
     */
    public function testGetArrayList(): void
    {
        $members = new \Galette\Repository\Members();

        $this->assertFalse($members->getArrayList($this->mids[0]));

        $selected = [
            $this->mids[0],
            $this->mids[3],
            $this->mids[6],
            $this->mids[9]
        ];
        $list = $members->getArrayList($selected, ['nom_adh', 'prenom_adh']);
        $this->assertCount(4, $list);
    }

    /**
     * Test getMembersList
     *
     * @return void
     */
    public function testRemoveMembers(): void
    {
        $this->logSuperAdmin();
        $members = new \Galette\Repository\Members();

        //Filter on inactive accounts
        $filters = new \Galette\Filters\MembersList();
        $filters->filter_account = \Galette\Repository\Members::INACTIVE_ACCOUNT;
        $members = new \Galette\Repository\Members($filters);
        $list = $members->getList();

        $this->assertSame(1, $list->count());

        $member_data = $list->current();
        $member = new \Galette\Entity\Adherent($this->zdb, (int)$member_data[\Galette\Entity\Adherent::PK]);

        //add member as sender for a mailing
        $mailhist = new \Galette\Core\MailingHistory($this->zdb, $this->login, $this->preferences);

        $values = array(
            'mailing_sender'            => $member->id,
            'mailing_sender_name'       => 'test',
            'mailing_sender_address'    => 'test@test.com',
            'mailing_subject'           => $this->seed,
            'mailing_body'              => 'a mailing',
            'mailing_date'              => '2015-01-01 00:00:00',
            'mailing_recipients'        => \Galette\Core\Galette::jsonEncode([]),
            'mailing_sent'              => true
        );
        $insert = $this->zdb->insert(\Galette\Core\MailingHistory::TABLE);
        $insert->values($values);
        $this->zdb->execute($insert);
        $mailing_id = $this->zdb->getLastGeneratedValue($mailhist);

        $this->assertFalse($members->removeMembers($member->id));
        $this->assertSame(['Cannot remove a member who still have dependencies (mailings, ...)'], $members->getErrors());
        $this->expectLogEntry(
            \Analog::ERROR,
            'Query error: DELETE FROM ' . ($this->zdb->isPostgres() ? '"galette_adherents"' : '`galette_adherents`')
        );
        $this->expectLogEntry(\Analog::ERROR, 'Member still have existing dependencies in the database');

        //remove mailing so member can be removed
        $this->assertTrue($mailhist->removeEntries($mailing_id, $this->history));
        $this->assertTrue($members->removeMembers($member->id));
        $this->login->logOut();
    }
}
