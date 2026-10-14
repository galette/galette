<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Repository;

use Galette\Tests\GaletteTestCase;

/**
 * Saved searches repository tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class SavedSearches extends GaletteTestCase
{
    protected int $seed = 20240417150507;

    /**
     * Test getList
     *
     * @throws \Throwable
     */
    public function testGetList(): void
    {
        global $i18n; // globals :(
        $i18n = $this->i18n;
        $i18n->changeLanguage('en_US');

        $saved = new \Galette\Entity\SavedSearch($this->zdb, $this->login);
        $searches = new \Galette\Repository\SavedSearches($this->zdb, $this->login);

        $post = [
            'parameters'    => [
                'filter_str'        => '',
                'field_filter'      => 0,
                'membership_filter' => 0,
                'filter_account'    => 0,
                'roup_filter'       => 0,
                'email_filter'      => 5,
                'nbshow'            => 10
            ],
            'form'          => 'Adherent',
            'name'          => 'Simple search'
        ];

        //store search
        $this->assertTrue($saved->check($post));
        $this->assertTrue($saved->store());
        $sid_1 = $saved->id;

        $list = $searches->getList(as_search: true);
        $this->assertIsArray($list);
        $this->assertCount(1, $list);
        $this->assertSame(1, $searches->getCount());

        $result = array_pop($list);
        $this->assertInstanceOf(\Galette\Entity\SavedSearch::class, $result);

        $list = $searches->getList(as_search: false);
        $this->assertInstanceOf(\Laminas\Db\ResultSet\ResultSet::class, $list);

        //another one
        $post['name'] = 'Another search';
        $this->assertTrue($saved->store());
        $sid_2 = $saved->id;
        $this->assertCount(2, $searches->getList(as_search: true));
        $this->assertSame(2, $searches->getCount());

        $post['name'] = 'Last one';
        $this->assertTrue($saved->store());
        $sid_3 = $saved->id;
        $this->assertCount(3, $searches->getList(as_search: true));
        $this->assertSame(3, $searches->getCount());

        $this->assertFalse($searches->remove([], $this->history));
        $this->expectLogEntry(
            \Analog\Analog::WARNING,
            'Asking to remove searches, but without providing an array or a single numeric value.'
        );
        $this->assertTrue($searches->remove($sid_2, $this->history));
        $list = $searches->getList(as_search: true);
        $this->assertCount(2, $list);
        foreach ($list as $entry) {
            $this->assertNotSame($sid_2, $entry->id);
        }

        $this->assertTrue($searches->remove([$sid_1, $sid_3], $this->history));
        $list = $searches->getList(as_search: true);
        $this->assertCount(0, $list);
    }

    /**
     * Test a member cannot remove searches from someone else
     *
     * @throws \Throwable
     */
    public function testRemoveOthersSearches(): void
    {
        global $i18n; // globals :(
        $i18n = $this->i18n;
        $i18n->changeLanguage('en_US');

        $this->getMemberOne();
        $this->getMemberTwo();

        $post = [
            'parameters'    => ['filter_str' => ''],
            'form'          => 'Adherent',
            'name'          => 'Member one search'
        ];

        //first member stores a search
        $m1data = $this->dataAdherentOne();
        $this->assertTrue($this->login->login($m1data['login_adh'], $m1data['mdp_adh']));
        $saved = new \Galette\Entity\SavedSearch($this->zdb, $this->login);
        $this->assertTrue($saved->check($post));
        $this->assertTrue($saved->store());
        $sid_one = $saved->id;
        $this->login->logOut();

        //second member stores a search
        $m2data = $this->dataAdherentTwo();
        $this->assertTrue($this->login->login($m2data['login_adh'], $m2data['mdp_adh']));
        $post['name'] = 'Member two search';
        $saved = new \Galette\Entity\SavedSearch($this->zdb, $this->login);
        $this->assertTrue($saved->check($post));
        $this->assertTrue($saved->store());
        $sid_two = $saved->id;

        //second member cannot remove first member search, even along with its own
        $searches = new \Galette\Repository\SavedSearches($this->zdb, $this->login);
        $this->assertFalse($searches->remove($sid_one, $this->history));
        $this->assertFalse($searches->remove([$sid_one, $sid_two], $this->history));
        $this->expectLogEntry(
            \Analog\Analog::WARNING,
            'Asking to remove searches that do not exist or are not owned by current user.'
        );

        $select = $this->zdb->select(\Galette\Entity\SavedSearch::TABLE);
        $select->where->in(\Galette\Entity\SavedSearch::PK, [$sid_one, $sid_two]);
        $this->assertSame(2, $this->zdb->execute($select)->count());

        //but can remove its own
        $this->assertTrue($searches->remove($sid_two, $this->history));
        $this->assertSame(1, $this->zdb->execute($select)->count());

        $this->login->logOut();
    }
}
