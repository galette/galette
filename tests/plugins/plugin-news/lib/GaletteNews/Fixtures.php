<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteNews;

use Galette\Core\Plugins\FixturesContext;
use Galette\Core\Plugins\FixturesProviderInterface;

/**
 * Unit tests disable this plugin: its fixtures must then be ignored.
 * End-to-end tests run it enabled, so it has to work nonetheless.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Fixtures implements FixturesProviderInterface
{
    /**
     * Create plugin fixtures
     *
     * @param FixturesContext $context Fixtures context
     */
    public function seedFixtures(FixturesContext $context): string
    {
        return 'Nothing created';
    }

    /**
     * Remove plugin fixtures
     *
     * @param FixturesContext $context Fixtures context
     */
    public function cleanFixtures(FixturesContext $context): void
    {
        //nothing to clean
    }
}
