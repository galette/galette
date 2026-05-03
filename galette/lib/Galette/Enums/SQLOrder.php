<?php

/**
 * This file is part of Galette (https://galette.eu).
 * SPDX-FileCopyrightText: Copyright © 2003-2026 The Galette Team
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

namespace Galette\Enums;

/**
 * SQL Order
 *
 * @author Johan Cwiklinski <johan@x-tnd.be>
 */
enum SQLOrder: string
{
    case ASC = 'ASC';
    case DESC = 'DESC';
}
