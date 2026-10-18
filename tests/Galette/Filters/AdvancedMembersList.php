<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Filters;

use Galette\Tests\GaletteTestCase;
use Safe\DateTime;

/**
 * Advanced members filters tests class
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class AdvancedMembersList extends GaletteTestCase
{
    /**
     * Test relative date bounds
     */
    public function testRelativeDates(): void
    {
        $filters = new \Galette\Filters\AdvancedMembersList();

        $filters->due_date_begin = '@today';
        $filters->due_date_end = '@today+30d';

        //expression is kept as is...
        $this->assertSame('@today', $filters->due_date_begin);
        $this->assertSame('@today+30d', $filters->due_date_end);
        //... and resolved for queries
        $today = new DateTime();
        $this->assertSame($today->format('Y-m-d'), $filters->rdue_date_begin);
        $this->assertSame($today->modify('+30 days')->format('Y-m-d'), $filters->rdue_date_end);
        $this->assertSame([], $filters->getDiscarded());

        //invalid expression is dropped, and reported
        $filters->birth_date_end = '@tomorrow';
        $this->assertNull($filters->birth_date_end);
        $this->assertNull($filters->rbirth_date_end);
        $this->assertSame(['Birth date: invalid date "@tomorrow"'], $filters->getDiscarded());
        $this->expectLogEntry(\Analog\Analog::WARNING, 'Invalid relative date `@tomorrow` for Birth date');

        //regular dates still work
        $filters->creation_date_begin = '2024-03-15';
        $this->assertSame('2024-03-15', $filters->rcreation_date_begin);
    }

    /**
     * Test membership period anchors are reported when unavailable
     */
    public function testUnavailablePeriod(): void
    {
        $this->setRawPreference('pref_beg_membership', '');
        $this->setRawPreference('pref_membership_ext', 12);

        $filters = new \Galette\Filters\AdvancedMembersList();
        $filters->contrib_begin_date_begin = '@period_start';
        $this->assertNull($filters->contrib_begin_date_begin);
        $this->assertSame(
            ['Contribution begin date: "start of the membership period" is not available'],
            $filters->getDiscarded()
        );
        $this->expectLogEntry(\Analog\Analog::WARNING, 'Unable to resolve `@period_start`');

        //available once memberships share a beginning date
        $this->setRawPreference('pref_beg_membership', '01/01');
        $filters = new \Galette\Filters\AdvancedMembersList();
        $filters->contrib_begin_date_begin = '@period_start';
        $this->assertSame('@period_start', $filters->contrib_begin_date_begin);
        $this->assertSame((new DateTime())->format('Y') . '-01-01', $filters->rcontrib_begin_date_begin);
    }

    /**
     * Test relative date in free search
     */
    public function testFreeSearchRelativeDate(): void
    {
        $filters = new \Galette\Filters\AdvancedMembersList();
        $filters->free_search = [
            'idx' => 1,
            'field' => 'ddn_adh',
            'type' => \Galette\DynamicFields\DynamicField::DATE,
            'search' => '@today-18y',
            'log_op' => $filters::OP_AND,
            'qry_op' => $filters::OP_BEFORE
        ];
        $free_search = $filters->getSearchParameters()['free_search'];
        $this->assertIsArray($free_search);
        $this->assertSame('@today-18y', $free_search[1]['search']);
        $this->assertSame(
            (new DateTime())->modify('-18 years')->format('Y-m-d'),
            $filters->resolveDate($free_search[1]['search'])
        );

        //invalid one is not kept
        $filters->free_search = [
            'idx' => 2,
            'field' => 'ddn_adh',
            'type' => \Galette\DynamicFields\DynamicField::DATE,
            'search' => '@yesterday',
            'log_op' => $filters::OP_AND,
            'qry_op' => $filters::OP_BEFORE
        ];
        $this->assertArrayNotHasKey(2, $filters->free_search);
        $this->assertCount(1, $filters->getDiscarded());
        $this->expectLogEntry(\Analog\Analog::WARNING, 'Invalid relative date `@yesterday` for ddn_adh');
    }

    /**
     * Test saved parameters are language neutral
     */
    public function testSearchParameters(): void
    {
        $this->i18n->changeLanguage('fr_FR');

        $filters = new \Galette\Filters\AdvancedMembersList();
        $filters->creation_date_begin = '15/03/2024';
        $filters->due_date_end = '@today+1m';

        //displayed in user language...
        $this->assertSame('15/03/2024', $filters->creation_date_begin);

        //... but saved in ISO format
        $parameters = $filters->getSearchParameters();
        $this->assertSame('2024-03-15', $parameters['creation_date_begin']);
        $this->assertSame('@today+1m', $parameters['due_date_end']);
        $this->assertNull($parameters['birth_date_begin']);

        //ISO format is accepted whatever the language
        $replayed = new \Galette\Filters\AdvancedMembersList();
        foreach ($parameters as $key => $value) {
            $replayed->$key = $value;
        }
        $this->assertSame('2024-03-15', $replayed->rcreation_date_begin);
        $this->assertSame('@today+1m', $replayed->due_date_end);

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
