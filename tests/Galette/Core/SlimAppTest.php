<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Core;

use Galette\Core\Db;
use Galette\Core\Preferences;
use Galette\Core\SlimApp;
use Galette\Tests\GaletteTestCase;

/**
 * Slim application tests
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class SlimAppTest extends GaletteTestCase
{
    /**
     * Database and preferences opened while booting are reused
     */
    public function testReuseBooted(): void
    {
        $gapp = new SlimApp($this->plugins, zdb: $this->zdb, preferences: $this->preferences);
        $this->assertSame($this->zdb, $gapp->getContainer()->get(Db::class));
        $this->assertSame($this->preferences, $gapp->getContainer()->get(Preferences::class));

        //built before the container, as while booting, then injected
        $preferences = new Preferences($this->zdb);
        $gapp = new SlimApp($this->plugins, zdb: $this->zdb, preferences: $preferences);
        $this->assertSame($preferences, $gapp->getContainer()->get(Preferences::class));
        $this->assertNotEmpty($preferences->getLegend());

        //preferences cannot be relied on while database needs an update
        $gapp = new SlimApp(
            plugins: $this->plugins,
            mode: SlimApp::MODE_NEED_UPDATE,
            zdb: $this->zdb,
            preferences: $this->preferences
        );
        $this->assertSame($this->zdb, $gapp->getContainer()->get(Db::class));
        $this->assertNotSame($this->preferences, $gapp->getContainer()->get(Preferences::class));
    }
}
