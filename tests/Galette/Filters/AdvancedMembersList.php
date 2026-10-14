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

        //shape stored in saved searches
        $replayed = new \Galette\Filters\AdvancedMembersList();
        $replayed->groups_search = $saved;
        $this->assertSame([0 => ['group' => (string)$gid]], $replayed->groups_search);

        $this->assertTrue($group->remove());
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
