<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Fixtures\Routes\Methodhandler;

use Galette\Controllers\Attributes\Route;

/**
 * Declares a route on a method, naming another method as its handler
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
class Named
{
    /**
     * Route handler
     */
    #[Route(name: 'named', pattern: '/named', handler: 'other')]
    public function page(): void
    {
    }

    /**
     * Method named as handler
     */
    public function other(): void
    {
    }
}
