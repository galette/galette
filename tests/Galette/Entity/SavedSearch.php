<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Entity;

use Galette\Tests\GaletteTestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

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
    #[AllowMockObjectsWithoutExpectations]
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
        $this->assertCount(1, $searches->getList(as_search: true));
        //store again: same search is updated
        $this->assertTrue($saved->store());
        $this->assertCount(1, $searches->getList(as_search: true));
        //store a new one, got a duplicate
        $duplicate = new \Galette\Entity\SavedSearch($this->zdb, $this->login);
        $this->assertTrue($duplicate->check($post));
        $this->assertTrue($duplicate->store());
        $this->assertCount(2, $searches->getList(as_search: true));
    }

    /**
     * Test saved search update, with relative dates
     */
    #[AllowMockObjectsWithoutExpectations]
    public function testUpdate(): void
    {
        global $i18n; // globals :(
        $i18n = $this->i18n;
        $i18n->changeLanguage('en_US');

        $saved = new \Galette\Entity\SavedSearch($this->zdb, $this->login);
        $this->assertTrue(
            $saved->check(
                [
                    'parameters'    => [
                        'due_date_begin'    => '@today',
                        'due_date_end'      => '@today+30d',
                        'free_search'       => []
                    ],
                    'form'          => 'Adherent',
                    'name'          => 'Due soon'
                ]
            )
        );
        $this->assertTrue($saved->store());
        $id = $saved->id;

        $loaded = new \Galette\Entity\SavedSearch($this->zdb, $this->login, $id);
        $this->assertSame('Due soon', $loaded->name);
        $this->assertSame('@today+30d', $loaded->parameters['due_date_end']);

        $today = new \Safe\DateTimeImmutable();
        $sparameters = $loaded->sparameters;
        $this->assertSame('today (' . $today->format('Y-m-d') . ')', $sparameters['due_date_begin']);
        $this->assertSame(
            'today plus 30 days (' . $today->modify('+30 days')->format('Y-m-d') . ')',
            $sparameters['due_date_end']
        );

        //rename and change parameters
        $this->assertTrue(
            $loaded->check(
                [
                    'form'          => $loaded->form,
                    'name'          => 'Due within two months',
                    'parameters'    => [
                        'due_date_begin'    => '@today',
                        'due_date_end'      => '@today+2m',
                        'free_search'       => []
                    ]
                ]
            )
        );
        $this->assertTrue($loaded->store());

        $reloaded = new \Galette\Entity\SavedSearch($this->zdb, $this->login, $id);
        $this->assertSame($id, $reloaded->id);
        $this->assertSame('Due within two months', $reloaded->name);
        $this->assertSame('@today+2m', $reloaded->parameters['due_date_end']);
        $this->assertSame($loaded->creation_date, $reloaded->creation_date);
    }
}
