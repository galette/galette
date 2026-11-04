<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Fixtures\Routes\Duplicates;

use Galette\Controllers\Attributes\Route;

/**
 * Declares a route named "same"
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Second
{
    /**
     * Route handler
     */
    #[Route(name: 'same', pattern: '/second')]
    public function handle(): void
    {
    }
}
