<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
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
    protected string $app_mode = 'INSTALL';

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
