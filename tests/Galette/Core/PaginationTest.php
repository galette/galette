<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Core;

use Galette\Core\Pagination;
use Galette\Core\PreferencesSchema;
use PHPUnit\Framework\TestCase;

/**
 * Pagination tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class PaginationTest extends TestCase
{
    /**
     * Usual numbers of records per page
     */
    public function testShowOptions(): void
    {
        $usual = [10 => '10', 20 => '20', 50 => '50', 100 => '100'];

        $this->assertSame($usual, Pagination::getShowOptions(20));
        //all records: the lists add their own choice
        $this->assertSame($usual, Pagination::getShowOptions(0));
    }

    /**
     * Another number is listed too, in order
     */
    public function testShowOptionsKeepCurrent(): void
    {
        $this->assertSame(
            [10 => '10', 20 => '20', 30 => '30', 50 => '50', 100 => '100'],
            Pagination::getShowOptions(30)
        );
        $this->assertSame(
            [10 => '10', 20 => '20', 50 => '50', 100 => '100', 250 => '250'],
            Pagination::getShowOptions(250)
        );
    }

    /**
     * The settings page selects the default number, it is not replaced by the first choice when saved
     */
    public function testShowOptionsHoldDefault(): void
    {
        $default = PreferencesSchema::getDefaults()['pref_numrows'];
        $this->assertArrayHasKey($default, Pagination::getShowOptions($default));
    }
}
