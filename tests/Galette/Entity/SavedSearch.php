<?php

/**
 * Copyright © 2003-2026 The Galette Team
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

namespace Galette\Tests\Entity;

use Galette\Tests\GaletteTestCase;

/**
 * Saved search tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class SavedSearch extends GaletteTestCase
{
    /**
     * Set up tests
     */
    public function setUp(): void
    {
        parent::setUp();
        $this->login = $this->getMockBuilder(\Galette\Core\Login::class)
            ->setConstructorArgs([$this->zdb, new \Galette\Core\I18n()])
            ->onlyMethods(['isLogged', 'isSuperAdmin', '__get'])
            ->getMock();
        $this->login->method('isLogged')->willReturn(true);
        $this->login->method('isSuperAdmin')->willReturn(true);
        $this->login->method('__get')->willReturn(0);
    }

    /**
     * Test saved search
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
