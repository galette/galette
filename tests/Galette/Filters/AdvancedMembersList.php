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
