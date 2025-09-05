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

namespace Galette\Entity\test\units;

use PHPUnit\Framework\TestCase;
use Laminas\Db\Adapter\Adapter;

/**
 * Saved search tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class SavedSearch extends TestCase
{
    private \Galette\Core\Db $zdb;
    private \Galette\Core\I18n $i18n;
    private \Galette\Core\Login $login;

    /**
     * Set up tests
     *
     * @return void
     */
    public function setUp(): void
    {
        $this->zdb = new \Galette\Core\Db();
        $this->i18n = new \Galette\Core\I18n();

        $this->login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs([$this->zdb, new \Galette\Core\I18n()])
            ->onlyMethods(['isLogged', 'isSuperAdmin', '__get'])
            ->getMock();
        $this->login->method('isLogged')->willReturn(true);
        $this->login->method('isSuperAdmin')->willReturn(true);
        $this->login->method('__get')->willReturn(0);
    }

    /**
     * Tear down tests
     *
     * @return void
     */
    public function tearDown(): void
    {
        if (TYPE_DB === 'mysql') {
            $this->assertSame([], $this->zdb->getWarnings());
        }
        $this->deleteCreated();
    }

    /**
     * Delete status
     *
     * @return void
     */
    private function deleteCreated(): void
    {
        $this->zdb->db->query(
            'TRUNCATE TABLE ' . PREFIX_DB . \Galette\Entity\SavedSearch::TABLE,
            \Laminas\Db\Adapter\Adapter::QUERY_MODE_EXECUTE
        );
    }

    /**
     * Test saved search
     *
     * @return void
     */
    public function testSave(): void
    {
        global $i18n, $translator; // globals :(
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

        $errored = $post;
        unset($errored['form']);
        $this->assertFalse($saved->check($errored));
        $this->assertSame(['form' => 'Form is mandatory!'], $saved->getErrors());

        //store search
        $this->assertTrue($saved->check($post));
        $this->assertTrue($saved->store());
        $this->assertCount(1, $searches->getList(true));
        //store again, got a duplicate
        $this->assertTrue($saved->store());
        $this->assertCount(2, $searches->getList(true));
    }
}
