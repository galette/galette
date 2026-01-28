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

namespace Galette\Tests\Core;

use Galette\Tests\BaseGaletteTestCase;

/**
 * DB fail tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Install extends BaseGaletteTestCase
{
    protected bool $db_transactions = false;

    /**
     * Set up tests
     */
    public function setUp(): void
    {
        global $galette_log_var;
        setlocale(LC_ALL, 'en_US');
        parent::setUp();
        $galette_log_var = null; //reset error messages after dependencies have been loaded - errors are specific to tests and can be ignored
    }

    /**
     * Test if current database version is supported
     */
    public function testDbSupport(): void
    {
        $this->assertFalse($this->zdb->isEngineSUpported());
    }

    /**
     * Test if current database version is supported
     */
    public function testGetUnsupportedMessage(): void
    {
        $this->assertMatchesRegularExpression(
            '/Minimum version for .+ engine is .+, .+ .+ found!/',
            $this->zdb->getUnsupportedMessage()
        );
    }
}
