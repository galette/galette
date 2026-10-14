<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Filters;

use Galette\Tests\GaletteTestCase;

/**
 * Advanced members filters tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class AdvancedMembersList extends GaletteTestCase
{
    /**
     * Test saved parameters are language neutral
     */
    public function testSearchParameters(): void
    {
        $this->i18n->changeLanguage('fr_FR');

        $filters = new \Galette\Filters\AdvancedMembersList();
        $filters->creation_date_begin = '15/03/2024';

        //displayed in user language...
        $this->assertSame('15/03/2024', $filters->creation_date_begin);

        //... but saved in ISO format
        $parameters = $filters->getSearchParameters();
        $this->assertSame('2024-03-15', $parameters['creation_date_begin']);
        $this->assertNull($parameters['birth_date_begin']);

        //ISO format is accepted whatever the language
        $replayed = new \Galette\Filters\AdvancedMembersList();
        foreach ($parameters as $key => $value) {
            $replayed->$key = $value;
        }
        $this->assertSame('2024-03-15', $replayed->rcreation_date_begin);

        $this->i18n->changeLanguage('en_US');
    }

    /**
     * Test groups as stored in saved searches
     */
    public function testSavedGroups(): void
    {
        $group = new \Galette\Entity\Group();
        $group->setName('Relative dates group');
        $this->assertTrue($group->store());
        $gid = $group->getId();

        $filters = new \Galette\Filters\AdvancedMembersList();
        //shape sent by the form
        $filters->groups_search = ['idx' => 0, 'group' => (string)$gid];
        $saved = $filters->groups_search;
        $this->assertSame([0 => ['group' => (string)$gid]], $saved);

        //shape stored in saved searches, with a removed group
        $replayed = new \Galette\Filters\AdvancedMembersList();
        $replayed->groups_search = $saved + [1 => ['group' => '999999']];
        $this->assertSame([0 => ['group' => (string)$gid]], $replayed->groups_search);
        $this->assertSame(['group #999999 no longer exists'], $replayed->getDiscarded());
        $this->expectLogEntry(\Analog\Analog::WARNING, 'Group #999999 does not exists!');

        $this->assertTrue($group->remove());
    }

    /**
     * Test removed references are reported
     */
    public function testDiscarded(): void
    {
        $filters = new \Galette\Filters\AdvancedMembersList();
        $filters->status = [999];
        $filters->contributions_types = [999];
        $filters->payments_types = [999];
        $filters->group_filter = 999999;
        $filters->free_search = [
            'idx' => 1,
            'field' => 'dyn_999',
            'type' => \Galette\DynamicFields\DynamicField::LINE,
            'search' => 'value',
            'log_op' => $filters::OP_AND,
            'qry_op' => $filters::OP_EQUALS
        ];
        $filters->contrib_dynamic = [999 => 'value'];

        $this->assertSame(
            [
                'status #999 no longer exists',
                'contribution type #999 no longer exists',
                'payment type #999 no longer exists',
                'group #999999 no longer exists',
                'dynamic field #999 no longer exists',
                'dynamic field #999 no longer exists',
            ],
            $filters->getDiscarded()
        );
        $this->assertArrayNotHasKey(1, $filters->getSearchParameters()['free_search']);
        $this->assertSame([], $filters->contrib_dynamic);
        $this->expectLogEntry(\Analog\Analog::WARNING, 'Status #999 does not exists!');
        $this->expectLogEntry(\Analog\Analog::WARNING, 'Contribution type #999 does not exists!');
        $this->expectLogEntry(\Analog\Analog::WARNING, 'Payment type #999 does not exists!');
        $this->expectLogEntry(\Analog\Analog::WARNING, 'Group #999999 does not exists!');
        $this->expectLogEntry(\Analog\Analog::WARNING, 'Dynamic field dyn_999 does not exists!');
        $this->expectLogEntry(\Analog\Analog::WARNING, 'Contribution dynamic field #999 does not exists!');
    }

    /**
     * Test reinit resets every date
     */
    public function testReinit(): void
    {
        $filters = new \Galette\Filters\AdvancedMembersList();
        $filters->contrib_end_date_end = '2025-12-31';
        $this->assertSame('2025-12-31', $filters->rcontrib_end_date_end);
        $filters->reinit();
        $this->assertNull($filters->contrib_end_date_end);
    }
}
