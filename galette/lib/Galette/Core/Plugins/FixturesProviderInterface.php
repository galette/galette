<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Core\Plugins;

/**
 * Fixtures provider interface
 *
 * Lets `galette:seed-fixtures` fill a plugin with sample data, tied to core
 * fixture members and groups - for end-to-end tests and demonstration.
 *
 * Unlike other provider interfaces, it is not implemented by the plugin main
 * class but by a `Fixtures` class in the plugin namespace: a plugin can ship
 * one while still running on a core that does not know this interface, as
 * nothing else than the command ever loads it.
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
interface FixturesProviderInterface
{
    /**
     * Create plugin fixtures
     *
     * Called once core fixtures are created; dates should be relative to the
     * current day, so data never looks outdated.
     *
     * @param FixturesContext $context Fixtures context
     *
     * @return string One line summary of what has been created
     */
    public function seedFixtures(FixturesContext $context): string;

    /**
     * Remove plugin fixtures
     *
     * Called before core fixtures (members, groups) are removed. It must only
     * remove what seedFixtures() creates, and work when nothing has been
     * seeded yet.
     *
     * @param FixturesContext $context Fixtures context
     */
    public function cleanFixtures(FixturesContext $context): void;
}
