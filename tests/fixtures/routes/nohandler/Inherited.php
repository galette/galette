<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Tests\Fixtures\Routes\Nohandler;

use Galette\Controllers\Attributes\Route;

/**
 * Declares a route on the class, without naming its handler
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
#[Route(name: 'inherited', pattern: '/inherited')]
class Inherited
{
}
