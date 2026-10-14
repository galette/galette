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
