<?php

/**
 * Copyright © 2003-2024 The Galette Team
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
 * Saved searches repository tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class SavedSearches extends GaletteTestCase
{
    protected int $seed = 20240417150507;

    /**
     * Tear down tests
     *
     * @return void
     */
    public function tearDown(): void
    {
        $this->zdb->db->query(
            'TRUNCATE TABLE ' . PREFIX_DB . \Galette\Entity\SavedSearch::TABLE,
            \Laminas\Db\Adapter\Adapter::QUERY_MODE_EXECUTE
        );
        parent::tearDown();
    }

    /**
     * Test getList
     *
     * @return void
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

        $list = $searches->getList(true);
        $this->assertIsArray($list);
        $this->assertCount(1, $list);
        $this->assertSame(1, $searches->getCount());

        $result = array_pop($list);
        $this->assertInstanceOf(\Galette\Entity\SavedSearch::class, $result);

        $list = $searches->getList(false);
        $this->assertInstanceOf(\Laminas\Db\ResultSet\ResultSet::class, $list);
        $this->assertNotInstanceOf(\Galette\Entity\SavedSearch::class, $list->current());

        //another one
        $post['name'] = 'Another search';
        $this->assertTrue($saved->store());
        $sid_2 = $saved->id;
        $this->assertCount(2, $searches->getList(true));
        $this->assertSame(2, $searches->getCount());

        $post['name'] = 'Last one';
        $this->assertTrue($saved->store());
        $sid_3 = $saved->id;
        $this->assertCount(3, $searches->getList(true));
        $this->assertSame(3, $searches->getCount());

        $this->assertFalse($searches->remove([], $this->history));
        $this->assertTrue($searches->remove($sid_2, $this->history));
        $list = $searches->getList(true);
        $this->assertCount(2, $list);
        foreach ($list as $entry) {
            $this->assertNotSame($sid_2, $entry->id);
        }

        $this->assertTrue($searches->remove([$sid_1, $sid_3], $this->history));
        $list = $searches->getList(true);
        $this->assertCount(0, $list);
    }
}
