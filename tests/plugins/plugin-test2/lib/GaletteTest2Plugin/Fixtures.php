<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace GaletteTest2Plugin;

use Galette\Core\Plugins\FixturesContext;
use Galette\Core\Plugins\FixturesProviderInterface;

/**
 * Records calls, so tests can check what the fixtures command passes
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Fixtures implements FixturesProviderInterface
{
    /** @var list<array{method: string, context: FixturesContext}> */
    public static array $calls = [];

    /**
     * Create plugin fixtures
     *
     * @param FixturesContext $context Fixtures context
     */
    public function seedFixtures(FixturesContext $context): string
    {
        self::$calls[] = ['method' => 'seed', 'context' => $context];
        return 'Nothing created';
    }

    /**
     * Remove plugin fixtures
     *
     * @param FixturesContext $context Fixtures context
     */
    public function cleanFixtures(FixturesContext $context): void
    {
        self::$calls[] = ['method' => 'clean', 'context' => $context];
    }
}
